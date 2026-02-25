<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;

class ScheduleController extends BaseController
{
    /* =====================================================
     * PROCESS HELPERS
     * ===================================================== */

    private function findProcessId($db, array $codes = [], array $names = []): ?int
    {
        if (!$db->tableExists('production_processes')) return null;

        // by process_code
        if (!empty($codes) && $db->fieldExists('process_code', 'production_processes')) {
            foreach ($codes as $code) {
                $row = $db->table('production_processes')
                    ->select('id')
                    ->where('process_code', $code)
                    ->get()->getRowArray();
                if ($row && !empty($row['id'])) return (int)$row['id'];
            }
        }

        // by process_name exact/like
        if (!empty($names) && $db->fieldExists('process_name', 'production_processes')) {
            foreach ($names as $name) {
                $row = $db->table('production_processes')
                    ->select('id')
                    ->where('process_name', $name)
                    ->get()->getRowArray();
                if ($row && !empty($row['id'])) return (int)$row['id'];
            }
            foreach ($names as $name) {
                $row = $db->table('production_processes')
                    ->select('id')
                    ->like('process_name', $name)
                    ->get()->getRowArray();
                if ($row && !empty($row['id'])) return (int)$row['id'];
            }
        }

        return null;
    }

    private function getBaritoriProcessId($db): ?int
    {
        // tabel kamu: process_code = BT, process_name = BURRYTORY
        return $this->findProcessId($db, ['BT'], ['BURRYTORY', 'Burrytory', 'Baritori', 'BARITORI']);
    }

    private function getDieCastingProcessId($db): ?int
    {
        return $this->findProcessId($db, ['DC'], ['Die Casting', 'DIE CASTING', 'DIE CAST']);
    }

    /* =====================================================
     * MACHINE HELPERS (AUTO)
     * ===================================================== */

    private function getAutoBaritoriMachineId($db): int
    {
        if (!$db->tableExists('machines')) return 0;

        // Prioritas: mesin Baritori (kalau ada)
        $rows = $db->table('machines')->select('id')
            ->whereIn('production_line', ['Baritori', 'BARITORI', 'BURRYTORY', 'Burrytory'])
            ->get()->getResultArray();

        if (!empty($rows)) {
            $pick = $rows[array_rand($rows)];
            return (int)($pick['id'] ?? 0);
        }

        // Fallback: mesin Die Casting (biar selalu ada)
        $rows = $db->table('machines')->select('id')
            ->whereIn('production_line', ['Die Casting', 'DIE CASTING', 'DC'])
            ->get()->getResultArray();

        if (!empty($rows)) {
            $pick = $rows[array_rand($rows)];
            return (int)($pick['id'] ?? 0);
        }

        // Fallback terakhir: mesin pertama
        $row = $db->table('machines')->select('id')->limit(1)->get()->getRowArray();
        return (int)($row['id'] ?? 0);
    }

    /* =====================================================
     * COLUMN DETECT
     * ===================================================== */

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

    /* =====================================================
     * FLOW (prev/next robust, tidak harus sequence-1)
     * ===================================================== */

    private function getPrevNextProcessByFlow($db, int $productId, int $currentProcessId): array
    {
        if (!$db->tableExists('product_process_flows')) {
            return ['prev' => null, 'next' => null];
        }

        // Ambil semua flow aktif untuk product
        $rows = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();

        if (!$rows) return ['prev' => null, 'next' => null];

        $seq = array_map(fn($r) => (int)$r['process_id'], $rows);
        $idx = array_search($currentProcessId, $seq, true);
        if ($idx === false) return ['prev' => null, 'next' => null];

        return [
            'prev' => $seq[$idx - 1] ?? null,
            'next' => $seq[$idx + 1] ?? null,
        ];
    }

