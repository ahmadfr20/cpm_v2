<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    /* ============================================
     * Helper: ambil process_id Machining
     * ============================================ */
    private function getProcessIdMachining($db): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Machining')
            ->get()
            ->getRowArray();

        if (!$row) {
            throw new \Exception('Process "Machining" belum ada di master production_processes');
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
     * Helper: validasi product punya flow Machining aktif
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
     * - Prioritas: DATE(dateCol) <= $date
     * - Fallback: row terbaru tanpa filter tanggal (schedule jauh)
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
     * RESERVE/RELEASE dari prev process untuk dikirim ke next
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

        $newStock = $curStock - $deltaQty;     // reserve (delta+) => turun
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
     * Incoming Machining saat schedule:
     * qty_in bertambah, stock tetap 0
     * ========================================================= */
    private function upsertIncomingWipMachining(
        $db,
        string $date,
        int $productId,
        int $fromPrevProcessId,
        int $toMachiningProcessId,
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
            'to_process_id'   => $toMachiningProcessId,
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

            // stock tetap 0 (belum diproses)
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
            ->where('ds.section', 'Machining')
            ->get()
            ->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        $actualMap = [];
        if ($db->tableExists('machining_hourly')) {
            $actuals = $db->table('machining_hourly')
                ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
                ->where('production_date', $date)
                ->groupBy('shift_id, machine_id, product_id')
                ->get()
                ->getResultArray();

            foreach ($actuals as $a) {
                $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
            }
        }

        return view('machining/schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /* ============================================
     * AJAX: PRODUCT + TARGET + STOCK PREV
     * Endpoint untuk select2 (productUrl)
     * ============================================ */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');
        $date    = (string)($this->request->getGet('date') ?? date('Y-m-d'));

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdMC = $this->getProcessIdMachining($db);

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        if ($totalSecond <= 0) return $this->response->setJSON([]);

        $hasCtMach = $db->fieldExists('cycle_time_machining', 'products');

        $products = $db->table('product_process_flows ppf')
            ->select('p.id, p.part_no, p.part_name, p.cavity, p.efficiency_rate'
                . ($hasCtMach ? ', p.cycle_time_machining' : ', p.cycle_time'))
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdMC)
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($products as &$p) {
            $cycle  = $hasCtMach ? (int)($p['cycle_time_machining'] ?? 0) : (int)($p['cycle_time'] ?? 0);
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
            $prevId = $this->resolvePrevProcessByFlow($db, (int)$p['id'], $processIdMC);
            $p['prev_process_id'] = (int)($prevId ?? 0);
            $p['stock_prev']      = $prevId ? $this->getPrevProcessStock($db, $date, (int)$p['id'], $prevId) : 0;
        }
        unset($p);

        return $this->response->setJSON($products);
    }

    /* ============================================
     * STORE
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

        $processIdMC = $this->getProcessIdMachining($db);
        $hasCtMach   = $db->fieldExists('cycle_time_machining', 'products');
        $now         = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                $planInput = (int)($row['plan'] ?? 0);
                if ($planInput < 0) $planInput = 0;
                if ($planInput > 1200) $planInput = 1200;

                if (!$this->validateProductHasFlow($db, $productId, $processIdMC)) {
                    throw new \Exception("Product ID {$productId} tidak punya flow Machining aktif.");
                }

                // header schedule
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                    ])
                    ->get()
                    ->getRowArray();

                if (!$schedule) {
                    $insertHeader = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                        'is_completed'  => 0,
                        'created_at'    => $now
                    ];
                    if ($db->fieldExists('process_id', 'daily_schedules')) $insertHeader['process_id'] = $processIdMC;
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

                $prevProcessIdNew = $this->resolvePrevProcessByFlow($db, $productId, $processIdMC);

                // release jika product diganti
                if ($existItem && $oldProductId > 0 && $oldProductId !== $productId) {
                    $prevOld = $this->resolvePrevProcessByFlow($db, $oldProductId, $processIdMC);

                    if ($prevOld && $oldPlan > 0) {
                        $this->applyPrevReserveToNext($db, $date, $oldProductId, $prevOld, -$oldPlan);
                        $this->upsertIncomingWipMachining($db, $date, $oldProductId, $prevOld, $processIdMC, (int)$existItem['id'], -$oldPlan);
                    }

                    $oldPlan = 0;
                }

                // CT machining
                $product = $db->table('products')
                    ->select($hasCtMach ? 'cycle_time_machining as ct, cavity, efficiency_rate' : 'cycle_time as ct, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();

                if (!$product) throw new \Exception("Product ID {$productId} tidak ditemukan.");

                $cycle  = (int)($product['ct'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                if ($cycle <= 0 || $cavity <= 0) {
                    throw new \Exception("Cycle time machining / cavity belum valid untuk Product ID {$productId}.");
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

                // validasi & apply delta
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
                        $this->upsertIncomingWipMachining($db, $date, $productId, $prevProcessIdNew, $processIdMC, $itemId, $delta);
                    }
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Schedule tersimpan. Stock prev berkurang + transfer tercatat + qty_in Machining bertambah (stock Machining tetap 0).'
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * INVENTORY STOCK (Machining Only)
     * ===================================================== */
    public function inventory()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        
        $role = (string)(session()->get('role') ?? '');
        $isAdmin = (strtoupper($role) === 'ADMIN');
        if (!$isAdmin) $date = date('Y-m-d');

        // Format tanggal
        $ts = strtotime($date);
        $bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
        $m = (int)date('n', $ts);
        $titleDate = date('d', $ts) . ' ' . ($bulan[$m] ?? date('M',$ts)) . ' ' . date('Y', $ts);

        $processIdMC = $this->getProcessIdMachining($db);

        $tbl = 'production_wip';
        $wipDateCol = $db->fieldExists('wip_date', $tbl) ? 'wip_date' : 
                     ($db->fieldExists('schedule_date', $tbl) ? 'schedule_date' : 'production_date');
        
        $colStock = 'stock';
        foreach (['stock', 'stock_qty', 'qty_stock'] as $col) {
            if ($db->fieldExists($col, $tbl)) {
                $colStock = $col; break;
            }
        }

        $productData = [];

        if ($db->tableExists($tbl)) {
            // Ambil max stock terakhir khusus untuk process Machining
            $query = $db->table($tbl . ' w')
                ->select('w.product_id, p.part_no, p.part_name, w.'.$colStock.' as current_stock')
                ->join('products p', 'p.id = w.product_id', 'inner')
                ->where("w.$wipDateCol <=", $date)
                ->where('w.to_process_id', $processIdMC)
                // Subquery row terakhir (terbaru) per product
                ->where('w.id IN (
                    SELECT MAX(id) 
                    FROM production_wip 
                    WHERE '.$wipDateCol.' <= "'.$date.'" 
                    AND to_process_id = '.$processIdMC.'
                    GROUP BY product_id
                )', null, false)
                ->get()
                ->getResultArray();

            foreach ($query as $row) {
                $qty = (int)$row['current_stock'];
                
                // Tampilkan hanya jika stok lebih dari 0
                if($qty > 0) {
                    $productData[] = [
                        'part_no'     => $row['part_no'],
                        'part_name'   => $row['part_name'],
                        'total_stock' => $qty,
                    ];
                }
            }
        }

        // Urutkan berdasarkan Part No
        usort($productData, function($a, $b) {
            return strcmp($a['part_no'], $b['part_no']);
        });

        return view('machining/schedule/inventory', [
            'date'        => $date,
            'titleDate'   => $titleDate,
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}