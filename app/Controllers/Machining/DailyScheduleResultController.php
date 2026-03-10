<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    /* =====================================================
     * HELPER: Ambil Process ID Machining Secara Dinamis
     * ===================================================== */
    private function getProcessIdMachining($db): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Machining')
            ->get()
            ->getRowArray();

        if (!$row) throw new \Exception('Process "Machining" belum ada di master production_processes');
        return (int)$row['id'];
    }

    /* =====================================================
     * HELPER: Total Detik / Menit Shift
     * ===================================================== */
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

    private function getTotalMinuteShift($db, int $shiftId): int
    {
        return (int)floor($this->getTotalSecondShift($db, $shiftId) / 60);
    }

    /* =====================================================
     * HELPER: Upsert WIP (Sama seperti Die Casting)
     * ===================================================== */
    private function upsertProductionWip(
        $db,
        string $date,
        int $productId,
        int $fromProcessId,
        int $toProcessId,
        int $qty,
        string $status,
        string $sourceTable,
        int $sourceId,
        ?int $qtyIn = null,
        ?int $qtyOut = null,
        ?int $stock = null
    ): int {
        if (!$db->tableExists('production_wip')) return 0;

        $where = [
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromProcessId,
            'to_process_id'   => $toProcessId,
            'source_table'    => $sourceTable,
            'source_id'       => $sourceId,
        ];

        $exist = $db->table('production_wip')->where($where)->get()->getRowArray();
        $payload = $where + [
            'qty'    => $qty,
            'status' => $status,
        ];

        if ($qtyIn !== null && $db->fieldExists('qty_in', 'production_wip'))   $payload['qty_in'] = $qtyIn;
        if ($qtyOut !== null && $db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = $qtyOut;
        if ($stock !== null && $db->fieldExists('stock', 'production_wip'))    $payload['stock'] = $stock;

        $now = date('Y-m-d H:i:s');
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            if (($exist['status'] ?? '') === 'DONE' && $status !== 'DONE') {
                return (int)$exist['id'];
            }
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
            return (int)$exist['id'];
        }

        if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
        $db->table('production_wip')->insert($payload);
        return (int)$db->insertID();
    }

    /* =====================================================
     * HELPER: Upsert Schedule Header (Sama seperti Die Casting)
     * ===================================================== */
    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;

        $where = [
            'schedule_date' => $date,
            'process_id'    => $processId,
            'shift_id'      => $shiftId,
            'section'       => $section,
        ];

        $exist = $db->table('daily_schedules')->where($where)->get()->getRowArray();
        $now   = date('Y-m-d H:i:s');

        if ($exist) {
            $update = ['is_completed' => 0];
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

    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d', strtotime('+1 day'));

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

    /* =====================================================
     * AJAX: GET PRODUCT & TARGET
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');
        $date    = (string)($this->request->getGet('date') ?? date('Y-m-d'));
        $todayDate = date('Y-m-d'); 

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdMC = $this->getProcessIdMachining($db);
        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        $hasCtMach   = $db->fieldExists('cycle_time_machining', 'products');

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
                $p['target_per_shift'] = min((int)floor(($totalSecond / $cycle) * $cavity * $eff), 1200);
            } else {
                $p['target_per_shift'] = 0;
            }

            // Ambil data WIP terbaru untuk part ID di Machining
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

    /* =====================================================
     * AJAX: ASAKAI APPROVAL DATA
     * ===================================================== */
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

    /* =====================================================
     * STORE SCHEDULE (PLAN)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Tidak ada data');
        }

        $role  = session()->get('role') ?? '';
        $today = date('Y-m-d'); 

        $formDate = trim((string)$this->request->getPost('date'));
        if ($formDate === '' && !empty($items)) {
            $firstRow = reset($items);
            $formDate = trim((string)($firstRow['date'] ?? ''));
        }

        if ($formDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formDate)) {
            return redirect()->back()->with('error', 'Tanggal schedule tidak valid.');
        }

        if ($role !== 'ADMIN' && $formDate <= $today) {
            return redirect()->back()->with('error', 'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh membuat schedule mulai besok.');
        }

        $now = date('Y-m-d H:i:s');
        $hasCtMach = $db->fieldExists('cycle_time_machining', 'products');

        $db->transBegin();
        try {
            $processIdMC = $this->getProcessIdMachining($db);

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);
                $planInput = max(0, (int)($row['plan'] ?? 0));
                
                // Gunakan tanggal dari form (master header)
                $dateRow = $formDate;

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                // Handle jika plan di-NOL kan (0)
                if ($planInput <= 0) {
                    $existHeader = $db->table('daily_schedules')->where(['schedule_date' => $dateRow, 'process_id' => $processIdMC, 'shift_id' => $shiftId, 'section' => 'Machining'])->get()->getRowArray();
                    if ($existHeader) {
                        $existItem = $db->table('daily_schedule_items')->where(['daily_schedule_id' => $existHeader['id'], 'machine_id' => $machineId])->get()->getRowArray();
                        if ($existItem) {
                            $db->table('daily_schedule_items')->where('id', $existItem['id'])->update(['target_per_shift' => 0, 'is_selected' => 0]);
                            $this->upsertProductionWip($db, $dateRow, $productId, 0, $processIdMC, 0, 'WAITING', 'daily_schedule_items', $existItem['id'], 0, 0, 0);
                        }
                    }
                    continue;
                }

                $product = $db->table('products')->where('id', $productId)->get()->getRowArray();
                if (!$product) continue;

                $cycle   = (int)($product[$hasCtMach ? 'cycle_time_machining' : 'cycle_time'] ?? 0);
                $cavity  = (int)($product['cavity'] ?? 0);
                
                $totalMinute = $this->getTotalMinuteShift($db, $shiftId);
                $hours = ($totalMinute > 0) ? ($totalMinute / 60) : 0;
                $targetPerHour = ($hours > 0) ? (int)ceil($planInput / $hours) : 0;

                // 1. Upsert Header Schedule
                $scheduleId = $this->upsertDailyScheduleHeader($db, $dateRow, $processIdMC, $shiftId, 'Machining');

                // 2. Upsert Detail Items Schedule
                $existItem = $db->table('daily_schedule_items')
                    ->where(['daily_schedule_id' => $scheduleId, 'machine_id' => $machineId])
                    ->get()->getRowArray();

                $dataItem = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $planInput,
                    'is_selected'       => 1
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')->where('id', $existItem['id'])->update($dataItem);
                    $itemId = (int)$existItem['id'];
                } else {
                    $db->table('daily_schedule_items')->insert($dataItem);
                    $itemId = (int)$db->insertID();
                }

                // 3. Upsert WIP Production (Persis Alur Die Casting: Stock diset 0)
                $wipStatus = ($dateRow === $today) ? 'SCHEDULED' : 'WAITING';

                // Cari riwayat dari mana proses ini berasal
                $wipLast = $db->table('production_wip')
                              ->where(['product_id' => $productId, 'to_process_id' => $processIdMC])
                              ->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
                $fromProcessId = $wipLast ? (int)$wipLast['from_process_id'] : 0;

                $this->upsertProductionWip(
                    $db,
                    $dateRow,
                    $productId,
                    $fromProcessId,
                    $processIdMC,
                    $planInput,             // qty (jumlah rencana)
                    $wipStatus,             // status
                    'daily_schedule_items', // source_table
                    $itemId,                // source_id
                    $planInput,             // qty_in (sama seperti Die Casting)
                    0,                      // qty_out
                    0                       // stock -> di-set 0 (Sesuai request aktual dipotong lewat per jam)
                );
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error saat menyimpan jadwal.');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Jadwal Machining dan WIP tersimpan dengan struktur Die Casting.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * INVENTORY STOCK (Menyamakan Logika Die Casting)
     * ===================================================== */
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
            // Gunakan logika subquery MAX id persis dari index() Die Casting
            $query = $db->table($tbl . ' w')
                ->select('w.product_id, p.part_no, p.part_name, w.'.$colStock.' as current_stock')
                ->join('products p', 'p.id = w.product_id', 'inner')
                ->where("w.$wipDateCol <=", $date)
                ->where('w.to_process_id', $processIdMC)
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
                
                // Karena jadwal menset stock = 0, inventory ini hanya melihat
                // "sisa real" yang murni belum dijadwalkan / hasil aktual.
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

        return view('machining/schedule/inventory', [
            'date'        => $date,
            'titleDate'   => $titleDate,
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}