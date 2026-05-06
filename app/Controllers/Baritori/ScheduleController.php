<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;

class ScheduleController extends BaseController
{
    private function findProcessId($db, array $codes = [], array $names = []): ?int
    {
        if (!$db->tableExists('production_processes')) return null;

        if (!empty($codes) && $db->fieldExists('process_code', 'production_processes')) {
            foreach ($codes as $code) {
                $row = $db->table('production_processes')->select('id')->where('process_code', $code)->get()->getRowArray();
                if ($row && !empty($row['id'])) return (int)$row['id'];
            }
        }

        if (!empty($names) && $db->fieldExists('process_name', 'production_processes')) {
            foreach ($names as $name) {
                $row = $db->table('production_processes')->select('id')->where('process_name', $name)->get()->getRowArray();
                if ($row && !empty($row['id'])) return (int)$row['id'];
            }
            foreach ($names as $name) {
                $row = $db->table('production_processes')->select('id')->like('process_name', $name)->get()->getRowArray();
                if ($row && !empty($row['id'])) return (int)$row['id'];
            }
        }
        return null;
    }

    private function getBaritoriProcessId($db): ?int
    {
        return $this->findProcessId($db, ['BT'], ['BURRYTORY', 'Burrytory', 'Baritori', 'BARITORI']);
    }

    private function getDieCastingProcessId($db): ?int
    {
        return $this->findProcessId($db, ['DC'], ['Die Casting', 'DIE CASTING', 'DIE CAST']);
    }

    private function getAutoBaritoriMachineId($db): int
    {
        if (!$db->tableExists('machines')) return 0;
        $rows = $db->table('machines')->select('id')->whereIn('production_line', ['Baritori', 'BARITORI', 'BURRYTORY', 'Burrytory'])->get()->getResultArray();
        if (!empty($rows)) return (int)($rows[array_rand($rows)]['id'] ?? 0);
        $rows = $db->table('machines')->select('id')->whereIn('production_line', ['Die Casting', 'DIE CASTING', 'DC'])->get()->getResultArray();
        if (!empty($rows)) return (int)($rows[array_rand($rows)]['id'] ?? 0);
        $row = $db->table('machines')->select('id')->limit(1)->get()->getRowArray();
        return (int)($row['id'] ?? 0);
    }