    /* =====================================================
     * ✅ AVAILABLE STOCK (robust)
     * - data kamu kadang stock 0 tapi transfer ada
     * - jadi ambil MAX dari beberapa kandidat pada last_date <= $date
     * ===================================================== */

/**
 * ✅ Ambil stock TERAKHIR berdasarkan kolom `stock` saja
 * - ambil row paling baru (date <= $date), urut date desc lalu id desc
 * - return COALESCE(stock,0)
 */
    private function getLatestStockOnly($db, string $date, int $processId, int $productId): int
    {
        if (!$db->tableExists('production_wip')) return 0;

        $wipDateCol = $this->detectWipDateColumn($db);
        $procCol    = $this->detectProcessColumn($db);
        $stockCol   = $this->detectStockColumn($db);
        if (!$stockCol) return 0;

        $row = $db->table('production_wip')
            ->select("COALESCE($stockCol,0) AS stock_val")
            ->where($procCol, $processId)
            ->where('product_id', $productId)
            ->where("$wipDateCol <=", $date)
            ->orderBy($wipDateCol, 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        return (int)($row['stock_val'] ?? 0);
    }

    /* =====================================================
     * SHIFT DC (diambil dari daily_schedules DC)
     * ===================================================== */

    private function getDcShifts($db, string $date): array
    {
        if (!$db->tableExists('shifts')) return [];

        $dcId = $this->getDieCastingProcessId($db);
        if (!$dcId || !$db->tableExists('daily_schedules')) {
            return $db->table('shifts')->get()->getResultArray();
        }

        $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
        if (!$dateCol || !$db->fieldExists('shift_id', 'daily_schedules') || !$db->fieldExists('process_id', 'daily_schedules')) {
            return $db->table('shifts')->get()->getResultArray();
        }

        $rows = $db->table('daily_schedules')
            ->select('shift_id')
            ->where('process_id', $dcId)
            ->where($dateCol, $date)
            ->groupBy('shift_id')
            ->get()->getResultArray();

        $ids = [];
        foreach ($rows as $r) {
            $sid = (int)($r['shift_id'] ?? 0);
            if ($sid > 0) $ids[] = $sid;
        }

        if (!$ids) return $db->table('shifts')->get()->getResultArray();
        return $db->table('shifts')->whereIn('id', $ids)->get()->getResultArray();
    }

    /* =====================================================
     * DAILY SCHEDULES UPSERT (Baritori)
     * ===================================================== */

    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;

        $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
        if (!$dateCol) return 0;

        $where = [
            $dateCol     => $date,
            'process_id' => $processId,
            'shift_id'   => $shiftId,
            'section'    => $section,
        ];

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

    private function insertDailyScheduleItem($db, int $dailyScheduleId, int $shiftId, int $machineId, int $productId, int $targetShift, int $targetHour): int
    {
        if (!$db->tableExists('daily_schedule_items')) return 0;

        // biar tidak dobel untuk product yang sama pada header yang sama
        $db->table('daily_schedule_items')
            ->where('daily_schedule_id', $dailyScheduleId)
            ->where('product_id', $productId)
            ->delete();

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

    /* =====================================================
     * INDEX
     * ===================================================== */

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) {
            return view('baritori/schedule/index', [
                'date'          => $date,
                'shifts'        => $this->getDcShifts($db, $date),
                'productsAvail' => [],
                'availableMap'  => [],
                'schedules'     => [],
                'processMap'    => [],
                'errorMsg'      => 'Process Baritori tidak ditemukan. Pastikan production_processes punya process_code = BT.',
            ]);
        }

        // process map label
        $processMap = [];
        if ($db->tableExists('production_processes')) {
            $prows = $db->table('production_processes')->select('id, process_name')->get()->getResultArray();
            foreach ($prows as $r) $processMap[(int)$r['id']] = (string)($r['process_name'] ?? '');
        }

        // cari semua product yang punya BT di flow
        $availableMap = [];
        $productsAvail = [];

        if ($db->tableExists('product_process_flows') && $db->tableExists('products') && $db->tableExists('production_wip')) {
            $rows = $db->table('product_process_flows')
                ->select('product_id')
                ->where('process_id', $baritoriId)
                ->where('is_active', 1)
                ->groupBy('product_id')
                ->get()->getResultArray();

            $idsAvail = [];
            foreach ($rows as $r) {
                $pid = (int)($r['product_id'] ?? 0);
                if ($pid <= 0) continue;

                $flow = $this->getPrevNextProcessByFlow($db, $pid, $baritoriId);
                $prevId = (int)($flow['prev'] ?? 0);
                $nextId = (int)($flow['next'] ?? 0);

                if ($prevId <= 0) continue;

                $av = $this->getLatestStockOnly($db, $date, $prevId, $pid);


                $availableMap[$pid] = [
                    'available'       => $av,
                    'prev_process_id' => $prevId,
                    'next_process_id' => $nextId > 0 ? $nextId : null,
                ];

                if ($av > 0) $idsAvail[] = $pid;
            }

            if (!empty($idsAvail)) {
                $q = $db->table('products')->select('id, part_no, part_name');
                if ($db->fieldExists('is_active', 'products')) $q->where('is_active', 1);

                $productsAvail = $q->whereIn('id', $idsAvail)
                    ->orderBy('part_no', 'ASC')
                    ->get()->getResultArray();
            }
        }

        // list schedule dari daily_schedule_items (bukan production_plans)
        $schedules = [];
        if ($db->tableExists('daily_schedules') && $db->tableExists('daily_schedule_items')) {
            $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;

            if ($dateCol) {
                $schedules = $db->table('daily_schedule_items dsi')
                    ->select('
                        ds.id as daily_schedule_id,
                        ds.' . $dateCol . ' as schedule_date,
                        ds.shift_id, s.shift_name,
                        dsi.id as item_id,
                        dsi.product_id, p.part_no, p.part_name,
                        dsi.target_per_shift, dsi.target_per_hour
                    ')
                    ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id', 'inner')
                    ->join('shifts s', 's.id = ds.shift_id', 'left')
                    ->join('products p', 'p.id = dsi.product_id', 'left')
                    ->where('ds.process_id', $baritoriId)
                    ->where('ds.section', 'Baritori')
                    ->where('ds.' . $dateCol, $date)
                    ->orderBy('dsi.id', 'DESC')
                    ->get()->getResultArray();
            }
        }

        return view('baritori/schedule/index', [
            'date'          => $date,
            'shifts'        => $this->getDcShifts($db, $date),
            'productsAvail' => $productsAvail,
            'availableMap'  => $availableMap,
            'schedules'     => $schedules,
            'processMap'    => $processMap,
            'errorMsg'      => null,
        ]);
    }

    /* =====================================================
     * STORE (TANPA production_plans)
     * - insert daily_schedules + daily_schedule_items
     * - insert production_wip source_table = daily_schedule_items
     * ===================================================== */

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

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) {
            return redirect()->back()->with('error', 'Process Baritori tidak ditemukan (process_code BT).');
        }

