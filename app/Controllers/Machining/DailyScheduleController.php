<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    // Mengambil ID Proses secara dinamis dari tabel (Mencegah salah ID)
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
        $items = $this->request->getPost('items');

        if ($date === '' || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
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
                $planInput = max(0, min((int)($row['plan'] ?? 0), 1200));

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                // 1. Dapatkan atau Buat Master Jadwal Harian
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date, 
                        'shift_id'      => $shiftId, 
                        'section'       => 'Machining',
                        'process_id'    => $processIdMC
                    ])
                    ->get()->getRowArray();

                if (!$schedule) {
                    $db->table('daily_schedules')->insert([
                        'schedule_date' => $date,
                        'process_id'    => $processIdMC, 
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                        'is_completed'  => 0,
                        'created_at'    => $now
                    ]);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];
                }

                // 2. Cek Detail Item Jadwal
                $existItem = $db->table('daily_schedule_items')
                    ->where(['daily_schedule_id' => $scheduleId, 'machine_id' => $machineId])
                    ->get()->getRowArray();

                $oldProductId = $existItem ? (int)($existItem['product_id'] ?? 0) : 0;
                $oldPlan      = $existItem ? (int)($existItem['target_per_shift'] ?? 0) : 0;

                // --- DETEKSI PERUBAHAN ---
                $isPlanChanged = false;
                if (!$existItem && $planInput > 0) {
                    $isPlanChanged = true; 
                } else if ($existItem && ($oldProductId !== $productId || $oldPlan !== $planInput)) {
                    $isPlanChanged = true; 
                }

                // --- LOG JADWAL KE WIP (TANPA KALKULASI / PEMOTONGAN STOK) ---
                if ($isPlanChanged && $planInput > 0) {
                    // Cek riwayat lama hanya untuk mendapatkan from_process_id
                    $wipNew = $db->table('production_wip')
                                 ->where(['product_id' => $productId, 'to_process_id' => $processIdMC])
                                 ->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
                                 
                    // Insert dengan nilai stock = 0 secara absolut (sesuai instruksi)
                    $db->table('production_wip')->insert([
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $wipNew ? $wipNew['from_process_id'] : null,
                        'to_process_id'   => $processIdMC,
                        'qty'             => $planInput,
                        'qty_in'          => 0,
                        'qty_out'         => 0,
                        'stock'           => 0,          // Selalu 0 saat penjadwalan
                        'transfer'        => $planInput, // Jumlah target plan dicatat di sini
                        'status'          => 'SCHEDULED', 
                        'source_table'    => 'daily_schedule_items',
                        'source_id'       => $scheduleId, 
                        'created_at'      => $now
                    ]);
                }

                // 3. Simpan Update ke Tabel Schedule Asli
                $product = $db->table('products')->where('id', $productId)->get()->getRowArray();
                $cycle   = (int)($product[$hasCtMach ? 'cycle_time_machining' : 'cycle_time'] ?? 0);
                $cavity  = (int)($product['cavity'] ?? 0);
                $targetHour = ($cycle > 0) ? (int)floor((3600 / $cycle) * $cavity * (($product['efficiency_rate']??100)/100)) : 0;

                $dataItem = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetHour,
                    'target_per_shift'  => $planInput,
                    'is_selected'       => ($planInput > 0) ? 1 : 0
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')->where('id', $existItem['id'])->update($dataItem);
                } else {
                    if ($planInput > 0) {
                        $db->table('daily_schedule_items')->insert($dataItem);
                    }
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('Terjadi kesalahan database saat menyimpan jadwal.');
            }
            
            $db->transCommit();
            return redirect()->back()->with('success', 'Jadwal Machining berhasil disimpan.');

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

        $processIdMC = $this->getProcessIdMachining($db);

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
                
                // Karena saat buat jadwal stock di-set 0, inventory ini akan lebih 
                // mengacu pada total (target plan & sisa stok murni)
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
            'date'        => $date,
            'titleDate'   => $titleDate,
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}