    private function detectWipDateColumn($db): string { return $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date'; }
    private function detectProcessColumn($db): string { return $db->fieldExists('to_process_id', 'production_wip') ? 'to_process_id' : 'process_id'; }
    private function detectStockColumn($db): ?string { return $db->fieldExists('stock', 'production_wip') ? 'stock' : null; }
    private function detectTransferColumn($db): ?string { return $db->fieldExists('transfer', 'production_wip') ? 'transfer' : null; }

    private function onlyExistingColumns($db, string $table, array $data): array
    {
        $clean = [];
        foreach ($data as $k => $v) if ($db->fieldExists($k, $table)) $clean[$k] = $v;
        return $clean;
    }

    private function getPrevNextProcessByFlow($db, int $productId, int $currentProcessId): array
    {
        if (!$db->tableExists('product_process_flows')) return ['prev' => null, 'next' => null];
        $rows = $db->table('product_process_flows')->select('process_id, sequence')->where('product_id', $productId)->where('is_active', 1)->orderBy('sequence', 'ASC')->get()->getResultArray();
        if (!$rows) return ['prev' => null, 'next' => null];
        $seq = array_map(fn($r) => (int)$r['process_id'], $rows);
        $idx = array_search($currentProcessId, $seq, true);
        if ($idx === false) return ['prev' => null, 'next' => null];
        return ['prev' => $seq[$idx - 1] ?? null, 'next' => $seq[$idx + 1] ?? null];
    }

    /**
     * Mengambil nilai stock dan transfer terakhir pada WIP
     */
    private function getLatestWip($db, string $date, int $processId, int $productId): array
    {
        if (!$db->tableExists('production_wip')) return ['stock' => 0, 'transfer' => 0];
        
        $wipDateCol = $this->detectWipDateColumn($db);
        $procCol    = $this->detectProcessColumn($db);
        $stockCol   = $this->detectStockColumn($db);
        $transCol   = $this->detectTransferColumn($db);
        
        if (!$stockCol) return ['stock' => 0, 'transfer' => 0];

        $selects = ["COALESCE($stockCol,0) AS stock_val"];
        if ($transCol) $selects[] = "COALESCE($transCol,0) AS trans_val";

        $row = $db->table('production_wip')
                  ->select(implode(', ', $selects))
                  ->where($procCol, $processId)
                  ->where('product_id', $productId)
                  ->where("$wipDateCol <=", $date)
                  ->orderBy($wipDateCol, 'DESC')
                  ->orderBy('id', 'DESC')
                  ->limit(1)
                  ->get()->getRowArray();

        return [
            'stock'    => (int)($row['stock_val'] ?? 0),
            'transfer' => (int)($row['trans_val'] ?? 0)
        ];
    }

    private function getBaritoriShifts($db, string $date): array
    {
        if (!$db->tableExists('shifts')) return [];
        $rows = $db->table('shifts')
            ->where('is_active', 1)
            ->groupStart()
                ->like('shift_name', 'BT')
                ->orLike('shift_name', 'Baritori')
            ->groupEnd()
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();
        
        return $rows ?: $db->table('shifts')->where('is_active', 1)->get()->getResultArray();
    }

    private function getVendors($db): array
    {
        if (!$db->tableExists('vendors')) return [];
        $q = $db->table('vendors')->select('id, vendor_code, vendor_code_app, vendor_name')->orderBy('vendor_name', 'ASC');
        if ($db->fieldExists('is_active', 'vendors')) $q->where('is_active', 1);
        return $q->get()->getResultArray();
    }

    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;
        $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
        if (!$dateCol) return 0;

        $where = [$dateCol => $date, 'process_id' => $processId, 'shift_id' => $shiftId, 'section' => $section];
        $exist = $db->table('daily_schedules')->where($where)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');

        if ($exist) {
            $upd = ['is_completed' => 0];
            if ($db->fieldExists('updated_at', 'daily_schedules')) $upd['updated_at'] = $now;
            $db->table('daily_schedules')->where('id', (int)$exist['id'])->update($upd);
            return (int)$exist['id'];
        }

        $payload = $where + ['is_completed' => 0];
        if ($db->fieldExists('created_at', 'daily_schedules')) $payload['created_at'] = $now;
        if ($db->fieldExists('updated_at', 'daily_schedules')) $payload['updated_at'] = $now;

        $db->table('daily_schedules')->insert($payload);
        return (int)$db->insertID();
    }

    private function insertDailyScheduleItem($db, int $dailyScheduleId, int $shiftId, int $machineId, int $productId, int $targetShift, int $targetHour, int $vendorId): int
    {
        if (!$db->tableExists('daily_schedule_items')) return 0;
        $db->table('daily_schedule_items')->where('daily_schedule_id', $dailyScheduleId)->where('product_id', $productId)->delete();
        $payload = [
            'daily_schedule_id' => $dailyScheduleId,
            'shift_id'          => $shiftId,
            'machine_id'        => $machineId,
            'product_id'        => $productId,
            'cycle_time'        => 0,
            'cavity'            => 0,
            'target_per_hour'   => $targetHour,
            'target_per_shift'  => $targetShift,
            'is_selected'       => 1,
        ];
        if ($db->fieldExists('vendor_id', 'daily_schedule_items')) $payload['vendor_id'] = $vendorId;
        $payload = $this->onlyExistingColumns($db, 'daily_schedule_items', $payload);
        $db->table('daily_schedule_items')->insert($payload);
        return (int)$db->insertID();
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) {
            return view('baritori/schedule/index', [
                'date' => $date, 'shifts' => [], 'productsAvail' => [], 'availableMap' => [], 'schedules' => [], 'processMap' => [], 'vendors' => [],
                'errorMsg' => 'Process Baritori tidak ditemukan.'
            ]);
        }

        $processMap = [];
        if ($db->tableExists('production_processes')) {
            foreach ($db->table('production_processes')->select('id, process_name')->get()->getResultArray() as $r) {
                $processMap[(int)$r['id']] = (string)$r['process_name'];
            }
        }

        $availableMap  = [];
        $productsAvail = [];