        if (!$db->tableExists('production_wip')) {
            return redirect()->back()->with('error', 'Tabel production_wip tidak ditemukan.');
        }

        $stockCol = $this->detectStockColumn($db);
        if (!$stockCol) {
            return redirect()->back()->with('error', 'Kolom stock tidak ditemukan di production_wip.');
        }

        // flow
        $flow = $this->getPrevNextProcessByFlow($db, $productId, $baritoriId);
        $prevId = (int)($flow['prev'] ?? 0);
        $nextId = (int)($flow['next'] ?? 0);

        if ($prevId <= 0) {
            return redirect()->back()->with('error', 'Flow sebelumnya untuk Baritori tidak ditemukan.');
        }

        // ✅ available dari proses sebelumnya (robust)
        $availablePrev = $this->getLatestStockOnly($db, $date, $prevId, $productId);



        if ($availablePrev <= 0) {
            return redirect()->back()->with('error', 'Stock proses sebelumnya kosong (0). Tidak bisa membuat schedule.');
        }
        if ($qty > $availablePrev) {
            return redirect()->back()->with('error', "Tidak bisa melebihi stock proses sebelumnya. Available: {$availablePrev}");
        }

        // auto machine_id untuk daily_schedule_items
        $machineId = $this->getAutoBaritoriMachineId($db);
        if ($machineId <= 0) {
            return redirect()->back()->with('error', 'Tidak ada mesin tersedia untuk mengisi machine_id pada daily_schedule_items.');
        }

