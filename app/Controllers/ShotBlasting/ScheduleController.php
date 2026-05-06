<?php

namespace App\Controllers\ShotBlasting;

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

    private function getShotBlastProcessId($db): ?int
    {
        return $this->findProcessId($db, ['SB'], ['SAND BLASTING', 'Sand Blasting', 'SHOT BLASTING', 'Shot Blasting', 'Shot Blast']);
    }

    private function getDieCastingProcessId($db): ?int
    {
        return $this->findProcessId($db, ['DC'], ['Die Casting', 'DIE CASTING', 'DIE CAST']);
    }

    private function getAutoShotBlastMachineId($db): int
    {
        if (!$db->tableExists('machines')) return 0;

        $rows = $db->table('machines')->select('id')->whereIn('production_line', ['Shot Blast', 'SHOT BLAST', 'Sand Blasting', 'SAND BLASTING', 'SB'])->get()->getResultArray();
        if (!empty($rows)) return (int)($rows[array_rand($rows)]['id'] ?? 0);

        $rows = $db->table('machines')->select('id')->whereIn('production_line', ['Die Casting', 'DIE CASTING', 'DC'])->get()->getResultArray();
        if (!empty($rows)) return (int)($rows[array_rand($rows)]['id'] ?? 0);

        $row = $db->table('machines')->select('id')->limit(1)->get()->getRowArray();
        return (int)($row['id'] ?? 0);
    }

    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        return 'production_date';
    }

    private function detectProcessColumn($db): string
    {
        if ($db->fieldExists('to_process_id', 'production_wip')) return 'to_process_id';
        if ($db->fieldExists('process_id', 'production_wip'))    return 'process_id';
        return 'to_process_id';
    }

    private function detectStockColumn($db): ?string
    {
        foreach (['stock', 'stock_qty', 'qty_stock'] as $c) {
            if ($db->fieldExists($c, 'production_wip')) return $c;
        }
        return null;
    }

    private function detectTransferColumn($db): ?string
    {
        foreach (['transfer', 'qty_transfer', 'buffer', 'buffer_qty'] as $c) {
            if ($db->fieldExists($c, 'production_wip')) return $c;
        }
        return null;
    }

    private function onlyExistingColumns($db, string $table, array $data): array
    {
        $clean = [];
        foreach ($data as $k => $v) {
            if ($db->fieldExists($k, $table)) $clean[$k] = $v;
        }
        return $clean;
    }

    private function getPrevNextProcessByFlow($db, int $productId, int $currentProcessId): array
    {
        if (!$db->tableExists('product_process_flows')) return ['prev' => null, 'next' => null];

        $rows = $db->table('product_process_flows')->select('process_id, sequence')
            ->where('product_id', $productId)->where('is_active', 1)->orderBy('sequence', 'ASC')->get()->getResultArray();

        if (!$rows) return ['prev' => null, 'next' => null];

        $seq = array_map(fn($r) => (int)$r['process_id'], $rows);
        $idx = array_search($currentProcessId, $seq, true);
        if ($idx === false) return ['prev' => null, 'next' => null];

        return [
            'prev' => $seq[$idx - 1] ?? null,
            'next' => $seq[$idx + 1] ?? null,
        ];
    }

    private function getLatestStockOnly($db, string $date, int $processId, int $productId): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        $wipDateCol = $this->detectWipDateColumn($db);
        $procCol    = $this->detectProcessColumn($db);
        $stockCol   = $this->detectStockColumn($db);
        if (!$stockCol) return 0;

        $row = $db->table('production_wip')->select("COALESCE($stockCol,0) AS stock_val")
            ->where($procCol, $processId)->where('product_id', $productId)->where("$wipDateCol <=", $date)
            ->orderBy($wipDateCol, 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

        return (int)($row['stock_val'] ?? 0);
    }

    private function getShotBlastShifts($db, string $date): array
    {
        if (!$db->tableExists('shifts')) return [];
        $rows = $db->table('shifts')
            ->where('is_active', 1)
            ->groupStart()
                ->like('shift_name', 'SB')
                ->orLike('shift_name', 'Shot Blast')
                ->orLike('shift_name', 'Sand Blast')
            ->groupEnd()
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        return $rows ?: $db->table('shifts')->where('is_active', 1)->get()->getResultArray();
    }

    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;
        $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
        if (!$dateCol) return 0;

        $where = [$dateCol => $date, 'process_id' => $processId, 'shift_id' => $shiftId, 'section' => $section];
        $exist = $db->table('daily_schedules')->where($where)->get()->getRowArray();
        $now   = date('Y-m-d H:i:s');

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

    private function insertDailyScheduleItem($db, int $dailyScheduleId, int $shiftId, int $machineId, int $productId, int $targetShift, int $targetHour): int
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

        $payload = $this->onlyExistingColumns($db, 'daily_schedule_items', $payload);
        $db->table('daily_schedule_items')->insert($payload);
        return (int)$db->insertID();
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $sbId = $this->getShotBlastProcessId($db);
        if (!$sbId) {
            return view('shot_blasting/schedule/index', [
                'date' => $date, 'shifts' => [], 'productsAvail' => [], 'availableMap' => [], 'schedules' => [], 'processMap' => [],
                'errorMsg' => 'Process Shot Blasting tidak ditemukan.'
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
            $rows = $db->table('product_process_flows')->select('product_id')->where('process_id', $sbId)->where('is_active', 1)->groupBy('product_id')->get()->getResultArray();
            $idsAvail = [];
            foreach ($rows as $r) {
                $pid = (int)($r['product_id'] ?? 0);
                if ($pid <= 0) continue;

                $flow   = $this->getPrevNextProcessByFlow($db, $pid, $sbId);
                $prevId = (int)($flow['prev'] ?? 0);
                $nextId = (int)($flow['next'] ?? 0);
                if ($prevId <= 0) continue;

                $av = $this->getLatestStockOnly($db, $date, $prevId, $pid);

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
                
                $schedules = $query->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id', 'inner')
                    ->join('shifts s', 's.id = ds.shift_id', 'left')
                    ->join('products p', 'p.id = dsi.product_id', 'left')
                    ->where('ds.process_id', $sbId)
                    ->where('ds.section', 'Shot Blasting')
                    ->where("ds.$dateCol", $date)
                    ->orderBy('dsi.id', 'DESC')
                    ->get()->getResultArray();
            }
        }

        return view('shot_blasting/schedule/index', [
            'date'          => $date,
            'shifts'        => $this->getShotBlastShifts($db, $date),
            'productsAvail' => $productsAvail,
            'availableMap'  => $availableMap,
            'schedules'     => $schedules,
            'processMap'    => $processMap,
            'errorMsg'      => null,
        ]);
    }

    public function store()
    {
        $db = db_connect();

        $date      = (string)$this->request->getPost('date');
        $shiftId   = (int)$this->request->getPost('shift_id');
        $productId = (int)$this->request->getPost('product_id');
        $qty       = (int)$this->request->getPost('target_shift');
        $targetHr  = (int)($this->request->getPost('target_hour') ?? 0);
        $sendNext  = (int)($this->request->getPost('send_next') ?? 0);

        if ($shiftId <= 0) return redirect()->back()->with('error', 'Shift wajib dipilih.');
        if ($productId <= 0) return redirect()->back()->with('error', 'Product wajib dipilih.');
        if ($qty <= 0) return redirect()->back()->with('error', 'Qty harus > 0.');

        $sbId = $this->getShotBlastProcessId($db);
        if (!$sbId) return redirect()->back()->with('error', 'Process Shot Blasting tidak ditemukan (process_code SB).');

        $machineId = $this->getAutoShotBlastMachineId($db);
        
        $wipDateCol  = $this->detectWipDateColumn($db);
        $transferCol = $this->detectTransferColumn($db);
        $now         = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            $dailyId = $this->upsertDailyScheduleHeader($db, $date, $sbId, $shiftId, 'Shot Blasting');
            if ($dailyId <= 0) throw new \Exception('Gagal membuat daily_schedules.');

            $itemId = $this->insertDailyScheduleItem($db, $dailyId, $shiftId, $machineId, $productId, $qty, $targetHr);
            if ($itemId <= 0) throw new \Exception('Gagal membuat daily_schedule_items.');

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with('success', 'Schedule Shot Blasting berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Gagal simpan: ' . $e->getMessage());
        }
    }
}