        if ($db->tableExists('product_process_flows') && $db->tableExists('products') && $db->tableExists('production_wip')) {
            $rows = $db->table('product_process_flows')->select('product_id')->where('process_id', $baritoriId)->where('is_active', 1)->groupBy('product_id')->get()->getResultArray();
            $idsAvail = [];
            foreach ($rows as $r) {
                $pid = (int)($r['product_id'] ?? 0);
                if ($pid <= 0) continue;

                $flow   = $this->getPrevNextProcessByFlow($db, $pid, $baritoriId);
                $prevId = (int)($flow['prev'] ?? 0);
                $nextId = (int)($flow['next'] ?? 0);
                if ($prevId <= 0) continue;

                $wipStatus = $this->getLatestWip($db, $date, $prevId, $pid);
                $av = $wipStatus['stock'];

                $availableMap[$pid] = ['available' => $av, 'prev_process_id' => $prevId, 'next_process_id' => $nextId > 0 ? $nextId : null];
                $idsAvail[] = $pid;
            }

            if ($idsAvail) {
                $q = $db->table('products')->select('id, part_no, part_name');
                if ($db->fieldExists('is_active', 'products')) $q->where('is_active', 1);
                $productsAvail = $q->whereIn('id', $idsAvail)->orderBy('part_no', 'ASC')->get()->getResultArray();
            }
        }

        $schedules = [];
        if ($db->tableExists('daily_schedules') && $db->tableExists('daily_schedule_items')) {
            $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
            if ($dateCol) {
                $query = $db->table('daily_schedule_items dsi')
                    ->select("ds.id as daily_schedule_id, ds.$dateCol as schedule_date, ds.shift_id, s.shift_name, dsi.id as item_id, dsi.product_id, p.part_no, p.part_name, dsi.target_per_shift, dsi.target_per_hour");
                if ($db->fieldExists('vendor_id', 'daily_schedule_items')) {
                    $query->select('dsi.vendor_id, v.vendor_name')->join('vendors v', 'v.id = dsi.vendor_id', 'left');
                }
                
                $schedules = $query->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id', 'inner')
                    ->join('shifts s', 's.id = ds.shift_id', 'left')
                    ->join('products p', 'p.id = dsi.product_id', 'left')
                    ->where('ds.process_id', $baritoriId)
                    ->where('ds.section', 'Baritori')
                    ->where("ds.$dateCol", $date)
                    ->orderBy('dsi.id', 'DESC')
                    ->get()->getResultArray();
            }
        }

        return view('baritori/schedule/index', [
            'date'          => $date,
            'shifts'        => $this->getBaritoriShifts($db, $date),
            'productsAvail' => $productsAvail,
            'availableMap'  => $availableMap,
            'schedules'     => $schedules,
            'processMap'    => $processMap,
            'vendors'       => $this->getVendors($db),
            'errorMsg'      => null,
        ]);
    }

    public function store()
    {
        $db = db_connect();

        $date      = (string)$this->request->getPost('date');
        $shiftId   = (int)$this->request->getPost('shift_id');
        $productId = (int)$this->request->getPost('product_id');
        $vendorId  = (int)$this->request->getPost('vendor_id'); // VENDOR
        $qty       = (int)$this->request->getPost('target_shift');
        $targetHr  = (int)($this->request->getPost('target_hour') ?? 0);

        if ($shiftId <= 0) return redirect()->back()->with('error', 'Shift wajib dipilih.');
        if ($vendorId <= 0 && $vendorId !== -1) return redirect()->back()->with('error', 'Vendor wajib dipilih.');
        if ($productId <= 0) return redirect()->back()->with('error', 'Product wajib dipilih.');
        if ($qty <= 0) return redirect()->back()->with('error', 'Qty harus > 0.');

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) return redirect()->back()->with('error', 'Process Baritori tidak ditemukan.');

        $machineId   = $this->getAutoBaritoriMachineId($db);

        $db->transBegin();
        try {
            $dailyId = $this->upsertDailyScheduleHeader($db, $date, $baritoriId, $shiftId, 'Baritori');
            if ($dailyId <= 0) throw new \Exception('Gagal membuat daily_schedules.');

            $itemId = $this->insertDailyScheduleItem($db, $dailyId, $shiftId, $machineId, $productId, $qty, $targetHr, $vendorId);
            if ($itemId <= 0) throw new \Exception('Gagal membuat daily_schedule_items.');

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with('success', 'Schedule Baritori tersimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Gagal simpan: ' . $e->getMessage());
        }
    }
}