        $wipDateCol = $this->detectWipDateColumn($db);
        $transferCol = $this->detectTransferColumn($db);
        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            // 1) upsert header daily_schedules
            $dailyId = $this->upsertDailyScheduleHeader($db, $date, $baritoriId, $shiftId, 'Baritori');
            if ($dailyId <= 0) throw new \Exception('Gagal membuat daily_schedules Baritori.');

            // 2) insert item daily_schedule_items
            $itemId = $this->insertDailyScheduleItem($db, $dailyId, $shiftId, $machineId, $productId, $qty, $targetHr);
            if ($itemId <= 0) throw new \Exception('Gagal membuat daily_schedule_items Baritori.');

            // 3) WIP: prev OUT (stock berkurang)
            $prevAfter = max(0, $availablePrev - $qty);

            $prevWip = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevId,
                'to_process_id'   => $prevId,
                'qty'             => $qty,
                'qty_in'          => 0,
                'qty_out'         => $qty,
                $stockCol         => $prevAfter,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $itemId,
                'status'          => 'DONE',
                'created_at'      => $now,
            ];
            if ($transferCol) $prevWip[$transferCol] = $qty;

            $prevWip = $this->onlyExistingColumns($db, 'production_wip', $prevWip);
            $db->table('production_wip')->insert($prevWip);

            // 4) WIP: Baritori IN (qty_in & stock langsung terisi)
            $barBefore = $this->getLatestStockOnly($db, $date, $baritoriId, $productId);

            $barAfter  = $barBefore + $qty;

            $barIn = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevId,
                'to_process_id'   => $baritoriId,
                'qty'             => $qty,
                'qty_in'          => $qty,
                'qty_out'         => 0,
                $stockCol         => $barAfter,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $itemId,
                'status'          => 'DONE',
                'created_at'      => $now,
            ];
            if ($transferCol) $barIn[$transferCol] = $qty;

            $barIn = $this->onlyExistingColumns($db, 'production_wip', $barIn);
            $db->table('production_wip')->insert($barIn);

            // 5) opsional send next
            if ($sendNext === 1) {
                if ($nextId <= 0) throw new \Exception('Next process untuk Baritori tidak ditemukan pada flow.');

                // Baritori OUT
                $barAfter2 = max(0, $barAfter - $qty);

                $barOut = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $baritoriId,
                    'to_process_id'   => $baritoriId,
                    'qty'             => $qty,
                    'qty_in'          => 0,
                    'qty_out'         => $qty,
                    $stockCol         => $barAfter2,
                    'source_table'    => 'daily_schedule_items',
                    'source_id'       => $itemId,
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];
                if ($transferCol) $barOut[$transferCol] = $qty;

                $barOut = $this->onlyExistingColumns($db, 'production_wip', $barOut);
                $db->table('production_wip')->insert($barOut);

                // Next IN
                $nextBefore = $this->getLatestStockOnly($db, $date, $nextId, $productId);

                $nextAfter  = $nextBefore + $qty;

                $nextIn = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $baritoriId,
                    'to_process_id'   => $nextId,
                    'qty'             => $qty,
                    'qty_in'          => $qty,
                    'qty_out'         => 0,
                    $stockCol         => $nextAfter,
                    'source_table'    => 'daily_schedule_items',
                    'source_id'       => $itemId,
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];
                if ($transferCol) $nextIn[$transferCol] = $qty;

                $nextIn = $this->onlyExistingColumns($db, 'production_wip', $nextIn);
                $db->table('production_wip')->insert($nextIn);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();

            $msg = $sendNext
                ? 'Schedule Baritori tersimpan ke Daily Schedules & Items, stock prev berkurang, dan sudah dikirim ke next process.'
                : 'Schedule Baritori tersimpan ke Daily Schedules & Items, stock prev berkurang dan stock Baritori bertambah.';

            return redirect()->back()->with('success', $msg);
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Gagal simpan Baritori: ' . $e->getMessage());
        }
    }
}
