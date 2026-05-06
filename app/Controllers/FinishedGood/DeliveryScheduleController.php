<?php

namespace App\Controllers\FinishedGood;

use App\Controllers\BaseController;

class DeliveryScheduleController extends BaseController
{
    private function ensureTables($db): void
    {
        if (!$db->tableExists('fg_delivery_schedules')) {
            $db->query("CREATE TABLE fg_delivery_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                schedule_date DATE NOT NULL,
                delivery_type VARCHAR(50) DEFAULT 'biasa',
                product_id INT NOT NULL,
                customer_id INT NOT NULL,
                rit_1 INT DEFAULT 0,
                rit_2 INT DEFAULT 0,
                rit_3 INT DEFAULT 0,
                rit_4 INT DEFAULT 0,
                rit_5 INT DEFAULT 0,
                target_per_shift INT DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
    }

    private function getFgProcessId($db): int
    {
        $names = ['FINISHED GOOD', 'Finished Good', 'FG'];
        foreach ($names as $n) {
            $r = $db->table('production_processes')->select('id')->where('process_name', $n)->get()->getRowArray();
            if ($r) return (int)$r['id'];
        }
        return 0;
    }

    private function getFgStockMap($db, string $date, int $fgProcessId): array
    {
        if (!$db->tableExists('production_wip') || $fgProcessId <= 0) return [];

        $wipDateCol = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';

        $rows = $db->table('production_wip')
            ->select("product_id, SUM(stock) AS total_stock")
            ->where('to_process_id', $fgProcessId)
            ->where('stock >', 0)
            ->where("status !=", 'DONE')
            ->where("$wipDateCol <=", $date)
            ->groupBy('product_id')
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['product_id']] = (int)$r['total_stock'];
        }
        return $map;
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $this->ensureTables($db);

        $fgProcessId = $this->getFgProcessId($db);
        $availableMap = $this->getFgStockMap($db, $date, $fgProcessId);

        $customers = $db->tableExists('customers')
            ? $db->table('customers')->orderBy('customer_name', 'ASC')->get()->getResultArray()
            : [];

        $products = $db->tableExists('products')
            ? $db->table('products')->where('is_active', 1)->orderBy('part_no', 'ASC')->get()->getResultArray()
            : [];

        // Build stock lookup (even if stock is 0)
        $stockMap = [];
        foreach ($products as &$p) {
            $stk = $availableMap[$p['id']] ?? 0;
            $stockMap[$p['id']] = $stk;
            $p['stock'] = $stk;
        }
        unset($p);

        // Get fixed definitions
        $fixedDefs = \App\Controllers\FinishedGood\DeliveryControlBoardController::getFixedDataPublic();
        
        $productByName = [];
        foreach ($products as $p) {
            $nameKey = preg_replace('/[^a-z0-9]/', '', strtolower($p['part_name']));
            $productByName[$nameKey] = $p;
        }

        $customerByName = [];
        foreach ($customers as $c) {
            $cNameKey = preg_replace('/[^a-z0-9]/', '', strtolower($c['customer_name']));
            $customerByName[$cNameKey] = $c;
        }

        // Build fixed rows configuration
        $fixedData = [];
        $fixedProductIds = [];
        foreach ($fixedDefs as $fd) {
            $cNameKey = preg_replace('/[^a-z0-9]/', '', strtolower($fd['customer_name']));
            $cust = $customerByName[$cNameKey] ?? null;

            $group = [
                'customer_name' => $fd['customer_name'],
                'customer_id'   => $cust ? $cust['id'] : null,
                'parts'         => []
            ];

            foreach ($fd['parts'] as $partName) {
                $pNameKey = preg_replace('/[^a-z0-9]/', '', strtolower($partName));
                $prod = $productByName[$pNameKey] ?? null;
                if ($prod) {
                    $prod['fixed_part_name'] = $partName;
                    $group['parts'][] = $prod;
                    $fixedProductIds[] = (int)$prod['id'];
                }
            }
            $fixedData[] = $group;
        }



        $schedules = $db->table('fg_delivery_schedules')
            ->where('schedule_date', $date)
            ->get()
            ->getResultArray();

        // Separate schedules for fixed vs custom rows (already stored)
        $scheduleMap = [];
        $customSchedules = [];
        foreach ($schedules as $s) {
            $pid = (int)$s['product_id'];
            if (in_array($pid, $fixedProductIds)) {
                $scheduleMap[$pid] = $s;
            } else {
                $customSchedules[] = $s;
            }
        }

        return view('finished_good/delivery_schedule/index', [
            'date' => $date,
            'customers' => $customers,
            'products' => $products, // All products to choose from custom select
            'stockMap' => $stockMap,
            'fixedData' => $fixedData,
            'scheduleMap' => $scheduleMap,
            'customSchedules' => $customSchedules,
            'fixedProductIds' => $fixedProductIds
        ]);
    }

    public function store()
    {
        $db = db_connect();
        $this->ensureTables($db);
        $date = $this->request->getPost('delivery_date') ?: date('Y-m-d');
        $items = $this->request->getPost('items') ?? [];
        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            // Delete existing schedules for this date
            $db->table('fg_delivery_schedules')->where('schedule_date', $date)->delete();

            $inserts = [];
            foreach ($items as $item) {
                if (empty($item['product_id']) || empty($item['customer_id'])) continue;

                $target = (int)($item['target_per_shift'] ?? 0);
                if ($target <= 0) continue;

                $inserts[] = [
                    'schedule_date' => $date,
                    'delivery_type' => $item['delivery_type'] ?? 'biasa',
                    'product_id' => (int)$item['product_id'],
                    'customer_id' => (int)$item['customer_id'],
                    'rit_1' => (int)($item['rit_1'] ?? 0),
                    'rit_2' => (int)($item['rit_2'] ?? 0),
                    'rit_3' => (int)($item['rit_3'] ?? 0),
                    'rit_4' => (int)($item['rit_4'] ?? 0),
                    'rit_5' => (int)($item['rit_5'] ?? 0),
                    'target_per_shift' => $target,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            if (!empty($inserts)) {
                $db->table('fg_delivery_schedules')->insertBatch($inserts);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error saat proses simpan schedule.');
            $db->transCommit();
            return redirect()->back()->with('success', 'Delivery Schedule berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
