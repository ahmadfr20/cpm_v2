<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    /* =========================
     * INDEX
     * ========================= */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date');
        if (empty($date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }

        // Auto-add kolom active_slot_ids ke die_casting_production jika belum ada
        if (!$db->fieldExists('active_slot_ids', 'die_casting_production')) {
            try { $db->query("ALTER TABLE die_casting_production ADD COLUMN active_slot_ids TEXT NULL AFTER end_time_slot_id"); } catch (\Throwable $e) {}
        }

        $processIdDC = $this->getProcessIdDieCasting($db);

        // SHIFT Die Casting (DC)
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->where('day_group', $this->getDayGroup($date))
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        $shiftSlots = [];
        foreach ($shifts as &$shift) {
            // Menggunakan sts.id ASC agar urutan slot shift malam tidak berantakan
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id as time_slot_id, ts.time_start, ts.time_end, sts.is_break')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', (int)$shift['id'])
                ->orderBy('sts.id', 'ASC') 
                ->get()->getResultArray();

            $totalMinute = 0;
            foreach ($slots as &$s) {
                $start = strtotime($s['time_start']);
                $end   = strtotime($s['time_end']);
                if ($end <= $start) $end += 86400; // Lewat tengah malam
                $mins = (int)(($end - $start) / 60);
                
                if (!empty($s['is_break'])) {
                    $s['minutes'] = 0;
                } else {
                    $s['minutes'] = $mins;
                    $totalMinute += $mins;
                }
                
                $s['label']   = substr($s['time_start'], 0, 5) . ' - ' . substr($s['time_end'], 0, 5);
            }
            $shift['total_minute'] = $totalMinute;
            $shiftSlots[$shift['id']] = $slots;
        }
        unset($shift);

        // Mesin Die Casting
        $machines = $db->table('machines')
            ->where('production_line', 'Die Casting')
            ->orderBy('line_position')
            ->get()
            ->getResultArray();

        // Existing schedule (die_casting_production)
        $existing = $db->table('die_casting_production')
            ->where('production_date', $date)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($existing as $e) {
            $map[(int)$e['shift_id']][(int)$e['machine_id']][] = $e;
        }

        // Active Slot IDs per machine per shift: dari die_casting_production
        // Format: $activeSlotMap[shift_id][machine_id][index] = [slot_id, ...] or null (semua aktif)
        $activeSlotMap = [];
        $slotCustomTimesMap = [];
        $mapCount = [];
        $hasSlotCustomCol = $db->fieldExists('slot_custom_times', 'die_casting_production');
        foreach ($existing as $e) {
            $sId = (int)$e['shift_id'];
            $mId = (int)$e['machine_id'];
            if (!isset($mapCount[$sId][$mId])) {
                $mapCount[$sId][$mId] = 0;
            }
            $idx = $mapCount[$sId][$mId];

            $raw = $e['active_slot_ids'] ?? null;
            $activeSlotMap[$sId][$mId][$idx] = $raw ? array_map('intval', explode(',', $raw)) : null;
            if ($hasSlotCustomCol) {
                $slotCustomTimesMap[$sId][$mId][$idx] = $e['slot_custom_times'] ?? null;
            }
            
            $mapCount[$sId][$mId]++;
        }

        // Ambil data Dandori yang sudah dicentang
        $dandoriRecords = [];
        if ($db->tableExists('die_casting_dandori')) {
            $dandoriRecords = $db->table('die_casting_dandori')
                ->where('dandori_date', $date)
                ->get()->getResultArray();
        }
        
        $dandoriMap = [];
        foreach ($dandoriRecords as $d) {
            $sId = (int)$d['shift_id'];
            $mId = (int)$d['machine_id'];
            $pId = (int)$d['product_id'];
            
            if (!isset($dandoriMap[$sId][$mId][$pId])) {
                $dandoriMap[$sId][$mId][$pId] = [
                    'time_slot_ids' => [],
                    'slot_minutes'  => [], // [ ['slot_id'=>X, 'minute'=>Y] ]
                ];
            }
            if (!empty($d['time_slot_id'])) {
                $slotId = (int)$d['time_slot_id'];
                $dandoriMap[$sId][$mId][$pId]['time_slot_ids'][] = $slotId;
                $dandoriMap[$sId][$mId][$pId]['slot_minutes'][] = [
                    'slot_id' => $slotId,
                    'minute'  => (int)($d['dandori_minute'] ?? 0),
                ];
            }
        }

        return view('die_casting/daily_schedule/index', [
            'date'               => $date,
            'shifts'             => $shifts,
            'shiftSlots'         => $shiftSlots,
            'machines'           => $machines,
            'map'                => $map,
            'dandoriMap'         => $dandoriMap,
            'activeSlotMap'      => $activeSlotMap,
            'slotCustomTimesMap' => $slotCustomTimesMap,
        ]);
    }

    /* =========================
     * Process ID Die Casting
     * ========================= */
    private function getProcessIdDieCasting($db): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Die Casting')
            ->get()
            ->getRowArray();

        if (!$row) {
            throw new \Exception('Process "Die Casting" belum ada di master production_processes');
        }
        return (int)$row['id'];
    }

    /* =========================
     * Helper total menit shift (Dihentikan pada endSlotId tertentu)
     * ========================= */
    private function getTotalMinuteShift($db, int $shiftId, ?int $endSlotId = null): int
    {
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.id, ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('sts.id', 'ASC')
            ->get()
            ->getResultArray();

        $totalMinute = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400;
            $totalMinute += (int)(($end - $start) / 60);

            if ($endSlotId !== null && (int)$s['id'] === $endSlotId) {
                break; // Hentikan perhitungan karena ini adalah slot terakhir shift
            }
        }
        return (int)$totalMinute;
    }

    private function validateProductHasFlowDC($db, int $productId, int $processIdDC): bool
    {
        return $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processIdDC)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /**
     * Calculate total active minutes from slot selections and custom times.
     * @param string|null $activeSlotIds  Comma-separated slot IDs (e.g. "5,6,7")
     * @param string|null $slotCustomTimes Comma-separated slot_id:minutes (e.g. "5:45,6:60")
     */
    private function calcActiveMinutes($db, int $shiftId, ?string $activeSlotIds, ?string $slotCustomTimes): int
    {
        if (!$activeSlotIds) {
            return $this->getTotalMinuteShift($db, $shiftId);
        }

        $slotIds = array_map('intval', explode(',', $activeSlotIds));
        $customMap = [];
        if ($slotCustomTimes) {
            foreach (explode(',', $slotCustomTimes) as $entry) {
                $parts = explode(':', $entry, 2);
                if (count($parts) === 2) $customMap[(int)$parts[0]] = (int)$parts[1];
            }
        }

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.id, ts.time_start, ts.time_end, sts.is_break')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('sts.id', 'ASC')
            ->get()->getResultArray();

        $totalMinute = 0;
        foreach ($slots as $sl) {
            if (!in_array((int)$sl['id'], $slotIds)) continue;
            if (!empty($sl['is_break'])) continue;
            if (isset($customMap[(int)$sl['id']])) {
                $totalMinute += $customMap[(int)$sl['id']];
            } else {
                $s = strtotime($sl['time_start']); $e = strtotime($sl['time_end']);
                if ($e <= $s) $e += 86400;
                $totalMinute += (int)(($e - $s) / 60);
            }
        }
        return $totalMinute;
    }

    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdDC = $this->getProcessIdDieCasting($db);
        $totalMinute = $this->getTotalMinuteShift($db, $shiftId); // Disini fallback full menit

        $products = $db->table('product_process_flows ppf')
            ->select('
                p.id,
                p.part_no,
                p.part_name,
                p.part_prod,
                p.weight_ascas,
                p.weight_runner,
                p.cycle_time,
                p.cavity,
                p.efficiency_rate
            ')
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdDC)
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($products as &$p) {
            $cycle  = (int)($p['cycle_time'] ?? 0);
            $cavity = (int)($p['cavity'] ?? 0);

            $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            if ($cycle > 0 && $cavity > 0 && $totalMinute > 0) {
                $target = floor((($totalMinute * 60) / $cycle) * $cavity * $eff);
                $p['target'] = min((int)$target, 1200);
            } else {
                $p['target'] = 0;
            }
        }
        unset($p);

        return $this->response->setJSON($products);
    }

    private function getActiveFlowProcessIds($db, int $productId): array
    {
        if (!$db->tableExists('product_process_flows')) return [];
        $rows = $db->table('product_process_flows')
            ->select('process_id')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();
        return array_column($rows, 'process_id');
    }

    private function getNextProcessIdByFlow($db, int $productId, int $currentProcessId): ?int
    {
        $seq = $this->getActiveFlowProcessIds($db, $productId);
        if (!$seq) return null;
        $idx = array_search($currentProcessId, $seq, true);
        if ($idx === false) return $seq[1] ?? null;
        return $seq[$idx + 1] ?? null;
    }

    private function upsertProductionWip(
        $db, string $date, int $productId, int $fromProcessId, int $toProcessId,
        int $qty, string $status, string $sourceTable, int $sourceId,
        ?int $qtyIn = null, ?int $qtyOut = null, ?int $stock = null
    ): int {
        if (!$db->tableExists('production_wip')) return 0;
        $where = [
            'production_date' => $date, 'product_id' => $productId,
            'from_process_id' => $fromProcessId, 'to_process_id' => $toProcessId,
            'source_table' => $sourceTable, 'source_id' => $sourceId,
        ];
        $exist = $db->table('production_wip')->where($where)->get()->getRowArray();
        $payload = $where + ['qty' => $qty, 'status' => $status];
        if ($qtyIn !== null && $db->fieldExists('qty_in', 'production_wip')) $payload['qty_in'] = $qtyIn;
        if ($qtyOut !== null && $db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = $qtyOut;
        if ($stock !== null && $db->fieldExists('stock', 'production_wip')) $payload['stock'] = $stock;
        $now = date('Y-m-d H:i:s');
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            if (($exist['status'] ?? '') === 'DONE' && $status !== 'DONE') return (int)$exist['id'];
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
            return (int)$exist['id'];
        }
        if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
        $db->table('production_wip')->insert($payload);
        return (int)$db->insertID();
    }

    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section, ?int $endSlotId = null): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;
        $where = ['schedule_date' => $date, 'process_id' => $processId, 'shift_id' => $shiftId, 'section' => $section];
        $exist = $db->table('daily_schedules')->where($where)->get()->getRowArray();
        $now   = date('Y-m-d H:i:s');

        $hasEndSlotCol = $db->fieldExists('end_time_slot_id', 'daily_schedules');

        if ($exist) {
            $update = ['is_completed' => 0];
            if ($hasEndSlotCol) $update['end_time_slot_id'] = $endSlotId;
            if ($db->fieldExists('updated_at', 'daily_schedules')) $update['updated_at'] = $now;
            
            $db->table('daily_schedules')->where('id', (int)$exist['id'])->update($update);
            return (int)$exist['id'];
        }

        $payload = $where + ['is_completed' => 0];
        if ($db->fieldExists('created_at', 'daily_schedules')) $payload['created_at'] = $now;
        if ($db->fieldExists('updated_at', 'daily_schedules')) $payload['updated_at'] = $now;
        
        $db->table('daily_schedules')->insert($payload);
        return (int)$db->insertID();
    }

    private function rebuildDailyScheduleItems($db, int $dailyScheduleId, array $itemsToInsert): void
    {
        if ($dailyScheduleId <= 0 || !$db->tableExists('daily_schedule_items')) return;
        $db->table('daily_schedule_items')->where('daily_schedule_id', $dailyScheduleId)->delete();
        foreach ($itemsToInsert as $it) {
            $db->table('daily_schedule_items')->insert($it);
        }
    }

    /* =====================================================
     * STORE SCHEDULE (PLAN)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        // Support JSON-consolidated items to bypass max_input_vars limit
        $itemsJson = $this->request->getPost('items_json');
        if ($itemsJson) {
            $items = json_decode($itemsJson, true);
        } else {
            $items = $this->request->getPost('items');
        }

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Tidak ada data');
        }

        $role  = session()->get('role') ?? '';
        $today = date('Y-m-d'); 

        $firstRow = reset($items);
        $formDate = isset($firstRow['date']) ? trim((string)$firstRow['date']) : '';

        if ($formDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formDate)) {
            return redirect()->back()->with('error', 'Tanggal schedule tidak valid.');
        }

        if ($role !== 'ADMIN' && $formDate <= $today) {
            return redirect()->back()->with('error', 'Hanya ADMIN yang boleh membuat schedule untuk hari ini.');
        }

        $now = date('Y-m-d H:i:s');

        // Auto-add slot_custom_times column
        if (!$db->fieldExists('slot_custom_times', 'die_casting_production')) {
            try { $db->query("ALTER TABLE die_casting_production ADD COLUMN slot_custom_times TEXT NULL AFTER active_slot_ids"); } catch (\Throwable $e) {}
        }

        $db->transBegin();
        try {
            $processIdDC = $this->getProcessIdDieCasting($db);
            $dailyGroup = [];

            if ($db->tableExists('die_casting_dandori')) {
                $db->table('die_casting_dandori')->where('dandori_date', $formDate)->delete();
            }

            foreach ($items as $uniqueKey => $row) {
                if (empty($row['date']) || empty($row['shift_id']) || empty($row['machine_id'])) continue;

                $date      = trim((string)$row['date']);
                $shiftId   = (int)$row['shift_id'];
                $machineId = (int)$row['machine_id'];

                if ($date !== $formDate) continue;

                $productId = (int)($row['product_id'] ?? 0);
                $qtyP      = (int)($row['qty_p'] ?? 0);
                $statusRow = (string)($row['status'] ?? 'Normal');
                
                $isDandori = isset($row['is_dandori']) && $row['is_dandori'] == 1;

                if ($productId <= 0 || $qtyP <= 0) continue;

                if (!$this->validateProductHasFlowDC($db, $productId, $processIdDC)) continue;

                $product = $db->table('products')->select('id, part_name, cycle_time, cavity')->where('id', $productId)->get()->getRowArray();
                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                $partLabel = (($product['part_name'] ?? '') ?: '-') . ' #1';

                // Handle Dandori
                if ($isDandori && $db->tableExists('die_casting_dandori')) {
                    $slotData = $row['slot_data'] ?? [];
                    $hasAnySlot = false;
                    foreach ($slotData as $tsId => $slotInfo) {
                        if (empty($slotInfo['selected'])) continue;
                        $db->table('die_casting_dandori')->insert([
                            'dandori_date' => $date, 'shift_id' => $shiftId, 'machine_id' => $machineId,
                            'product_id' => $productId, 'time_slot_id' => (int)$tsId,
                            'dandori_minute' => (int)($slotInfo['minute'] ?? 0),
                            'activity' => 'Dandori', 'created_at' => $now
                        ]);
                        $hasAnySlot = true;
                    }
                    if (!$hasAnySlot) {
                        $db->table('die_casting_dandori')->insert([
                            'dandori_date' => $date, 'shift_id' => $shiftId, 'machine_id' => $machineId,
                            'product_id' => $productId, 'time_slot_id' => null, 'dandori_minute' => 0,
                            'activity' => 'Dandori', 'created_at' => $now
                        ]);
                    }
                }

                $activeSlotIds = isset($row['active_slot_ids']) && !empty($row['active_slot_ids'])
                    ? (string)$row['active_slot_ids'] : null;
                $slotCustomTimes = isset($row['slot_custom_times']) && !empty($row['slot_custom_times'])
                    ? (string)$row['slot_custom_times'] : null;

                // Collect per shift for delete-reinsert
                $gKey = $date . '_' . $shiftId;
                if (!isset($dailyGroup[$gKey])) {
                    $dailyGroup[$gKey] = ['date' => $date, 'shift_id' => $shiftId, 'rows' => []];
                }

                // Calculate total active minutes
                $totalMinute = $this->calcActiveMinutes($db, $shiftId, $activeSlotIds, $slotCustomTimes);
                $hours = ($totalMinute > 0) ? ($totalMinute / 60) : 0;
                $targetPerHour = ($hours > 0) ? (int)ceil($qtyP / $hours) : 0;

                $dailyGroup[$gKey]['rows'][] = [
                    'production_date' => $date, 'shift_id' => $shiftId, 'machine_id' => $machineId,
                    'product_id' => $productId, 'part_label' => $partLabel, 'qty_p' => $qtyP,
                    'qty_a' => 0, 'qty_ng' => 0, 'status' => $statusRow, 'process_id' => $processIdDC,
                    'is_completed' => 0, 'active_slot_ids' => $activeSlotIds,
                    'slot_custom_times' => $slotCustomTimes, 'created_at' => $now,
                    '_cycle' => $cycle, '_cavity' => $cavity, '_tph' => $targetPerHour,
                ];
            }

            // Delete-and-Reinsert per shift (preserves duplicate machines)
            $hasSlotCustomCol = $db->fieldExists('slot_custom_times', 'die_casting_production');
            $hasActiveSlotCol = $db->fieldExists('active_slot_ids', 'die_casting_production');

            foreach ($dailyGroup as $g) {
                // Delete all existing rows for this shift
                $db->table('die_casting_production')->where([
                    'production_date' => $g['date'], 'shift_id' => $g['shift_id'],
                ])->delete();

                // Insert all rows
                foreach ($g['rows'] as $r) {
                    $payload = [
                        'production_date' => $r['production_date'], 'shift_id' => $r['shift_id'],
                        'machine_id' => $r['machine_id'], 'product_id' => $r['product_id'],
                        'part_label' => $r['part_label'], 'qty_p' => $r['qty_p'],
                        'qty_a' => $r['qty_a'], 'qty_ng' => $r['qty_ng'],
                        'status' => $r['status'], 'process_id' => $r['process_id'],
                        'is_completed' => $r['is_completed'], 'created_at' => $r['created_at'],
                    ];
                    if ($hasActiveSlotCol) $payload['active_slot_ids'] = $r['active_slot_ids'];
                    if ($hasSlotCustomCol) $payload['slot_custom_times'] = $r['slot_custom_times'];

                    $db->table('die_casting_production')->insert($payload);
                    $sourceId = (int)$db->insertID();

                    $wipStatus = ($g['date'] === $today) ? 'SCHEDULED' : 'WAITING';
                    $this->upsertProductionWip(
                        $db, $g['date'], $r['product_id'], $processIdDC, $processIdDC, $r['qty_p'],
                        $wipStatus, 'die_casting_production', $sourceId, $r['qty_p'], 0, 0
                    );
                }

                // Sync daily_schedule_items
                $headerId = $this->upsertDailyScheduleHeader($db, $g['date'], $processIdDC, $g['shift_id'], 'Die Casting', null);
                if ($headerId > 0) {
                    $schedItems = [];
                    foreach ($g['rows'] as $r) {
                        $schedItems[] = [
                            'daily_schedule_id' => $headerId, 'shift_id' => $g['shift_id'],
                            'machine_id' => $r['machine_id'], 'product_id' => $r['product_id'],
                            'cycle_time' => $r['_cycle'], 'cavity' => $r['_cavity'],
                            'target_per_hour' => $r['_tph'], 'target_per_shift' => $r['qty_p'],
                            'is_selected' => 1, 'end_time_slot_id' => null,
                        ];
                    }
                    $this->rebuildDailyScheduleItems($db, $headerId, $schedItems);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Schedule DC & Dandori tersimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function finishShift()
    {
        $db = db_connect();

        $date    = (string)$this->request->getPost('date');
        $shiftId = (int)$this->request->getPost('shift_id');

        if (!$date || $shiftId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        $db->transBegin();
        try {
            $processIdDC = $this->getProcessIdDieCasting($db);

            $actuals = [];
            if ($db->tableExists('die_casting_hourly')) {
                $actuals = $db->table('die_casting_hourly')
                    ->select('machine_id, product_id, SUM(qty_fg) AS total_fg, SUM(qty_ng) AS total_ng')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->groupBy('machine_id, product_id')
                    ->get()->getResultArray();
            }

            $actMap = [];
            foreach ($actuals as $a) {
                $key = (int)$a['machine_id'] . '_' . (int)$a['product_id'];
                $actMap[$key] = [
                    'fg' => (int)($a['total_fg'] ?? 0),
                    'ng' => (int)($a['total_ng'] ?? 0),
                ];
            }

            $rows = $db->table('die_casting_production')
                ->select('id, machine_id, product_id, qty_p')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->where('qty_p >', 0)
                ->get()->getResultArray();

            $processed = 0;

            foreach ($rows as $r) {
                $sourceId  = (int)$r['id'];
                $machineId = (int)$r['machine_id'];
                $productId = (int)$r['product_id'];
                if ($sourceId <= 0 || $productId <= 0) continue;

                $key   = $machineId . '_' . $productId;
                $qtyA  = (int)($actMap[$key]['fg'] ?? 0);
                $qtyNG = (int)($actMap[$key]['ng'] ?? 0);

                $db->table('die_casting_production')
                    ->where('id', $sourceId)
                    ->update([
                        'qty_a'        => $qtyA,
                        'qty_ng'       => $qtyNG,
                        'is_completed' => 1,
                    ]);

                $this->upsertProductionWip(
                    $db, $date, $productId, $processIdDC, $processIdDC, $qtyA,
                    'DONE', 'die_casting_production', $sourceId, null, $qtyA, 0
                );

                $nextProcessId = $this->getNextProcessIdByFlow($db, $productId, $processIdDC);
                if ($nextProcessId && $qtyA > 0) {
                    $this->upsertProductionWip(
                        $db, $date, $productId, $processIdDC, $nextProcessId, $qtyA,
                        'WAITING', 'die_casting_production', $sourceId, $qtyA, 0, $qtyA
                    );
                }

                $processed++;
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return $this->response->setJSON([
                'status'  => true,
                'message' => 'Finish shift OK. WIP DC selesai dan qtyA masuk ke proses berikutnya sebagai IN/Stock.',
                'count'   => $processed
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function inventory()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        
        $role = (string)(session()->get('role') ?? '');
        $isAdmin = (strtoupper($role) === 'ADMIN');
        if (!$isAdmin) $date = date('Y-m-d');

        $ts = strtotime($date);
        $bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
        $m = (int)date('n', $ts);
        $titleDate = date('d', $ts) . ' ' . ($bulan[$m] ?? date('M',$ts)) . ' ' . date('Y', $ts);

        $processIdDC = $this->getProcessIdDieCasting($db);

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
                ->where('w.to_process_id', $processIdDC)
                ->where('w.id IN (
                    SELECT MAX(id) 
                    FROM production_wip 
                    WHERE '.$wipDateCol.' <= "'.$date.'" 
                    AND to_process_id = '.$processIdDC.'
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

        return view('die_casting/daily_schedule/inventory', [
            'date'        => $date,
            'titleDate'   => $titleDate,
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}