<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftDailyScheduleController extends BaseController
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
     * PROCESS ID Assy Shaft (robust)
     * ===================================================== */
    private function getProcessIdAssyShaft($db): int
    {
        if ($db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')->select('id')
                ->where('process_code', 'AS')
                ->get()->getRowArray();
            if ($row && !empty($row['id'])) return (int)$row['id'];
        }

        $exact = ['Assy Shaft', 'ASSY SHAFT', 'AssyShaft', 'ASSYSHAFT', 'AS'];
        foreach ($exact as $name) {
            $row = $db->table('production_processes')->select('id')
                ->where('process_name', $name)
                ->get()->getRowArray();
            if ($row && !empty($row['id'])) return (int)$row['id'];
        }

        $row = $db->table('production_processes')->select('id')
            ->like('process_name', 'SHAFT')
            ->get()->getRowArray();
        if ($row && !empty($row['id'])) return (int)$row['id'];

        throw new \Exception('Process "Assy Shaft" belum ada / tidak terdeteksi di master production_processes');
    }

    /* =====================================================
     * SHIFT Minutes
     * ===================================================== */
    private function getTotalMinuteShift($db, int $shiftId, ?int $endSlotId = null): int
    {
        if (!$db->tableExists('shift_time_slots') || !$db->tableExists('time_slots')) {
            return 0;
        }

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.id, ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('sts.id', 'ASC')
            ->get()->getResultArray();

        $totalMinute = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400; // Lewat tengah malam
            
            $totalMinute += (int)(($end - $start) / 60);
            
            if ($endSlotId !== null && (int)$s['id'] === $endSlotId) {
                break; 
            }
        }

        return $totalMinute;
    }

    /* =====================================================
     * VALIDATE product punya flow AS aktif
     * ===================================================== */
    private function validateProductHasFlow($db, int $productId, int $processId): bool
    {
        if (!$db->tableExists('product_process_flows')) return false;

        $q = $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processId);

        if ($db->fieldExists('is_active', 'product_process_flows')) {
            $q->where('is_active', 1);
        }

        return $q->countAllResults() > 0;
    }

    /* =====================================================
     * resolve PREV process by flow
     * ===================================================== */
    private function resolvePrevProcessByFlow($db, int $productId, int $currentProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;

        $curQ = $db->table('product_process_flows')
            ->select('sequence')
            ->where('product_id', $productId)
            ->where('process_id', $currentProcessId);

        if ($db->fieldExists('is_active', 'product_process_flows')) {
            $curQ->where('is_active', 1);
        }

        $currentFlow = $curQ->orderBy('sequence', 'ASC')->get()->getRowArray();
        if (!$currentFlow) return null;

        $curSeq = (int)$currentFlow['sequence'];

        $prevQ = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('sequence <', $curSeq);

        if ($db->fieldExists('is_active', 'product_process_flows')) {
            $prevQ->where('is_active', 1);
        }

        $prevFlow = $prevQ->orderBy('sequence', 'DESC')->get()->getRowArray();
        return $prevFlow ? (int)$prevFlow['process_id'] : null;
    }

    /* =====================================================
     * Detect date col production_wip
     * ===================================================== */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
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
     * Ambil ROW stock terbaru untuk prev process
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
            ->where('pw.to_process_id', $prevProcessId)
            ->where("DATE(pw.$dateCol) <=", $date, false);

        $row = $builder
            ->orderBy("DATE(pw.$dateCol)", 'DESC', false)
            ->orderBy("pw.id", 'DESC')
            ->get()->getRowArray();

        if ($row) return $row;

        return $db->table('production_wip pw')
            ->select("pw.id, pw.$stockCol as stock_val, pw.transfer, pw.qty_in, pw.qty_out")
            ->where('pw.product_id', $productId)
            ->where('pw.to_process_id', $prevProcessId)
            ->orderBy("pw.id", 'DESC')
            ->get()->getRowArray();
    }

    private function getPrevProcessStock($db, string $date, int $productId, int $prevProcessId): int
    {
        $row = $this->getPrevProcessLatestRow($db, $date, $productId, $prevProcessId);
        return (int)($row['stock_val'] ?? 0);
    }

    /* =========================================================
     * Reserve/Release prev -> next
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
     * Incoming WIP untuk Assy Shaft saat schedule
     * ========================================================= */
    private function upsertIncomingWipForAssyShaft(
        $db, string $date, int $productId, int $fromPrevProcessId,
        int $toAssyShaftProcessId, int $scheduleItemId, int $deltaQty
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
            if ($hasQty) $upd['qty'] = $newQtyIn;
            if ($hasQtyOut) $upd['qty_out'] = (int)($exist['qty_out'] ?? 0);
            if ($hasStock) $upd['stock'] = 0;
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
        if ($hasQty) $ins['qty'] = $insQty;
        if ($hasQtyOut) $ins['qty_out'] = 0;
        if ($hasStock) $ins['stock'] = 0;
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

        $dailySchedules = $db->table('daily_schedules')
            ->where('schedule_date', $date)
            ->where('section', 'Assy Shaft')
            ->get()->getResultArray();
            
        $shiftEndSlots = [];
        foreach ($dailySchedules as $ds) {
            $shiftEndSlots[$ds['shift_id']] = $ds['end_time_slot_id'];
        }

        $shiftSlots = [];
        foreach ($shifts as &$shift) {
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id as time_slot_id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', (int)$shift['id'])
                ->orderBy('sts.id', 'ASC')
                ->get()->getResultArray();

            $totalMinute = 0;
            foreach ($slots as &$s) {
                $start = strtotime($s['time_start']);
                $end   = strtotime($s['time_end']);
                if ($end <= $start) $end += 86400;
                
                $mins = (int)(($end - $start) / 60);
                $s['minutes'] = $mins;
                $s['label']   = substr($s['time_start'], 0, 5) . ' - ' . substr($s['time_end'], 0, 5);
                $totalMinute += $mins;
            }
            $shift['total_minute'] = $totalMinute;
            $shiftSlots[$shift['id']] = $slots;
        }
        unset($shift);

        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.id schedule_id, ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Shaft')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id']][$e['machine_id']][] = $e;
        }

        $actualMap = [];
        if ($db->tableExists('machining_assy_shaft_hourly')) {
            $actuals = $db->table('machining_assy_shaft_hourly')
                ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
                ->where('production_date', $date)
                ->groupBy('shift_id, machine_id, product_id')
                ->get()->getResultArray();

            foreach ($actuals as $a) {
                $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
            }
        }

        return view('machining/assy_shaft_schedule/index', [
            'date'          => $date,
            'shifts'        => $shifts,
            'shiftSlots'    => $shiftSlots,
            'shiftEndSlots' => $shiftEndSlots,
            'machines'      => $machines,
            'planMap'       => $planMap,
            'actualMap'     => $actualMap
        ]);
    }

    /* =====================================================
     * AJAX Select2: product + target + stock_prev
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)($this->request->getGet('shift_id') ?? 0);
        $date    = (string)($this->request->getGet('date') ?? date('Y-m-d'));
        $term = trim((string)($this->request->getGet('term') ?? ''));
        $q    = trim((string)($this->request->getGet('q') ?? ''));
        $search = ($term !== '') ? $term : $q;

        if ($shiftId <= 0) {
            return $this->response->setJSON(['results' => []]);
        }

        try {
            $processIdAS = $this->getProcessIdAssyShaft($db);
            $totalMinute = $this->getTotalMinuteShift($db, $shiftId); 

            $hasCtMach = $db->fieldExists('cycle_time_machining', 'products');

            $builder = $db->table('product_process_flows ppf')
                ->select('p.id, p.part_no, p.part_name, p.cavity, p.efficiency_rate'
                    . ($hasCtMach ? ', p.cycle_time_machining' : ', p.cycle_time'))
                ->join('products p', 'p.id = ppf.product_id', 'inner')
                ->where('ppf.process_id', $processIdAS);

            if ($db->fieldExists('is_active', 'product_process_flows')) {
                $builder->where('ppf.is_active', 1);
            }
            if ($db->fieldExists('is_active', 'products')) {
                $builder->where('p.is_active', 1);
            }

            if ($search !== '') {
                $builder->groupStart()
                    ->like('p.part_no', $search)
                    ->orLike('p.part_name', $search)
                    ->groupEnd();
            }

            $products = $builder
                ->groupBy('p.id')
                ->orderBy('p.part_no', 'ASC')
                ->limit(50)
                ->get()->getResultArray();

            $results = [];
            foreach ($products as $p) {
                $pid    = (int)($p['id'] ?? 0);
                $cycle  = $hasCtMach ? (int)($p['cycle_time_machining'] ?? 0) : (int)($p['cycle_time'] ?? 0);
                $cavity = (int)($p['cavity'] ?? 0);
                $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                $targetShift = 0;
                $targetHour  = 0;

                if ($cycle > 0 && $cavity > 0) {
                    if ($totalMinute > 0) {
                        $targetShift = (int)floor((($totalMinute * 60) / $cycle) * $cavity * $eff);
                    }
                    $targetHour = (int)floor((3600 / $cycle) * $cavity * $eff);
                }

                $prevId = $this->resolvePrevProcessByFlow($db, $pid, $processIdAS);
                $stockPrev = $prevId ? $this->getPrevProcessStock($db, $date, $pid, (int)$prevId) : 0;

                $results[] = [
                    'id' => $pid,
                    'text' => trim((string)($p['part_no'] ?? '').' - '.(string)($p['part_name'] ?? '')),
                    'cycle_time_used'  => $cycle, 
                    'target_per_shift' => min($targetShift, 1200),
                    'target_per_hour'  => (int)$targetHour,
                    'prev_process_id'  => (int)($prevId ?? 0),
                    'stock_prev'       => (int)$stockPrev,
                ];
            }

            return $this->response->setJSON(['results' => $results]);

        } catch (\Throwable $e) {
            log_message('error', 'AssyShaft getProductAndTarget error: ' . $e->getMessage());
            return $this->response->setJSON(['results' => []]);
        }
    }

    /* =====================================================
     * STORE
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $date  = trim((string)($this->request->getPost('date') ?? ''));
        $items = $this->request->getPost('items');
        
        $shiftEndSlots = $this->request->getPost('shift_end_slots') ?? [];

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $deny = $this->guardScheduleDateByRoleRedirect($date);
        if ($deny) return $deny;

        $processIdAS   = $this->getProcessIdAssyShaft($db);
        $hasCtMach     = $db->fieldExists('cycle_time_machining', 'products');
        $hasEndSlotCol = $db->fieldExists('end_time_slot_id', 'daily_schedules');
        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            $activeSchedules = [];

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0) continue;

                $planInput = (int)($row['plan'] ?? 0);
                if ($planInput < 0) $planInput = 0;
                if ($planInput > 1200) $planInput = 1200;

                $endSlotId = !empty($shiftEndSlots[$shiftId]) ? (int)$shiftEndSlots[$shiftId] : null;

                // 1. HEADER (daily_schedules)
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Shaft',
                    ])->get()->getRowArray();

                if (!$schedule) {
                    $header = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Shaft',
                        'is_completed'  => 0,
                        'created_at'    => $now,
                    ];
                    if ($db->fieldExists('process_id', 'daily_schedules')) $header['process_id'] = $processIdAS;
                    if ($hasEndSlotCol) $header['end_time_slot_id'] = $endSlotId;
                    
                    $db->table('daily_schedules')->insert($header);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];
                    $upd = ['updated_at' => $now];
                    
                    if ($db->fieldExists('process_id', 'daily_schedules') && empty($schedule['process_id'])) {
                        $upd['process_id'] = $processIdAS;
                    }
                    if ($hasEndSlotCol) $upd['end_time_slot_id'] = $endSlotId;
                    
                    $db->table('daily_schedules')->where('id', $scheduleId)->update($upd);
                }

                if (!isset($activeSchedules[$scheduleId])) {
                    $activeSchedules[$scheduleId] = [];
                }

                if ($productId <= 0 || $planInput <= 0) continue;

                if (!$this->validateProductHasFlow($db, $productId, $processIdAS)) {
                    throw new \Exception("Product ID {$productId} tidak punya flow Assy Shaft aktif.");
                }

                // Cek Item
                $existItem = $db->table('daily_schedule_items')
                    ->where(['daily_schedule_id' => $scheduleId, 'machine_id' => $machineId, 'product_id' => $productId])
                    ->get()->getRowArray();

                $oldPlan = $existItem ? (int)($existItem['target_per_shift'] ?? 0) : 0;
                $prevProcessIdNew = $this->resolvePrevProcessByFlow($db, $productId, $processIdAS);

                if (!$existItem) {
                    $db->table('daily_schedule_items')->insert([
                        'daily_schedule_id' => $scheduleId,
                        'shift_id'          => $shiftId,
                        'machine_id'        => $machineId,
                        'product_id'        => $productId,
                        'cycle_time'        => 0, // diisi nanti
                        'cavity'            => 0,
                        'target_per_hour'   => 0,
                        'target_per_shift'  => 0,
                        'is_selected'       => 0,
                    ]);
                    $itemId = (int)$db->insertID();
                } else {
                    $itemId = (int)$existItem['id'];
                }

                // Kalkulasi Selisih Reserve WIP
                if ($prevProcessIdNew) {
                    $delta = $planInput - $oldPlan;
                    if ($delta > 0) {
                        $stockPrev = $this->getPrevProcessStock($db, $date, $productId, $prevProcessIdNew);
                        if ($stockPrev <= 0) throw new \Exception("Stock kosong pada proses sebelumnya untuk Product ID {$productId}.");
                        if ($delta > $stockPrev) throw new \Exception("Scheduling tambahan ({$delta}) > stock ({$stockPrev}) untuk Product ID {$productId}.");
                    }
                    if ($delta !== 0) {
                        $this->applyPrevReserveToNext($db, $date, $productId, $prevProcessIdNew, $delta);
                        $this->upsertIncomingWipForAssyShaft($db, $date, $productId, $prevProcessIdNew, $processIdAS, $itemId, $delta);
                    }
                }

                // Update detail item
                $product = $db->table('products')
                    ->select('cavity, efficiency_rate' . ($hasCtMach ? ', cycle_time_machining' : ', cycle_time'))
                    ->where('id', $productId)->get()->getRowArray();
                
                $cycle  = $hasCtMach ? (int)($product['cycle_time_machining'] ?? 0) : (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;
                $targetPerHour = (int)floor((3600 / $cycle) * $cavity * $eff);

                $activeSchedules[$scheduleId][] = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $planInput,
                    'is_selected'       => 1,
                ];
            }

            // Replace jadwal item secara masal di tiap shift
            foreach ($activeSchedules as $schId => $itemsToInsert) {
                $existingRows = $db->table('daily_schedule_items')->where('daily_schedule_id', $schId)->get()->getResultArray();
                $existingMap = [];
                foreach($existingRows as $er) {
                    $existingMap[$er['machine_id'].'_'.$er['product_id']] = $er['id'];
                }

                $keysToKeep = [];
                foreach ($itemsToInsert as $it) {
                    $k = $it['machine_id'].'_'.$it['product_id'];
                    $keysToKeep[] = $k;
                    if(isset($existingMap[$k])) {
                        $db->table('daily_schedule_items')->where('id', $existingMap[$k])->update($it);
                    } else {
                        $db->table('daily_schedule_items')->insert($it);
                    }
                }
                
                // Jika ada data di DB yang sudah tidak dikirim form lagi, kembalikan WIP-nya
                foreach($existingRows as $er) {
                    $k = $er['machine_id'].'_'.$er['product_id'];
                    if(!in_array($k, $keysToKeep)) {
                         $prevOld = $this->resolvePrevProcessByFlow($db, $er['product_id'], $processIdAS);
                         if ($prevOld && $er['target_per_shift'] > 0) {
                             $this->applyPrevReserveToNext($db, $date, $er['product_id'], $prevOld, -$er['target_per_shift']);
                             $this->upsertIncomingWipForAssyShaft($db, $date, $er['product_id'], $prevOld, $processIdAS, (int)$er['id'], -$er['target_per_shift']);
                         }
                         $db->table('daily_schedule_items')->where('id', $er['id'])->delete();
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
     * INVENTORY STOCK (Assy Shaft Only)
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

        $processIdAS = $this->getProcessIdAssyShaft($db);

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
            $query = $db->table($tbl . ' w')
                ->select('w.product_id, p.part_no, p.part_name, w.'.$colStock.' as current_stock')
                ->join('products p', 'p.id = w.product_id', 'inner')
                ->where("w.$wipDateCol <=", $date)
                ->where('w.to_process_id', $processIdAS)
                ->where('w.id IN (
                    SELECT MAX(id) 
                    FROM production_wip 
                    WHERE '.$wipDateCol.' <= "'.$date.'" 
                    AND to_process_id = '.$processIdAS.'
                    GROUP BY product_id
                )', null, false)
                ->get()
                ->getResultArray();

            foreach ($query as $row) {
                $qty = (int)$row['current_stock'];
                if($qty > 0) {
                    $productData[] = [
                        'part_no'     => $row['part_no'],
                        'part_name'   => $row['part_name'],
                        'total_stock' => $qty,
                    ];
                }
            }
        }

        usort($productData, function($a, $b) {
            return strcmp($a['part_no'], $b['part_no']);
        });

        return view('machining/assy_shaft_schedule/inventory', [
            'date'        => $date,
            'titleDate'   => $titleDate,
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}