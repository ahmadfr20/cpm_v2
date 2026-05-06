<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    private function getProcessIdByCategory($db, string $category): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', $category)
            ->get()
            ->getRowArray();

        if (!$row) throw new \Exception('Process "' . $category . '" belum ada di master production_processes');
        return (int)$row['id'];
    }

    private function getTotalSecondShift($db, int $shiftId, ?int $endSlotId = null): int
    {
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.id, ts.time_start, ts.time_end, sts.is_break')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('sts.id', 'ASC')
            ->get()
            ->getResultArray();

        $totalSecond = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400; // Lewat tengah malam
            
            if (empty($s['is_break'])) {
                $totalSecond += ($end - $start);
            }

            if ($endSlotId !== null && (int)$s['id'] === $endSlotId) {
                break; // Stop perhitungan jika ini adalah slot terakhir shift yang disetel
            }
        }
        return (int)$totalSecond;
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d', strtotime('+1 day'));
        
        // Auto-add kolom active_slot_ids dan slot_custom_times ke daily_schedule_items jika belum ada
        if (!$db->fieldExists('active_slot_ids', 'daily_schedule_items')) {
            try { $db->query("ALTER TABLE daily_schedule_items ADD COLUMN active_slot_ids TEXT NULL AFTER end_time_slot_id"); } catch (\Throwable $e) {}
        }
        if (!$db->fieldExists('slot_custom_times', 'daily_schedule_items')) {
            try { $db->query("ALTER TABLE daily_schedule_items ADD COLUMN slot_custom_times TEXT NULL AFTER active_slot_ids"); } catch (\Throwable $e) {}
        }

        $category = $this->request->getGet('category') ?? 'Machining';
        $processIdMC = $this->getProcessIdByCategory($db, $category);

        // Ambil process code dari category untuk filter shift
        $processRow = $db->table('production_processes')
            ->select('process_code')
            ->where('process_name', $category)
            ->get()->getRowArray();
        $processCode = $processRow['process_code'] ?? 'MC';

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->where('day_group', $this->getDayGroup($date))
            ->like('shift_name', $processCode)
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        // Fallback: kalau tidak ada shift untuk process code ini, pakai shift MC
        if (empty($shifts)) {
            $shifts = $db->table('shifts')
                ->select('id, shift_code, shift_name')
                ->where('is_active', 1)
                ->where('day_group', $this->getDayGroup($date))
                ->like('shift_name', 'MC')
                ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
                ->get()
                ->getResultArray();
        }

        // Susun Time Slots per Shift
        $shiftSlots = [];
        foreach ($shifts as &$shift) {
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id as time_slot_id, ts.time_start, ts.time_end, sts.is_break')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', (int)$shift['id'])
                ->orderBy('sts.id', 'ASC')
                ->get()->getResultArray();

            $totalSecond = 0;
            foreach ($slots as &$s) {
                $start = strtotime($s['time_start']);
                $end   = strtotime($s['time_end']);
                if ($end <= $start) $end += 86400;
                $secs = ($end - $start);
                
                if (!empty($s['is_break'])) {
                    $s['seconds'] = 0;
                } else {
                    $s['seconds'] = $secs;
                    $totalSecond += $secs;
                }
                
                $s['label']   = substr($s['time_start'], 0, 5) . ' - ' . substr($s['time_end'], 0, 5);
            }
            $shift['total_second'] = $totalSecond;
            $shiftSlots[$shift['id']] = $slots;
        }
        unset($shift);

        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', $category)
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        // Fallback: jika tidak ada mesin untuk kategori ini, tampilkan mesin Machining
        if (empty($machines) && $category !== 'Machining') {
            $machines = $db->table('machines m')
                ->select('m.id, m.machine_code, m.machine_name, m.line_position')
                ->join('production_processes pp', 'pp.id = m.process_id')
                ->where('pp.process_name', 'Machining')
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();
        }

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', $category)
            ->orderBy('dsi.id', 'ASC')
            ->get()
            ->getResultArray();

        // Map data schedule
        $planMap = [];
        foreach ($existing as $e) {
            $planMap[(int)$e['shift_id']][(int)$e['machine_id']][] = $e;
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

        // Ambil data Dandori Machining yang sudah dicentang
        $dandoriRecords = [];
        if ($db->tableExists('machining_dandori')) {
            $dandoriRecords = $db->table('machining_dandori')
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
                    'slot_minutes'  => [],
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

        // Active Slot IDs + Custom Times per machine per shift
        $activeSlotMap = [];
        $slotCustomTimesMap = [];
        $mapCount = [];
        $hasSlotCustomCol = $db->fieldExists('slot_custom_times', 'daily_schedule_items');
        $slotSelect = 'dsi.shift_id, dsi.machine_id, dsi.active_slot_ids';
        if ($hasSlotCustomCol) $slotSelect .= ', dsi.slot_custom_times';
        $existingSlots = $db->table('daily_schedule_items dsi')
            ->select($slotSelect)
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', $category)
            ->orderBy('dsi.id', 'ASC')
            ->get()->getResultArray();
            
        foreach ($existingSlots as $e) {
            $sId = (int)($e['shift_id'] ?? 0);
            if ($sId <= 0) continue;
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

        // Cek Role Admin
        $role = (string)(session()->get('role') ?? '');
        $isAdmin = (strtoupper($role) === 'ADMIN');

        return view('machining/schedule/index', [
            'category'           => $category,
            'processCode'        => $processCode,
            'date'               => $date,
            'shifts'             => $shifts,
            'shiftSlots'         => $shiftSlots,
            'machines'           => $machines,
            'planMap'            => $planMap,
            'actualMap'          => $actualMap,
            'dandoriMap'         => $dandoriMap,
            'activeSlotMap'      => $activeSlotMap,
            'slotCustomTimesMap' => $slotCustomTimesMap,
            'isAdmin'            => $isAdmin
        ]);
    }

    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');
        $date    = (string)($this->request->getGet('date') ?? date('Y-m-d'));
        $todayDate = date('Y-m-d'); 

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $category = $this->request->getGet('category') ?? 'Machining';
        $processIdMC = $this->getProcessIdByCategory($db, $category);
        $hasCtMach   = $db->fieldExists('cycle_time_machining', 'products');

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);

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

        // Fallback: jika kategori khusus tidak ada produk, gunakan produk dari Machining
        if (empty($products) && $category !== 'Machining') {
            $machiningProcessId = $this->getProcessIdByCategory($db, 'Machining');
            $products = $db->table('product_process_flows ppf')
                ->select('p.id, p.part_no, p.part_name, p.cavity, p.efficiency_rate'
                    . ($hasCtMach ? ', p.cycle_time_machining' : ', p.cycle_time'))
                ->join('products p', 'p.id = ppf.product_id')
                ->where('ppf.is_active', 1)
                ->where('p.is_active', 1)
                ->where('ppf.process_id', $machiningProcessId)
                ->groupBy('p.id')
                ->orderBy('p.part_no', 'ASC')
                ->get()
                ->getResultArray();
        }

        foreach ($products as &$p) {
            $cycle  = $hasCtMach ? (int)($p['cycle_time_machining'] ?? 0) : (int)($p['cycle_time'] ?? 0);
            $cavity = (int)($p['cavity'] ?? 0);
            $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            $p['cycle_time_used'] = $cycle;

            if ($cycle > 0 && $cavity > 0) {
                $p['target_per_shift'] = min((int)floor(($totalSecond / $cycle) * $cavity * $eff), 1200);
            } else {
                $p['target_per_shift'] = 0;
            }

            $wipMC = $db->table('production_wip')
                        ->select('stock')
                        ->where('product_id', $p['id'])
                        ->where('to_process_id', $processIdMC)
                        ->orderBy('id', 'DESC') 
                        ->limit(1)
                        ->get()->getRowArray();
                        
            $p['stock_ready'] = $wipMC ? (int)$wipMC['stock'] : 0;

            $ngDetails = [];
            $totalNg = 0;

            if ($db->tableExists('machining_transfer_ng')) {
                $ngData = $db->table('machining_transfer_ng mtn')
                             ->select('mtn.qty, nc.ng_name')
                             ->join('ng_categories nc', 'nc.id = mtn.ng_category_id')
                             ->where('mtn.product_id', $p['id'])
                             ->where('mtn.transaction_date', $todayDate) 
                             ->get()->getResultArray();
                
                foreach($ngData as $ng) {
                    $totalNg += (int)$ng['qty'];
                    $ngDetails[] = $ng['ng_name'] . ': ' . $ng['qty'];
                }
            }
            
            $p['ng_before_total'] = $totalNg;
            $p['ng_before_list']  = implode('<br>', $ngDetails); 
        }
        unset($p);

        return $this->response->setJSON($products);
    }

    public function getApprovalData()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d'); 

        $productionData = [];
        if ($db->tableExists('machining_hourly')) {
            $productionData = $db->table('machining_hourly mh')
                ->select('p.part_no, p.part_name, SUM(mh.qty_fg) as total_fg, SUM(mh.qty_ng) as total_ng')
                ->join('products p', 'p.id = mh.product_id')
                ->where('mh.production_date', $date)
                ->groupBy('p.id')
                ->get()->getResultArray();
        }

        $ngData = [];
        if ($db->tableExists('machining_hourly_ng_details')) {
            $ngData = $db->table('machining_hourly_ng_details mnd')
                ->select('p.part_no, p.part_name, nc.ng_name, SUM(mnd.qty) as total_ng')
                ->join('machining_hourly mh', 'mh.id = mnd.machining_hourly_id')
                ->join('products p', 'p.id = mh.product_id')
                ->join('ng_categories nc', 'nc.id = mnd.ng_category_id')
                ->where('mh.production_date', $date)
                ->groupBy('p.id, mnd.ng_category_id')
                ->get()->getResultArray();
        }

        $isApproved = false;
        $approvedBy = '';
        
        if ($db->tableExists('approval_logs')) {
            $appLog = $db->table('approval_logs')
                ->where('approval_type', 'MACHINING_PRODUCTION')
                ->where('approval_date', $date)
                ->get()->getRowArray();

            if($appLog) {
                $isApproved = true;
                $approvedBy = $appLog['approved_by_name'];
            }
        }

        return $this->response->setJSON([
            'production_data' => $productionData,
            'ng_data'         => $ngData,
            'is_approved'     => $isApproved,
            'approved_by'     => $approvedBy,
            'date_format'     => date('d M Y', strtotime($date)),
            'raw_date'        => $date
        ]);
    }

    public function approveStock()
    {
        $db = db_connect();
        $date = $this->request->getPost('date') ?? date('Y-m-d');
        $fullname = session()->get('fullname') ?? 'User Unknown';

        if (!$db->tableExists('approval_logs')) {
            $db->query("CREATE TABLE `approval_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `approval_type` VARCHAR(50),
                `approval_date` DATE,
                `approved_by_name` VARCHAR(100),
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }

        $exist = $db->table('approval_logs')
            ->where('approval_type', 'MACHINING_PRODUCTION')
            ->where('approval_date', $date)
            ->countAllResults();

        if ($exist == 0) {
            $db->table('approval_logs')->insert([
                'approval_type'    => 'MACHINING_PRODUCTION', 
                'approval_date'    => $date,
                'approved_by_name' => $fullname
            ]);
        }

        return redirect()->back()->with('success', 'Data produksi Machining tanggal ' . date('d M Y', strtotime($date)) . ' telah di-Approve oleh ' . $fullname);
    }

    public function store()
    {
        $db    = db_connect();
        $date  = trim((string)$this->request->getPost('date'));
        // Support JSON-consolidated items to bypass max_input_vars limit
        $itemsJson = $this->request->getPost('items_json');
        if ($itemsJson) {
            $items = json_decode($itemsJson, true);
        } else {
            $items = $this->request->getPost('items');
        }
        
        if ($date === '' || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $category = trim((string)$this->request->getPost('category')) ?: 'Machining';
        $processIdMC = $this->getProcessIdByCategory($db, $category);
        $hasCtMach     = $db->fieldExists('cycle_time_machining', 'products');
        $hasEndSlotCol = $db->fieldExists('end_time_slot_id', 'daily_schedules');
        $now           = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            $activeSchedules = [];

            // Hapus data Dandori lama untuk tanggal ini agar tersinkronisasi ulang saat update
            if ($db->tableExists('machining_dandori')) {
                $db->table('machining_dandori')->where('dandori_date', $date)->delete();
            }

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);
                $planInput = max(0, min((int)($row['plan'] ?? 0), 1200));

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;
                
                $endSlotId = !empty($row['end_time_slot_id']) ? (int)$row['end_time_slot_id'] : null;

                // Cek Isian Dandori (format slot_data baru)
                $isDandori = isset($row['is_dandori']) && $row['is_dandori'] == 1;

                // Insert ke tabel Machining Dandori dengan format slot_data baru
                if ($isDandori && $db->tableExists('machining_dandori')) {
                    $slotData = $row['slot_data'] ?? [];
                    $hasAnySlot = false;

                    foreach ($slotData as $tsId => $slotInfo) {
                        if (empty($slotInfo['selected'])) continue;
                        $tsId = (int)$tsId;
                        $dandoriMinute = (int)($slotInfo['minute'] ?? 0);
                        $db->table('machining_dandori')->insert([
                            'dandori_date'   => $date,
                            'shift_id'       => $shiftId,
                            'machine_id'     => $machineId,
                            'product_id'     => $productId,
                            'time_slot_id'   => $tsId,
                            'dandori_minute' => $dandoriMinute,
                            'activity'       => 'Dandori',
                            'created_at'     => $now
                        ]);
                        $hasAnySlot = true;
                    }

                    if (!$hasAnySlot) {
                        $db->table('machining_dandori')->insert([
                            'dandori_date'   => $date,
                            'shift_id'       => $shiftId,
                            'machine_id'     => $machineId,
                            'product_id'     => $productId,
                            'time_slot_id'   => null,
                            'dandori_minute' => 0,
                            'activity'       => 'Dandori',
                            'created_at'     => $now
                        ]);
                    }
                }

                // Jika target = 0 dan tidak ada dandori, tidak usah dijadwalkan
                if ($planInput <= 0 && !$isDandori) continue;

                // 1. Dapatkan atau Buat Master Jadwal Harian
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date, 
                        'shift_id'      => $shiftId, 
                        'section'       => $category,
                        'process_id'    => $processIdMC
                    ])
                    ->get()->getRowArray();

                if (!$schedule) {
                    $insertData = [
                        'schedule_date' => $date,
                        'process_id'    => $processIdMC, 
                        'shift_id'      => $shiftId,
                        'section'       => $category,
                        'is_completed'  => 0,
                        'created_at'    => $now
                    ];
                    // We no longer set end_time_slot_id to schedule (header)
                    
                    $db->table('daily_schedules')->insert($insertData);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];
                    $updateData = ['updated_at' => $now];
                    // We no longer set end_time_slot_id to schedule (header)
                    
                    $db->table('daily_schedules')->where('id', $scheduleId)->update($updateData);
                }

                if (!isset($activeSchedules[$scheduleId])) {
                    $activeSchedules[$scheduleId] = [];
                }

                if ($planInput <= 0) continue;

                // 2. Cek Detail Item Jadwal
                $existItem = $db->table('daily_schedule_items')
                    ->where(['daily_schedule_id' => $scheduleId, 'machine_id' => $machineId, 'product_id' => $productId])
                    ->get()->getRowArray();

                $oldPlan = $existItem ? (int)($existItem['target_per_shift'] ?? 0) : 0;
                $isPlanChanged = (!$existItem) || ($oldPlan !== $planInput);

                // --- LOG JADWAL KE WIP (hanya untuk category Machining) ---
                // Untuk kategori proses khusus (Leak Test, Assy Bushing, dll), WIP dikelola oleh
                // masing-masing hourly controller saat operator input data produksi.
                if ($isPlanChanged && $category === 'Machining') {
                    $wipNew = $db->table('production_wip')
                                 ->where(['product_id' => $productId, 'to_process_id' => $processIdMC])
                                 ->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
                                 
                    $db->table('production_wip')->insert([
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $wipNew ? $wipNew['from_process_id'] : null,
                        'to_process_id'   => $processIdMC,
                        'qty'             => $planInput,
                        'qty_in'          => 0,
                        'qty_out'         => 0,
                        'stock'           => 0,          
                        'transfer'        => $planInput, 
                        'status'          => 'SCHEDULED', 
                        'source_table'    => 'daily_schedule_items',
                        'source_id'       => $scheduleId, 
                        'created_at'      => $now
                    ]);
                }

                $product = $db->table('products')->where('id', $productId)->get()->getRowArray();
                $cycle   = (int)($product[$hasCtMach ? 'cycle_time_machining' : 'cycle_time'] ?? 0);
                $cavity  = (int)($product['cavity'] ?? 0);
                $targetHour = ($cycle > 0) ? (int)floor((3600 / $cycle) * $cavity * (($product['efficiency_rate']??100)/100)) : 0;

                // Simpan active_slot_ids per mesin
                $activeSlotIds = !empty($row['active_slot_ids']) ? (string)$row['active_slot_ids'] : null;
                $slotCustomTimes = !empty($row['slot_custom_times']) ? (string)$row['slot_custom_times'] : null;

                $activeSchedules[$scheduleId][] = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetHour,
                    'target_per_shift'  => $planInput,
                    'is_selected'       => 1,
                    'end_time_slot_id'  => null,
                    'active_slot_ids'   => $activeSlotIds,
                    'slot_custom_times' => $slotCustomTimes,
                ];
            }

            // 3. Rebuild (Hapus dan Insert Ulang) Detail Schedule per Shift
            foreach ($activeSchedules as $schId => $itemsToInsert) {
                $db->table('daily_schedule_items')->where('daily_schedule_id', $schId)->delete();
                if (!empty($itemsToInsert)) {
                    $db->table('daily_schedule_items')->insertBatch($itemsToInsert);
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('Terjadi kesalahan database saat menyimpan jadwal.');
            }
            
            $db->transCommit();
            return redirect()->back()->with('success', 'Jadwal ' . $category . ' berhasil disimpan.');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
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

        $category = $this->request->getGet('category') ?? 'Machining';
        $processIdMC = $this->getProcessIdByCategory($db, $category);

        $productData = [];

        if ($db->tableExists('production_wip')) {
            $query = $db->table('production_wip w')
                ->select('w.product_id, p.part_no, p.part_name, w.stock, w.transfer')
                ->join('products p', 'p.id = w.product_id', 'inner')
                ->where('w.to_process_id', $processIdMC) 
                ->where('w.id IN (
                    SELECT MAX(id) 
                    FROM production_wip 
                    WHERE to_process_id = '.$processIdMC.'
                    GROUP BY product_id
                )', null, false)
                ->get()
                ->getResultArray();

            foreach ($query as $row) {
                $stock = (int)$row['stock'];
                $transfer = (int)$row['transfer'];
                
                $totalWipFisik = $stock + $transfer;

                if($totalWipFisik > 0) {
                    $productData[] = [
                        'part_no'     => $row['part_no'],
                        'part_name'   => $row['part_name'],
                        'total_stock' => $stock,      
                        'transfer'    => $transfer,   
                        'total_wip'   => $totalWipFisik 
                    ];
                }
            }
        }

        usort($productData, function($a, $b) {
            return strcmp($a['part_no'], $b['part_no']);
        });

        return view('machining/schedule/inventory', [
            'category'    => $category,
            'date'        => $date,
            'titleDate'   => $titleDate,
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}