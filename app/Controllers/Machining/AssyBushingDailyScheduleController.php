<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyBushingDailyScheduleController extends BaseController
{
    /* =====================================================
     * GUARD: role & tanggal (redirect)
     * ===================================================== */
    private function guardScheduleDateByRoleRedirect(string $date)
    {
        $role = session()->get('role') ?? '';

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d) {
            return redirect()->back()->with('error', 'Format tanggal tidak valid');
        }
        $d->setTime(0, 0, 0);

        $today = new \DateTime(date('Y-m-d'));
        $today->setTime(0, 0, 0);

        if ($role !== 'ADMIN' && $d <= $today) {
            return redirect()->back()->with(
                'error',
                'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh mulai besok.'
            );
        }

        return null;
    }

    /* =====================================================
     * PROCESS ID Assy Bushing (robust)
     * ===================================================== */
    private function getProcessIdAssyBushing($db): int
    {
        // 1) by process_code AB
        if ($db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')->select('id')
                ->where('process_code', 'AB')
                ->get()->getRowArray();
            if ($row && !empty($row['id'])) return (int)$row['id'];
        }

        // 2) exact name
        $exact = ['Assy Bushing', 'ASSY BUSHING', 'AssyBushing', 'ASSYBUSHING', 'AB'];
        foreach ($exact as $name) {
            $row = $db->table('production_processes')->select('id')
                ->where('process_name', $name)
                ->get()->getRowArray();
            if ($row && !empty($row['id'])) return (int)$row['id'];
        }

        // 3) like
        $row = $db->table('production_processes')->select('id')
            ->like('process_name', 'BUSHING')
            ->get()->getRowArray();
        if ($row && !empty($row['id'])) return (int)$row['id'];

        throw new \Exception('Process "Assy Bushing" belum ada / tidak terdeteksi di master production_processes');
    }

    /* =====================================================
     * SHIFT seconds
     * ===================================================== */
    private function getTotalSecondShift($db, int $shiftId): int
    {
        if (!$db->tableExists('shift_time_slots') || !$db->tableExists('time_slots')) {
            return 0;
        }

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()->getResultArray();

        $totalSecond = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400;
            $totalSecond += ($end - $start);
        }

        return (int)$totalSecond;
    }

    /* =====================================================
     * VALIDATE product punya flow AB aktif
     * ===================================================== */
    private function validateProductHasFlow($db, int $productId, int $processId): bool
    {
        if (!$db->tableExists('product_process_flows')) return false;

        return $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processId)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /* =====================================================
     * resolve PREV process by flow (sequence < current)
     * ===================================================== */
    private function resolvePrevProcessByFlow($db, int $productId, int $currentProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;

        $currentFlow = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1,
            ])
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getRowArray();

        if (!$currentFlow) return null;
        $curSeq = (int)$currentFlow['sequence'];

        $prevFlow = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->where('sequence <', $curSeq)
            ->orderBy('sequence', 'DESC')
            ->get()
            ->getRowArray();

        if (!$prevFlow) return null;
        return (int)$prevFlow['process_id'];
    }

    /* =====================================================
     * Detect date col production_wip
     * ===================================================== */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    /* =====================================================
     * Detect stock col production_wip
     * ===================================================== */
    private function detectWipStockColumn($db): ?string
    {
        foreach (['stock', 'stock_qty', 'qty_stock'] as $c) {
            if ($db->fieldExists($c, 'production_wip')) return $c;
        }
        return null;
    }

    private function ensureTransferColumn($db): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->fieldExists('transfer', 'production_wip')) {
            throw new \Exception('Kolom transfer belum ada di production_wip. Jalankan migration tambah kolom transfer.');
        }
    }

    /* =========================================================
     * Ambil ROW stock terbaru untuk prev process (carry-over)
     * ========================================================= */
    private function getPrevProcessLatestRow($db, string $date, int $productId, int $prevProcessId): ?array
    {
        if (!$db->tableExists('production_wip')) return null;

        $dateCol  = $this->detectWipDateColumn($db);
        $stockCol = $this->detectWipStockColumn($db);
        if (!$stockCol) return null;

        $builder = $db->table('production_wip pw')
            ->select("pw.id, pw.$stockCol as stock_val, pw.transfer, pw.qty_in, pw.qty_out")
            ->where('pw.product_id', $productId)
            ->where('pw.to_process_id', $prevProcessId);

        $builder->where("DATE(pw.$dateCol) <=", $date, false);

        $row = $builder
            ->orderBy("DATE(pw.$dateCol)", 'DESC', false)
            ->orderBy("pw.id", 'DESC')
            ->get()
            ->getRowArray();

        if ($row) return $row;

        // fallback latest
        return $db->table('production_wip pw')
            ->select("pw.id, pw.$stockCol as stock_val, pw.transfer, pw.qty_in, pw.qty_out")
            ->where('pw.product_id', $productId)
            ->where('pw.to_process_id', $prevProcessId)
            ->orderBy("pw.id", 'DESC')
            ->get()
            ->getRowArray();
    }

    private function getPrevProcessStock($db, string $date, int $productId, int $prevProcessId): int
    {
        $row = $this->getPrevProcessLatestRow($db, $date, $productId, $prevProcessId);
        return (int)($row['stock_val'] ?? 0);
    }

    /* =========================================================
     * Reserve/Release prev -> next:
     * ========================================================= */
    private function applyPrevReserveToNext($db, string $date, int $productId, int $prevProcessId, int $deltaQty): void
    {
        if ($deltaQty === 0) return;
        if (!$db->tableExists('production_wip')) return;

        $this->ensureTransferColumn($db);

        $stockCol = $this->detectWipStockColumn($db);
        if (!$stockCol) return;

        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $now = date('Y-m-d H:i:s');

        $prevRow = $this->getPrevProcessLatestRow($db, $date, $productId, $prevProcessId);
        if (!$prevRow) return;

        $rowId       = (int)$prevRow['id'];
        $curStock    = (int)($prevRow['stock_val'] ?? 0);
        $curTransfer = (int)($prevRow['transfer'] ?? 0);

        $newStock = $curStock - $deltaQty;
        if ($newStock < 0) $newStock = 0;

        $newTransfer = $curTransfer + $deltaQty;
        if ($newTransfer < 0) $newTransfer = 0;

        $upd = [
            $stockCol  => $newStock,
            'transfer' => $newTransfer,
        ];
        if ($hasUpdatedAt) $upd['updated_at'] = $now;

        $db->table('production_wip')->where('id', $rowId)->update($upd);
    }

    /* =========================================================
     * Incoming WIP untuk Assy Bushing saat schedule:
     * qty_in bertambah, stock AB tetap 0
     * ========================================================= */
    private function upsertIncomingWipForAssyBushing(
        $db,
        string $date,
        int $productId,
        int $fromPrevProcessId,
        int $toAssyProcessId,
        int $scheduleItemId,
        int $deltaQty
    ): void {
        if ($deltaQty === 0) return;
        if (!$db->tableExists('production_wip')) return;

        $dateCol = $this->detectWipDateColumn($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQty    = $db->fieldExists('qty', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

        if (!$hasQtyIn) throw new \Exception('Kolom qty_in tidak ada di production_wip');

        $now = date('Y-m-d H:i:s');

        $key = [
            $dateCol          => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromPrevProcessId,
            'to_process_id'   => $toAssyProcessId,
            'source_table'    => 'daily_schedule_items',
            'source_id'       => $scheduleItemId,
        ];

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

        if ($exist) {
            $newQtyIn = (int)($exist['qty_in'] ?? 0) + $deltaQty;
            if ($newQtyIn < 0) $newQtyIn = 0;

            $upd = [
                'qty_in' => $newQtyIn,
                'status' => ($newQtyIn > 0) ? 'WAITING' : ($exist['status'] ?? 'WAITING'),
            ];
            if ($hasQty)    $upd['qty'] = $newQtyIn;
            if ($hasQtyOut) $upd['qty_out'] = (int)($exist['qty_out'] ?? 0);

            // ✅ stock tetap 0 (belum diproses)
            if ($hasStock)  $upd['stock'] = 0;

            if ($db->fieldExists('transfer', 'production_wip')) $upd['transfer'] = (int)($exist['transfer'] ?? 0);
            if ($hasUpdatedAt) $upd['updated_at'] = $now;

            $db->table('production_wip')->where('id', (int)$exist['id'])->update($upd);
            return;
        }

        $insQty = max(0, $deltaQty);
        if ($insQty <= 0) return;

        $ins = $key + [
            'qty_in' => $insQty,
            'status' => 'WAITING',
        ];
        if ($hasQty)    $ins['qty'] = $insQty;
        if ($hasQtyOut) $ins['qty_out'] = 0;
        if ($hasStock)  $ins['stock'] = 0;
        if ($db->fieldExists('transfer', 'production_wip')) $ins['transfer'] = 0;
        if ($hasCreatedAt) $ins['created_at'] = $now;
        if ($hasUpdatedAt) $ins['updated_at'] = $now;

        $db->table('production_wip')->insert($ins);
    }

    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
    {
        $db = db_connect();

        $date = trim((string)$this->request->getGet('date'));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id', 'left')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Bushing')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'].'_'.$e['machine_id']] = $e;
        }

        $actualMap = [];
        if ($db->tableExists('machining_assy_bushing_hourly')) {
            $actuals = $db->table('machining_assy_bushing_hourly')
                ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
                ->where('production_date', $date)
                ->groupBy('shift_id, machine_id, product_id')
                ->get()->getResultArray();

            foreach ($actuals as $a) {
                $actualMap[$a['shift_id'].'_'.$a['machine_id'].'_'.$a['product_id']] = $a;
            }
        }

        return view('machining/assy_bushing_schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /* =====================================================
     * AJAX: product + target + stock_prev (untuk view)
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = (int)($this->request->getGet('machine_id') ?? 0);
        $shiftId   = (int)($this->request->getGet('shift_id') ?? 0);
        $date      = (string)($this->request->getGet('date') ?? date('Y-m-d'));

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdAB = $this->getProcessIdAssyBushing($db);

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        // kalau shift kosong, tetap tampilkan produk, target = 0

        $q = $db->table('product_process_flows ppf')
            ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdAB);

        // Optional: filter by machine_products (kalau ada)
        if ($machineId > 0 && $db->tableExists('machine_products')) {
            $q->join(
                'machine_products mp',
                'mp.product_id = p.id AND mp.machine_id = '.$machineId.' AND mp.is_active=1',
                'left'
            );
        }

        $products = $q->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()->getResultArray();

        $out = [];
        foreach ($products as $p) {
            $pid    = (int)($p['id'] ?? 0);
            $cycle  = (int)($p['cycle_time'] ?? 0);
            $cavity = (int)($p['cavity'] ?? 0);

            $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            $targetShift = 0;
            if ($totalSecond > 0 && $cycle > 0 && $cavity > 0) {
                $targetShift = (int)min(floor(($totalSecond / $cycle) * $cavity * $eff), 1200);
            }

            $prevId = $this->resolvePrevProcessByFlow($db, $pid, $processIdAB);
            $stockPrev = $prevId ? $this->getPrevProcessStock($db, $date, $pid, (int)$prevId) : 0;

            $out[] = [
                'id'               => $pid,
                'part_no'          => (string)($p['part_no'] ?? ''),
                'part_name'        => (string)($p['part_name'] ?? ''),
                'cycle_time_used'  => $cycle,
                'target_per_shift' => $targetShift,
                'prev_process_id'  => (int)($prevId ?? 0),
                'stock_prev'       => (int)$stockPrev,
            ];
        }

        return $this->response->setJSON($out);
    }

    /* =====================================================
     * STORE — MACHINING STYLE WIP FLOW
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $date  = trim((string)($this->request->getPost('date') ?? ''));
        $items = $this->request->getPost('items');

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $deny = $this->guardScheduleDateByRoleRedirect($date);
        if ($deny) return $deny;

        $processIdAB = $this->getProcessIdAssyBushing($db);
        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0) continue;

                $planInput = (int)($row['target_per_shift'] ?? 0);
                if ($planInput < 0) $planInput = 0;
                if ($planInput > 1200) $planInput = 1200;

                if ($productId <= 0) continue;

                if (!$this->validateProductHasFlow($db, $productId, $processIdAB)) {
                    throw new \Exception("Product ID {$productId} tidak punya flow Assy Bushing aktif.");
                }

                // ===== header daily_schedules (1 per shift) =====
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Bushing',
                    ])
                    ->get()->getRowArray();

                if (!$schedule) {
                    $header = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Bushing',
                        'is_completed'  => 0,
                        'created_at'    => $now,
                    ];
                    if ($db->fieldExists('process_id', 'daily_schedules')) $header['process_id'] = $processIdAB;
                    if ($db->fieldExists('updated_at', 'daily_schedules')) $header['updated_at'] = $now;

                    $db->table('daily_schedules')->insert($header);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];
                    if ($db->fieldExists('process_id', 'daily_schedules') && empty($schedule['process_id'])) {
                        $upd = ['process_id' => $processIdAB];
                        if ($db->fieldExists('updated_at', 'daily_schedules')) $upd['updated_at'] = $now;
                        $db->table('daily_schedules')->where('id', $scheduleId)->update($upd);
                    }
                }

                // ===== cek item lama (delta) =====
                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'machine_id'        => $machineId,
                    ])
                    ->get()->getRowArray();

                $oldProductId = $existItem ? (int)($existItem['product_id'] ?? 0) : 0;
                $oldPlan      = $existItem ? (int)($existItem['target_per_shift'] ?? 0) : 0;

                $prevProcessIdNew = $this->resolvePrevProcessByFlow($db, $productId, $processIdAB);

                // release old jika product diganti
                if ($existItem && $oldProductId > 0 && $oldProductId !== $productId) {
                    $prevOld = $this->resolvePrevProcessByFlow($db, $oldProductId, $processIdAB);
                    if ($prevOld && $oldPlan > 0) {
                        // release = delta negatif
                        $this->applyPrevReserveToNext($db, $date, $oldProductId, $prevOld, -$oldPlan);
                        $this->upsertIncomingWipForAssyBushing($db, $date, $oldProductId, $prevOld, $processIdAB, (int)$existItem['id'], -$oldPlan);
                    }
                    $oldPlan = 0;
                }

                // master product (CT & cavity)
                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()->getRowArray();

                if (!$product) throw new \Exception("Product ID {$productId} tidak ditemukan.");

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                if ($cycle <= 0 || $cavity <= 0) {
                    throw new \Exception("Cycle time / cavity belum valid untuk Product ID {$productId}.");
                }

                $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;
                $targetPerHour = (int)floor((3600 / $cycle) * $cavity * $eff);

                // upsert schedule item
                $dataItem = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $planInput,
                    'is_selected'       => ($planInput > 0) ? 1 : 0,
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')->where('id', (int)$existItem['id'])->update($dataItem);
                    $itemId = (int)$existItem['id'];
                } else {
                    if ($planInput <= 0) continue;
                    $db->table('daily_schedule_items')->insert($dataItem);
                    $itemId = (int)$db->insertID();
                }

                // apply delta (reserve stock prev + incoming AB)
                if ($prevProcessIdNew) {
                    $delta = $planInput - $oldPlan;

                    if ($delta > 0) {
                        $stockPrev = $this->getPrevProcessStock($db, $date, $productId, $prevProcessIdNew);
                        if ($stockPrev <= 0) throw new \Exception("Stock kosong pada proses sebelumnya untuk Product ID {$productId}.");
                        if ($delta > $stockPrev) throw new \Exception("Scheduling tambahan ({$delta}) > stock ({$stockPrev}) untuk Product ID {$productId}.");
                    }

                    if ($delta !== 0) {
                        $this->applyPrevReserveToNext($db, $date, $productId, $prevProcessIdNew, $delta);
                        $this->upsertIncomingWipForAssyBushing($db, $date, $productId, $prevProcessIdNew, $processIdAB, $itemId, $delta);
                    }
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Schedule Assy Bushing tersimpan. Stock prev berkurang + transfer tercatat + qty_in Assy Bushing bertambah (stock Assy Bushing tetap 0).'
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
