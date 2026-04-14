<?php

namespace App\Controllers\QC;

use App\Controllers\BaseController;

class QCScheduleController extends BaseController
{
    private function detectWipDateColumn($db): string
    {
        return $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';
    }

    private function getQcProcessId($db): int
    {
        $names = ['FINAL INSPECTION', 'Final Inspection', 'Final Inspection / QC', 'QC'];
        foreach ($names as $name) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_name', $name)
                ->get()->getRowArray();
            if ($row) return (int)$row['id'];
        }
        return 0;
    }

    private function getProcessBeforeQc($db, int $productId, int $qcProcessId): int
    {
        $flows = $db->table('product_process_flows')
            ->select('process_id')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();

        $prev = 0;
        foreach ($flows as $f) {
            if ((int)$f['process_id'] === $qcProcessId) {
                return $prev; // return the process right before QC
            }
            $prev = (int)$f['process_id'];
        }
        return $prev; // fallback: return last process if QC not found in flow
    }

    public function index()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // Get shifts
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // Get all products
        $products = [];
        if ($db->tableExists('products')) {
            $productQuery = $db->table('products')->select('id, part_no, part_name');
            if ($db->fieldExists('is_active', 'products')) {
                $productQuery->where('is_active', 1);
            }
            $products = $productQuery->orderBy('part_no', 'ASC')->get()->getResultArray();
        }

        // Get existing schedules for the date
        $schedules = $db->table('qc_schedules qs')
            ->select('qs.*, p.part_no, p.part_name, s.shift_name, pp.process_name')
            ->join('products p', 'p.id = qs.product_id', 'left')
            ->join('shifts s', 's.id = qs.shift_id', 'left')
            ->join('production_processes pp', 'pp.id = qs.source_process_id', 'left')
            ->where('qs.schedule_date', $date)
            ->orderBy('qs.created_at', 'DESC')
            ->get()->getResultArray();

        // Calculate ready stock for each product
        $readyStocks = [];
        $wipDateCol = $this->detectWipDateColumn($db);
        $qcProcessId = $this->getQcProcessId($db);

        foreach ($products as $p) {
            $prevProcessId = $this->getProcessBeforeQc($db, $p['id'], $qcProcessId);
            if ($prevProcessId > 0) {
                // Get wip stock
                $stockQuery = $db->table('production_wip')
                    ->select('SUM(stock) as available_stock')
                    ->where('product_id', $p['id'])
                    ->where('to_process_id', $prevProcessId)
                    ->where('stock >', 0)
                    ->where('status !=', 'DONE')
                    ->where("$wipDateCol <=", $date)
                    ->get()->getRowArray();
                
                $avail = (int)($stockQuery['available_stock'] ?? 0);

                if ($avail > 0) {
                    // Check how much is already scheduled today
                    $schedQuery = $db->table('qc_schedules')
                        ->select('SUM(qty_plan) as total_sched')
                        ->where('product_id', $p['id'])
                        ->where('schedule_date', $date)
                        ->get()->getRowArray();
                        
                    $schedTotal = (int)($schedQuery['total_sched'] ?? 0);
                    $readyToSched = max(0, $avail - $schedTotal);

                    $processNameRow = $db->table('production_processes')->select('process_name')->where('id', $prevProcessId)->get()->getRowArray();
                    $readyStocks[] = [
                        'part_no' => $p['part_no'],
                        'part_name' => $p['part_name'],
                        'process_name' => $processNameRow ? $processNameRow['process_name'] : 'Unknown',
                        'total_wip' => $avail,
                        'already_scheduled' => $schedTotal,
                        'ready_to_schedule' => $readyToSched
                    ];
                }
            }
        }

        return view('qc/schedule/index', [
            'date'        => $date,
            'shifts'      => $shifts,
            'products'    => $products,
            'schedules'   => $schedules,
            'readyStocks' => $readyStocks
        ]);
    }

    public function getAvailableStock()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $productId = (int)$this->request->getGet('product_id');

        if ($productId <= 0 || !$db->tableExists('production_wip')) {
            return $this->response->setJSON([]);
        }

        $wipDateCol = $this->detectWipDateColumn($db);
        $qcProcessId = $this->getQcProcessId($db);
        $prevProcessId = $this->getProcessBeforeQc($db, $productId, $qcProcessId);

        if ($prevProcessId <= 0) {
            return $this->response->setJSON([]);
        }

        // Fetch stock specific to the process before QC
        $query = $db->table('production_wip pw')
            ->select('pw.to_process_id as process_id, pp.process_name, COALESCE(SUM(pw.stock), 0) as available_stock')
            ->join('production_processes pp', 'pp.id = pw.to_process_id', 'left')
            ->where('pw.product_id', $productId)
            ->where('pw.to_process_id', $prevProcessId)
            ->where("pw.$wipDateCol <=", $date)
            ->where('pw.stock >', 0)
            ->where('pw.status !=', 'DONE')
            ->groupBy('pw.to_process_id');

        $stockData = $query->get()->getResultArray();

        // If no stock exists but we want to show the process name
        if (empty($stockData)) {
            $process = $db->table('production_processes')->where('id', $prevProcessId)->get()->getRowArray();
            $stockData = [
                [
                    'process_id' => $prevProcessId,
                    'process_name' => $process ? $process['process_name'] : 'Unknown Process',
                    'available_stock' => 0
                ]
            ];
        }

        return $this->response->setJSON($stockData);
    }

    public function store()
    {
        $db = db_connect();

        $date            = (string)$this->request->getPost('date');
        $shiftId         = (int)$this->request->getPost('shift_id');
        $productId       = (int)$this->request->getPost('product_id');
        $sourceProcessId = (int)$this->request->getPost('source_process_id');
        $qtyPlan         = (int)$this->request->getPost('qty_plan');

        if (empty($date) || $shiftId <= 0 || $productId <= 0 || $sourceProcessId <= 0 || $qtyPlan <= 0) {
            return redirect()->back()->with('error', 'Input tidak lengkap atau Qty Plan invalid.');
        }

        $wipDateCol = $this->detectWipDateColumn($db);

        // Verify stock is actually available
        $availableStockRow = $db->table('production_wip')
            ->select('SUM(stock) as available')
            ->where('product_id', $productId)
            ->where('to_process_id', $sourceProcessId)
            ->where("$wipDateCol <=", $date)
            ->where('stock >', 0)
            ->where('status !=', 'DONE')
            ->get()->getRowArray();

        $available = (int)($availableStockRow['available'] ?? 0);

        if ($qtyPlan > $available) {
            return redirect()->back()->with('error', "Qty Plan ($qtyPlan) melebihi stock tersedia dari proses ini ($available).");
        }

        try {
            $db->table('qc_schedules')->insert([
                'schedule_date'     => $date,
                'shift_id'          => $shiftId,
                'product_id'        => $productId,
                'source_process_id' => $sourceProcessId,
                'qty_plan'          => $qtyPlan,
                'qty_inspected'     => 0,
                'status'            => 'PENDING',
                'created_by'        => session()->get('fullname') ?? 'System',
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ]);
            
            return redirect()->back()->with('success', 'QC Schedule berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan schedule: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $db = db_connect();
        
        $schedule = $db->table('qc_schedules')->where('id', $id)->get()->getRowArray();
        
        if (!$schedule) {
            return redirect()->back()->with('error', 'Schedule tidak ditemukan.');
        }

        if ((int)$schedule['qty_inspected'] > 0) {
            return redirect()->back()->with('error', 'Schedule tidak bisa dihapus karena sudah ada hasil inspeksi.');
        }

        $db->table('qc_schedules')->where('id', $id)->delete();
        
        return redirect()->back()->with('success', 'QC Schedule berhasil dihapus.');
    }
}
