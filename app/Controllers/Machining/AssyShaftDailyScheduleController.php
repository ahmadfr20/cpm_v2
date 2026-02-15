<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftDailyScheduleController extends BaseController
{
    /* ============================================
     * Helper: ambil process_id Assy Shaft
     * ============================================ */
    private function getProcessIdAssyShaft($db): int
    {
        // prioritas pakai process_code = AS jika ada
        if ($db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_code', 'AS')
                ->get()
                ->getRowArray();
            if ($row) return (int)$row['id'];
        }

        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Assy Shaft')
            ->get()
            ->getRowArray();

        if (!$row) {
            throw new \Exception('Process "Assy Shaft" belum ada di master production_processes');
        }

        return (int)$row['id'];
    }

    /* ============================================
     * Helper: hitung total detik shift
     * ============================================ */
    private function getTotalSecondShift($db, int $shiftId): int
    {
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()
            ->getResultArray();

        $totalSecond = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400;
            $totalSecond += ($end - $start);
        }

        return (int)$totalSecond;
    }

    /* ============================================
     * Helper: validasi product punya flow Assy Shaft aktif
     * ============================================ */
    private function validateProductHasFlow($db, int $productId, int $processId): bool
    {
        return $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processId)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /* =====================================================
     * Helper: resolve PREV process by flow (sequence < current)
     * ===================================================== */
    private function resolvePrevProcessByFlow($db, int $productId, int $currentProcessId): ?int
    {
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

    /* ============================================
     * Helper: detect date column production_wip
     * ============================================ */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    /* ============================================
     * Helper: detect stock column production_wip
     * ============================================ */
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
     * RESERVE/RELEASE dari prev process untuk dikirim ke Assy Shaft
     * - stock prev berkurang saat reserve
     * - transfer prev bertambah saat reserve
     * ========================================================= */
    private function applyPrevReserveToNext(
        $db,
        string $date,
        int $productId,
        int $prevProcessId,
        int $deltaQty
    ): void {
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

        $newStock = $curStock - $deltaQty;       // reserve (delta+) => turun
        if ($newStock < 0) $newStock = 0;

        $newTransfer = $curTransfer + $deltaQty; // reserve => naik
        if ($newTransfer < 0) $newTransfer = 0;

        $update = [
            $stockCol  => $newStock,
            'transfer' => $newTransfer,
        ];

        if ($hasUpdatedAt) $update['updated_at'] = $now;

        $db->table('production_wip')->where('id', $rowId)->update($update);
    }

    /* =========================================================
     * Incoming Assy Shaft saat schedule:
     * qty_in bertambah, stock tetap 0 (sama persis machining)
     * ========================================================= */
    private function upsertIncomingWipAssyShaft(
        $db,
        string $date,
        int $productId,
        int $fromPrevProcessId,
        int $toAssyShaftProcessId,
        int $scheduleItemId,
        int $deltaQty
    ): void {
        if ($deltaQty === 0) return;
        if (!$db->tableExists('production_wip')) return;

        $dateCol = $this->detectWipDateColumn($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasQty    = $db->fieldExists('qty', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

        if (!$hasQtyIn) throw new \Exception('Kolom qty_in tidak ada di production_wip');

        $now = date('Y-m-d H:i:s');

        $key = [
            $dateCol          => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromPrevProcessId,
            'to_process_id'   => $toAssyShaftProcessId,
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

            // ✅ stock tetap 0
            if ($hasStock)  $upd['stock'] = 0;

            if ($hasUpdatedAt) $upd['updated_at'] = $now;

            $db->table('production_wip')->where('id', (int)$exist['id'])->update($upd);
            return;
        }

        $qtyIn = max(0, $deltaQty);
        if ($qtyIn <= 0) return;

        $ins = $key + [
            'qty_in' => $qtyIn,
            'status' => 'WAITING',
        ];

        if ($hasQty)    $ins['qty'] = $qtyIn;
        if ($hasQtyOut) $ins['qty_out'] = 0;
        if ($hasStock)  $ins['stock'] = 0;

        if ($db->fieldExists('transfer', 'production_wip')) $ins['transfer'] = 0;

        if ($hasCreatedAt) $ins['created_at'] = $now;
        if ($hasUpdatedAt) $ins['updated_at'] = $now;

        $db->table('production_wip')->insert($ins);
    }

    /* ============================================
     * INDEX (view)
     * ============================================ */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date');
        if (empty($date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        // mesin tetap ambil dari process Machining (sesuai file kamu sebelumnya)
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Shaft')
            ->get()
            ->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        $actualMap = [];
        if ($db->tableExists('machining_assy_shaft_hourly')) {
            $actuals = $db->table('machining_assy_shaft_hourly')
                ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
                ->where('production_date', $date)
                ->groupBy('shift_id, machine_id, product_id')
                ->get()
                ->getResultArray();

            foreach ($actuals as $a) {
                $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
            }
        }

        return view('machining/assy_shaft_schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /* ============================================
     * ✅ AJAX: PRODUCT + TARGET + STOCK PREV
     * ============================================ */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');
        $date    = (string)($this->request->getGet('date') ?? date('Y-m-d'));

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdAS = $this->getProcessIdAssyShaft($db);

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        if ($totalSecond <= 0) return $this->response->setJSON([]);

        // opsional: kalau kamu punya CT khusus assy shaft
        $hasCtAS = $db->fieldExists('cycle_time_assy_shaft', 'products');

        $products = $db->table('product_process_flows ppf')
            ->select(
                'p.id, p.part_no, p.part_name, p.cavity, p.efficiency_rate'
                . ($hasCtAS ? ', p.cycle_time_assy_shaft' : ', p.cycle_time')
            )
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdAS)
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($products as &$p) {
            $cycle  = $hasCtAS ? (int)($p['cycle_time_assy_shaft'] ?? 0) : (int)($p['cycle_time'] ?? 0);
            $cavity = (int)($p['cavity'] ?? 0);

            $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            $p['cycle_time_used'] = $cycle;

            if ($cycle > 0 && $cavity > 0) {
                $targetShift = floor(($totalSecond / $cycle) * $cavity * $eff);
                $targetHour  = floor((3600 / $cycle) * $cavity * $eff);

                $p['target_per_shift'] = min((int)$targetShift, 1200);
                $p['target_per_hour']  = (int)$targetHour;
            } else {
                $p['target_per_shift'] = 0;
                $p['target_per_hour']  = 0;
            }

            // prev flow + stock prev (carry-over)
            $prevId = $this->resolvePrevProcessByFlow($db, (int)$p['id'], $processIdAS);
            $p['prev_process_id'] = (int)($prevId ?? 0);
            $p['stock_prev']      = $prevId ? $this->getPrevProcessStock($db, $date, (int)$p['id'], $prevId) : 0;
        }
        unset($p);

        return $this->response->setJSON($products);
    }

    /* ============================================
     * STORE (alur sama Machining biasa)
     * ============================================ */
    public function store()
    {
        $db    = db_connect();
        $date  = trim((string)$this->request->getPost('date'));
        $items = $this->request->getPost('items');

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $role  = session()->get('role') ?? '';
        $today = date('Y-m-d');
        if ($role !== 'ADMIN' && $date <= $today) {
            return redirect()->back()->with('error', 'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh membuat schedule mulai besok.');
        }

        $processIdAS = $this->getProcessIdAssyShaft($db);
        $hasCtAS     = $db->fieldExists('cycle_time_assy_shaft', 'products');
        $now         = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                // ✅ sama seperti machining biasa: plan diambil dari items[*][plan]
                $planInput = (int)($row['plan'] ?? 0);
                if ($planInput < 0) $planInput = 0;
                if ($planInput > 1200) $planInput = 1200;

                if (!$this->validateProductHasFlow($db, $productId, $processIdAS)) {
                    throw new \Exception("Product ID {$productId} tidak punya flow Assy Shaft aktif.");
                }

                // header schedule
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Shaft',
                    ])
                    ->get()
                    ->getRowArray();

                if (!$schedule) {
                    $insertHeader = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Shaft',
                        'is_completed'  => 0,
                        'created_at'    => $now
                    ];
                    if ($db->fieldExists('process_id', 'daily_schedules')) $insertHeader['process_id'] = $processIdAS;
                    if ($db->fieldExists('updated_at', 'daily_schedules')) $insertHeader['updated_at'] = $now;

                    $db->table('daily_schedules')->insert($insertHeader);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];
                }

                // item lama (delta)
                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'machine_id'        => $machineId
                    ])
                    ->get()
                    ->getRowArray();

                $oldProductId = $existItem ? (int)($existItem['product_id'] ?? 0) : 0;
                $oldPlan      = $existItem ? (int)($existItem['target_per_shift'] ?? 0) : 0;

                $prevProcessIdNew = $this->resolvePrevProcessByFlow($db, $productId, $processIdAS);

                // release jika product diganti
                if ($existItem && $oldProductId > 0 && $oldProductId !== $productId) {
                    $prevOld = $this->resolvePrevProcessByFlow($db, $oldProductId, $processIdAS);

                    if ($prevOld && $oldPlan > 0) {
                        $this->applyPrevReserveToNext($db, $date, $oldProductId, $prevOld, -$oldPlan);
                        $this->upsertIncomingWipAssyShaft($db, $date, $oldProductId, $prevOld, $processIdAS, (int)$existItem['id'], -$oldPlan);
                    }

                    $oldPlan = 0;
                }

                // CT Assy Shaft (fallback ke cycle_time)
                $product = $db->table('products')
                    ->select($hasCtAS ? 'cycle_time_assy_shaft as ct, cavity, efficiency_rate' : 'cycle_time as ct, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();

                if (!$product) throw new \Exception("Product ID {$productId} tidak ditemukan.");

                $cycle  = (int)($product['ct'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                if ($cycle <= 0 || $cavity <= 0) {
                    throw new \Exception("Cycle time Assy Shaft / cavity belum valid untuk Product ID {$productId}.");
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
                    'is_selected'       => ($planInput > 0) ? 1 : 0
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')->where('id', (int)$existItem['id'])->update($dataItem);
                    $itemId = (int)$existItem['id'];
                } else {
                    if ($planInput <= 0) continue;
                    $db->table('daily_schedule_items')->insert($dataItem);
                    $itemId = (int)$db->insertID();
                }

                // validasi & apply delta (sama persis machining biasa)
                if ($prevProcessIdNew) {
                    $delta = $planInput - $oldPlan;

                    if ($delta > 0) {
                        $stockPrev = $this->getPrevProcessStock($db, $date, $productId, $prevProcessIdNew);

                        if ($stockPrev <= 0) {
                            throw new \Exception("Stock kosong pada proses sebelumnya (prev) untuk Product ID {$productId}. Tidak bisa scheduling.");
                        }
                        if ($delta > $stockPrev) {
                            throw new \Exception("Scheduling tambahan ({$delta}) tidak boleh melebihi stock tersedia ({$stockPrev}) untuk Product ID {$productId}.");
                        }
                    }

                    if ($delta !== 0) {
                        $this->applyPrevReserveToNext($db, $date, $productId, $prevProcessIdNew, $delta);
                        $this->upsertIncomingWipAssyShaft($db, $date, $productId, $prevProcessIdNew, $processIdAS, $itemId, $delta);
                    }
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Schedule Assy Shaft tersimpan. Stock prev berkurang + transfer tercatat + qty_in Assy Shaft bertambah (stock Assy Shaft tetap 0).'
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * INCOMING WIP (FOR MODAL)
     * ✅ Sesuaikan agar avail pakai qty_in (bukan stock)
     * ===================================================== */
    public function incomingWip()
    {
        $db   = db_connect();
        $date = trim((string)($this->request->getGet('date') ?? ''));

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }

        try {
            if (!$db->tableExists('production_wip')) {
                return $this->response->setJSON(['status' => false, 'message' => 'Tabel production_wip tidak ditemukan']);
            }

            $wipDateCol    = $this->detectWipDateColumn($db);
            $processIdAS   = $this->getProcessIdAssyShaft($db);

            $hasQtyIn = $db->fieldExists('qty_in', 'production_wip');
            $hasQty   = $db->fieldExists('qty', 'production_wip');

            if (!$hasQtyIn && !$hasQty) {
                return $this->response->setJSON(['status' => false, 'message' => 'Kolom qty_in/qty tidak ada di production_wip']);
            }

            // ✅ incoming availability harus qty_in (machining-style)
            $availExpr = $hasQtyIn ? 'pw.qty_in' : 'pw.qty';

            $rows = $db->table('production_wip pw')
                ->select("
                    pw.id AS wip_id,
                    pw.product_id,
                    pw.from_process_id,
                    p.part_no,
                    p.part_name,
                    {$availExpr} AS avail
                ")
                ->join('products p', 'p.id = pw.product_id', 'left')
                ->where("pw.{$wipDateCol}", $date)
                ->where('pw.to_process_id', (int)$processIdAS)
                ->where('pw.status', 'WAITING')
                ->where("{$availExpr} >", 0)
                ->orderBy('p.part_no', 'ASC')
                ->get()
                ->getResultArray();

            return $this->response->setJSON([
                'status' => true,
                'data'   => array_map(function ($r) {
                    return [
                        'wip_id'          => (int)$r['wip_id'],
                        'product_id'      => (int)$r['product_id'],
                        'from_process_id' => (int)($r['from_process_id'] ?? 0),
                        'part_no'         => (string)($r['part_no'] ?? ''),
                        'part_name'       => (string)($r['part_name'] ?? ''),
                        'avail'           => (int)($r['avail'] ?? 0),
                    ];
                }, $rows)
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /* =====================================================
     * ASSIGN INCOMING WIP (FOR MODAL)
     * ✅ Sesuaikan avail pakai qty_in
     * Catatan: ini hanya “alokasi” incoming ke schedule item,
     * tidak mengubah stock prev (karena incoming sudah berasal dari prev).
     * ===================================================== */
    public function assignIncomingWip()
    {
        $db = db_connect();

        $date      = trim((string)($this->request->getPost('date') ?? ''));
        $shiftId   = (int)($this->request->getPost('shift_id') ?? 0);
        $machineId = (int)($this->request->getPost('machine_id') ?? 0);
        $productId = (int)($this->request->getPost('product_id') ?? 0);
        $qtyAssign = (int)($this->request->getPost('qty') ?? 0);
        $wipId     = (int)($this->request->getPost('wip_id') ?? 0);

        if ($date === '' || $shiftId <= 0 || $machineId <= 0 || $productId <= 0 || $qtyAssign <= 0 || $wipId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Payload tidak lengkap']);
        }

        $role  = session()->get('role') ?? '';
        $today = date('Y-m-d');
        if ($role !== 'ADMIN' && $date <= $today) {
            return $this->response->setJSON(['status' => false, 'message' => 'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh mulai besok.']);
        }

        if (!$db->tableExists('production_wip')) {
            return $this->response->setJSON(['status' => false, 'message' => 'Tabel production_wip tidak ditemukan']);
        }

        $wipDateCol  = $this->detectWipDateColumn($db);
        $processIdAS = $this->getProcessIdAssyShaft($db);
        $hasQtyIn    = $db->fieldExists('qty_in', 'production_wip');
        $hasQty      = $db->fieldExists('qty', 'production_wip');
        $hasQtyOut   = $db->fieldExists('qty_out', 'production_wip');

        if (!$hasQtyIn && !$hasQty) {
            return $this->response->setJSON(['status' => false, 'message' => 'Kolom qty_in/qty tidak ada di production_wip']);
        }

        $wip = $db->table('production_wip')->where('id', $wipId)->get()->getRowArray();
        if (!$wip) return $this->response->setJSON(['status' => false, 'message' => 'WIP sumber tidak ditemukan']);

        if ((int)($wip['to_process_id'] ?? 0) !== (int)$processIdAS) {
            return $this->response->setJSON(['status' => false, 'message' => 'WIP ini bukan incoming Assy Shaft']);
        }
        if ((string)($wip[$wipDateCol] ?? '') !== $date) {
            return $this->response->setJSON(['status' => false, 'message' => 'Tanggal WIP tidak sama']);
        }
        if (strtoupper((string)($wip['status'] ?? '')) !== 'WAITING') {
            return $this->response->setJSON(['status' => false, 'message' => 'WIP bukan status WAITING']);
        }

        $fromProcessId = (int)($wip['from_process_id'] ?? 0);
        if ($fromProcessId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'from_process_id kosong']);
        }

        // ✅ avail = qty_in (machining-style)
        $avail = $hasQtyIn ? (int)($wip['qty_in'] ?? 0) : (int)($wip['qty'] ?? 0);

        if ($qtyAssign > $avail) {
            return $this->response->setJSON(['status' => false, 'message' => "Qty melebihi available ({$avail})"]);
        }

        $qtyAssign = min($qtyAssign, 1200);

        // pastikan product punya flow Assy Shaft
        if (!$this->validateProductHasFlow($db, $productId, $processIdAS)) {
            return $this->response->setJSON(['status' => false, 'message' => "Product {$productId} tidak punya flow Assy Shaft aktif"]);
        }

        // ambil CT untuk schedule item
        $hasCtAS = $db->fieldExists('cycle_time_assy_shaft', 'products');
        $product = $db->table('products')
            ->select($hasCtAS ? 'cycle_time_assy_shaft as ct, cavity, efficiency_rate' : 'cycle_time as ct, cavity, efficiency_rate')
            ->where('id', $productId)
            ->get()
            ->getRowArray();
        if (!$product) return $this->response->setJSON(['status' => false, 'message' => 'Product tidak ditemukan']);

        $cycle  = (int)($product['ct'] ?? 0);
        $cavity = (int)($product['cavity'] ?? 0);
        $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
        $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

        if ($cycle <= 0 || $cavity <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Cycle time / cavity belum valid']);
        }

        $targetPerHour = (int)floor((3600 / $cycle) * $cavity * $eff);
        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            // header schedule
            $schedule = $db->table('daily_schedules')
                ->where([
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Assy Shaft',
                ])
                ->get()
                ->getRowArray();

            if (!$schedule) {
                $insertHeader = [
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Assy Shaft',
                    'is_completed'  => 0,
                    'created_at'    => $now
                ];
                if ($db->fieldExists('process_id', 'daily_schedules')) $insertHeader['process_id'] = $processIdAS;
                if ($db->fieldExists('updated_at', 'daily_schedules')) $insertHeader['updated_at'] = $now;

                $db->table('daily_schedules')->insert($insertHeader);
                $scheduleId = (int)$db->insertID();
            } else {
                $scheduleId = (int)$schedule['id'];
            }

            // upsert item per machine
            $existItem = $db->table('daily_schedule_items')
                ->where([
                    'daily_schedule_id' => $scheduleId,
                    'machine_id'        => $machineId
                ])
                ->get()->getRowArray();

            $dataItem = [
                'daily_schedule_id' => $scheduleId,
                'shift_id'          => $shiftId,
                'machine_id'        => $machineId,
                'product_id'        => $productId,
                'cycle_time'        => $cycle,
                'cavity'            => $cavity,
                'target_per_hour'   => $targetPerHour,
                'target_per_shift'  => $qtyAssign,
                'is_selected'       => 1
            ];

            if ($existItem) {
                $db->table('daily_schedule_items')->where('id', (int)$existItem['id'])->update($dataItem);
                $itemId = (int)$existItem['id'];
            } else {
                $db->table('daily_schedule_items')->insert($dataItem);
                $itemId = (int)$db->insertID();
            }

            // kurangi qty_in pada WIP incoming (alokasi)
            $remaining = $avail - $qtyAssign;

            $wipUpd = [
                'status' => ($remaining <= 0) ? 'DONE' : 'WAITING',
            ];

            if ($hasQtyIn) $wipUpd['qty_in'] = $remaining;
            if ($hasQty)   $wipUpd['qty']    = $remaining;
            if ($hasQtyOut) $wipUpd['qty_out'] = (int)($wip['qty_out'] ?? 0) + $qtyAssign;

            if ($db->fieldExists('updated_at', 'production_wip')) $wipUpd['updated_at'] = $now;

            $db->table('production_wip')->where('id', $wipId)->update($wipUpd);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return $this->response->setJSON([
                'status'  => true,
                'message' => 'Incoming WIP berhasil di-assign',
                'data'    => [
                    'schedule_id' => $scheduleId,
                    'item_id'     => $itemId,
                    'wip_id'      => $wipId,
                    'assigned'    => $qtyAssign,
                    'remaining'   => $remaining,
                ]
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }
}
