<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;
use App\Models\ProductModel;

class DeliveryController extends BaseController
{
    /* =====================================================
     * HELPERS: process + wip
     * ===================================================== */

    private function findProcessId($db, array $codes = [], array $names = []): ?int
    {
        if (!$db->tableExists('production_processes')) return null;

        if (!empty($codes) && $db->fieldExists('process_code', 'production_processes')) {
            foreach ($codes as $code) {
                $row = $db->table('production_processes')
                    ->select('id')
                    ->where('process_code', $code)
                    ->get()->getRowArray();
                if ($row && !empty($row['id'])) return (int)$row['id'];
            }
        }

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
        return $this->findProcessId($db, ['BT'], ['BURRYTORY', 'Burrytory', 'BARITORI', 'Baritori']);
    }

    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        return 'production_date';
    }

    private function detectWipProcessColumn($db): string
    {
        if ($db->fieldExists('to_process_id', 'production_wip')) return 'to_process_id';
        if ($db->fieldExists('process_id', 'production_wip'))    return 'process_id';
        return 'to_process_id';
    }

    private function detectWipStockColumn($db): ?string
    {
        foreach (['stock', 'stock_qty', 'qty_stock'] as $c) {
            if ($db->fieldExists($c, 'production_wip')) return $c;
        }
        return null;
    }

    private function detectWipTransferColumn($db): ?string
    {
        foreach (['transfer', 'qty_transfer', 'transfer_qty', 'buffer', 'buffer_qty'] as $c) {
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

    private function getLatestSnapshot($db, string $date, int $processId, int $productId): array
    {
        if (!$db->tableExists('production_wip')) {
            return ['id' => 0, 'stock' => 0, 'transfer' => 0, 'status' => null];
        }

        $wipDateCol  = $this->detectWipDateColumn($db);
        $procCol     = $this->detectWipProcessColumn($db);
        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);

        if (!$stockCol) {
            return ['id' => 0, 'stock' => 0, 'transfer' => 0, 'status' => null];
        }

        $hasStatus = $db->fieldExists('status', 'production_wip');

        $select = "id, COALESCE($stockCol,0) AS stock_val";
        if ($transferCol) $select .= ", COALESCE($transferCol,0) AS transfer_val";
        if ($hasStatus)   $select .= ", status";

        // 1) WAITING/OPEN hari ini
        if ($hasStatus) {
            $row = $db->table('production_wip')
                ->select($select)
                ->where($procCol, $processId)
                ->where('product_id', $productId)
                ->where($wipDateCol, $date)
                ->whereIn('status', ['WAITING', 'OPEN'])
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()->getRowArray();

            if ($row) {
                return [
                    'id'       => (int)($row['id'] ?? 0),
                    'stock'    => (int)($row['stock_val'] ?? 0),
                    'transfer' => (int)($row['transfer_val'] ?? 0),
                    'status'   => $row['status'] ?? null,
                ];
            }
        }

        // 2) latest non-SCHEDULED
        $qb = $db->table('production_wip')
            ->select($select)
            ->where($procCol, $processId)
            ->where('product_id', $productId)
            ->where("$wipDateCol <=", $date);

        if ($hasStatus) {
            $qb->where("(status <> 'SCHEDULED' OR status IS NULL)", null, false);
        }

        $row2 = $qb->orderBy($wipDateCol, 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

        if ($row2) {
            return [
                'id'       => (int)($row2['id'] ?? 0),
                'stock'    => (int)($row2['stock_val'] ?? 0),
                'transfer' => (int)($row2['transfer_val'] ?? 0),
                'status'   => $row2['status'] ?? null,
            ];
        }

        // 3) fallback from_process_id
        if ($db->fieldExists('from_process_id', 'production_wip')) {
            $qb3 = $db->table('production_wip')
                ->select($select)
                ->where('from_process_id', $processId)
                ->where('product_id', $productId)
                ->where("$wipDateCol <=", $date);

            if ($hasStatus) {
                $qb3->where("(status <> 'SCHEDULED' OR status IS NULL)", null, false);
            }

            $row3 = $qb3->orderBy($wipDateCol, 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

            return [
                'id'       => (int)($row3['id'] ?? 0),
                'stock'    => (int)($row3['stock_val'] ?? 0),
                'transfer' => (int)($row3['transfer_val'] ?? 0),
                'status'   => $row3['status'] ?? null,
            ];
        }

        return ['id' => 0, 'stock' => 0, 'transfer' => 0, 'status' => null];
    }

    private function getLatestStockOnly($db, string $date, int $processId, int $productId): int
    {
        $snap = $this->getLatestSnapshot($db, $date, $processId, $productId);
        return (int)($snap['stock'] ?? 0);
    }

    private function upsertWaitingSnapshot($db, string $date, int $processId, int $productId, int $fromProcessId, int $newStock, int $newTransfer, string $sourceTable, int $sourceId): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->fieldExists('status', 'production_wip')) return;

        $wipDateCol  = $this->detectWipDateColumn($db);
        $procCol     = $this->detectWipProcessColumn($db);
        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);
        if (!$stockCol) return;

        $now = date('Y-m-d H:i:s');

        $row = $db->table('production_wip')
            ->select('id')
            ->where($wipDateCol, $date)
            ->where($procCol, $processId)
            ->where('product_id', $productId)
            ->whereIn('status', ['WAITING', 'OPEN'])
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        if ($row) {
            $upd = [$stockCol => $newStock];
            if ($transferCol) $upd[$transferCol] = $newTransfer;
            if ($db->fieldExists('updated_at', 'production_wip')) $upd['updated_at'] = $now;

            $db->table('production_wip')
                ->where('id', (int)$row['id'])
                ->update($this->onlyExistingColumns($db, 'production_wip', $upd));
            return;
        }

        $ins = [
            $wipDateCol       => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromProcessId,
            'to_process_id'   => $processId,
            'qty'             => 0,
            'qty_in'          => 0,
            'qty_out'         => 0,
            $stockCol         => $newStock,
            'source_table'    => $sourceTable,
            'source_id'       => $sourceId,
            'status'          => 'WAITING',
            'created_at'      => $now,
        ];
        if ($transferCol) $ins[$transferCol] = $newTransfer;

        $ins = $this->onlyExistingColumns($db, 'production_wip', $ins);
        $db->table('production_wip')->insert($ins);
    }

    private function getPrevNextProcessByFlow($db, int $productId, int $currentProcessId): array
    {
        if (!$db->tableExists('product_process_flows')) return ['prev' => null, 'next' => null];

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

    private function getVendors($db): array
    {
        if (!$db->tableExists('vendors')) return [];

        $q = $db->table('vendors')
            ->select('id, vendor_code, vendor_code_app, vendor_name')
            ->orderBy('vendor_name', 'ASC');

        if ($db->fieldExists('is_active', 'vendors')) {
            $q->where('is_active', 1);
        }

        return $q->get()->getResultArray();
    }

    /* =====================================================
     * HELPERS: Daily Schedules
     * ===================================================== */

    private function detectDailyScheduleDateColumn($db): ?string
    {
        if (!$db->tableExists('daily_schedules')) return null;
        if ($db->fieldExists('schedule_date', 'daily_schedules')) return 'schedule_date';
        if ($db->fieldExists('date', 'daily_schedules')) return 'date';
        return null;
    }

    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;

        $dateCol = $this->detectDailyScheduleDateColumn($db);
        if (!$dateCol) return 0;

        $where = [
            $dateCol     => $date,
            'process_id' => $processId,
            'shift_id'   => $shiftId,
        ];

        if ($db->fieldExists('section', 'daily_schedules')) {
            $where['section'] = $section;
        }

        $exist = $db->table('daily_schedules')->where($where)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');

        if ($exist) {
            $upd = [];
            if ($db->fieldExists('is_completed', 'daily_schedules')) $upd['is_completed'] = 0;
            if ($db->fieldExists('updated_at', 'daily_schedules')) $upd['updated_at'] = $now;
            if (!empty($upd)) $db->table('daily_schedules')->where('id', (int)$exist['id'])->update($upd);
            return (int)$exist['id'];
        }

        $payload = $where;
        if ($db->fieldExists('is_completed', 'daily_schedules')) $payload['is_completed'] = 0;
        if ($db->fieldExists('created_at', 'daily_schedules')) $payload['created_at'] = $now;
        if ($db->fieldExists('updated_at', 'daily_schedules')) $payload['updated_at'] = $now;

        $db->table('daily_schedules')->insert($payload);
        return (int)$db->insertID();
    }

    private function insertDailyScheduleItem($db, int $dailyScheduleId, int $shiftId, int $productId, int $qty, int $vendorId): int
    {
        if (!$db->tableExists('daily_schedule_items')) return 0;

        $payload = [
            'daily_schedule_id' => $dailyScheduleId,
            'shift_id'          => $shiftId,
            'product_id'        => $productId,
            'target_per_shift'  => $qty,
            'target_per_hour'   => 0,
            'is_selected'       => 1,
        ];

        if ($db->fieldExists('vendor_id', 'daily_schedule_items')) $payload['vendor_id'] = $vendorId;
        if ($db->fieldExists('machine_id', 'daily_schedule_items') && !isset($payload['machine_id'])) $payload['machine_id'] = 0;

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
        $date = date('Y-m-d');

        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->orderBy('shift_code')
            ->get()->getResultArray();

        $vendors = $this->getVendors($db);

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) {
            return view('baritori/delivery/index', [
                'date'           => $date,
                'shifts'         => $shifts,
                'vendors'        => $vendors,
                'products'       => [],
                'pager'          => null,
                'availableMap'   => [],
                'prevProcMap'    => [],
                'processNameMap' => [],
                'errorMsg'       => 'Process Baritori tidak ditemukan (process_code BT).',
            ]);
        }

        $processNameMap = [];
        if ($db->tableExists('production_processes')) {
            $prows = $db->table('production_processes')->select('id, process_name')->get()->getResultArray();
            foreach ($prows as $r) $processNameMap[(int)$r['id']] = (string)($r['process_name'] ?? '');
        }

        // products yg punya BT dalam flow
        $pidsBT = [];
        if ($db->tableExists('product_process_flows')) {
            $rows = $db->table('product_process_flows')
                ->select('product_id')
                ->where('process_id', $baritoriId)
                ->where('is_active', 1)
                ->groupBy('product_id')
                ->get()->getResultArray();

            foreach ($rows as $r) {
                $pid = (int)($r['product_id'] ?? 0);
                if ($pid > 0) $pidsBT[] = $pid;
            }
        }

        $availableMap = [];
        $prevProcMap  = [];
        $idsAvail     = [];

        if (!empty($pidsBT) && $db->tableExists('production_wip')) {
            foreach ($pidsBT as $pid) {
                $flow   = $this->getPrevNextProcessByFlow($db, $pid, $baritoriId);
                $prevId = (int)($flow['prev'] ?? 0);
                if ($prevId <= 0) continue;

                $av = $this->getLatestStockOnly($db, $date, $prevId, $pid);

                $availableMap[$pid] = $av;
                $prevProcMap[$pid]  = $prevId;

                if ($av > 0) $idsAvail[] = $pid;
            }
        }

        $productModel = new ProductModel();
        $query = $productModel->filterProducts();

        if (!empty($idsAvail)) $query->whereIn('products.id', $idsAvail);
        else $query->where('products.id', 0);

        $products = $query->paginate(10);

        return view('baritori/delivery/index', [
            'date'           => $date,
            'shifts'         => $shifts,
            'vendors'        => $vendors,
            'products'       => $products,
            'pager'          => $productModel->pager,
            'availableMap'   => $availableMap,
            'prevProcMap'    => $prevProcMap,
            'processNameMap' => $processNameMap,
            'errorMsg'       => null,
        ]);
    }

    /* =====================================================
     * STORE (FIXED material_transactions)
     * ===================================================== */

    public function store()
    {
        $db = db_connect();

        $date     = date('Y-m-d');
        $shiftId  = (int)$this->request->getPost('shift_id');
        $vendorId = (int)$this->request->getPost('vendor_id');
        $do       = (string)$this->request->getPost('do_number'); // akan auto-skip jika kolom tidak ada
        $items    = $this->request->getPost('items');

        if ($shiftId <= 0 || $vendorId <= 0 || !is_array($items) || empty($items)) {
            return redirect()->back()->with('error', 'Data tidak lengkap');
        }

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) {
            return redirect()->back()->with('error', 'Process Baritori tidak ditemukan (process_code BT).');
        }

        if (!$db->tableExists('production_wip')) {
            return redirect()->back()->with('error', 'Tabel production_wip tidak ditemukan.');
        }

        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);
        if (!$stockCol) {
            return redirect()->back()->with('error', 'Kolom stock tidak ditemukan di production_wip.');
        }

        $wipDateCol = $this->detectWipDateColumn($db);
        $now        = date('Y-m-d H:i:s');

        $db->transBegin();

        try {
            $dailyId = $this->upsertDailyScheduleHeader($db, $date, $baritoriId, $shiftId, 'Baritori Delivery');
            if ($dailyId <= 0) throw new \Exception("Gagal membuat daily_schedules Baritori Delivery.");

            foreach ($items as $row) {
                $productId = (int)($row['product_id'] ?? 0);
                $qty       = (int)($row['qty'] ?? 0);
                if ($productId <= 0 || $qty <= 0) continue;

                $flow   = $this->getPrevNextProcessByFlow($db, $productId, $baritoriId);
                $prevId = (int)($flow['prev'] ?? 0);
                if ($prevId <= 0) throw new \Exception("Flow sebelumnya untuk product_id {$productId} tidak ditemukan.");

                $prevSnap      = $this->getLatestSnapshot($db, $date, $prevId, $productId);
                $availablePrev = (int)($prevSnap['stock'] ?? 0);
                $prevTransfer  = (int)($prevSnap['transfer'] ?? 0);

                if ($availablePrev <= 0) throw new \Exception("Stock prev process kosong untuk product_id {$productId}.");
                if ($qty > $availablePrev) throw new \Exception("Qty delivery melebihi stock prev process. Available: {$availablePrev}");

                $dailyItemId = $this->insertDailyScheduleItem($db, $dailyId, $shiftId, $productId, $qty, $vendorId);
                if ($dailyItemId <= 0) throw new \Exception("Gagal membuat daily_schedule_items Baritori Delivery.");

                // =====================================================
                // ✅ material_transactions (Sesuai tabel kamu)
                // - process_from = prev process
                // - process_to   = vendor_id (vendors.id)
                // =====================================================
                $trxPayload = [
                    'transaction_date' => $date,
                    'shift_id'         => $shiftId,
                    'product_id'       => $productId,
                    'qty'              => $qty,
                    'transaction_type' => 'VENDOR_OUT',
                    'created_at'       => $now,

                    'process_from'     => $prevId,
                    'process_to'       => $vendorId,

                    // optional (kalau memang ada di tabel)
                    'do_number'        => $do,
                    'source_table'     => 'daily_schedule_items',
                    'source_id'        => $dailyItemId,
                ];

                $trxPayload = $this->onlyExistingColumns($db, 'material_transactions', $trxPayload);
                $db->table('material_transactions')->insert($trxPayload);
                $trxId = (int)$db->insertID();

                // ====== PREV: stock turun + transfer naik ======
                $prevAfterStock     = max(0, $availablePrev - $qty);
                $prevAfterTransfer  = $prevTransfer + $qty;

                $this->upsertWaitingSnapshot(
                    $db,
                    $date,
                    $prevId,
                    $productId,
                    $prevId,
                    $prevAfterStock,
                    $prevAfterTransfer,
                    'material_transactions',
                    $trxId
                );

                // history DONE prev OUT
                $prevWip = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevId,
                    'to_process_id'   => $prevId,
                    'qty'             => $qty,
                    'qty_in'          => 0,
                    'qty_out'         => $qty,
                    $stockCol         => $prevAfterStock,
                    'source_table'    => 'material_transactions',
                    'source_id'       => $trxId,
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];
                if ($transferCol) $prevWip[$transferCol] = $qty;

                $prevWip = $this->onlyExistingColumns($db, 'production_wip', $prevWip);
                $db->table('production_wip')->insert($prevWip);

                // ====== BARITORI: hanya catat movement IN (qty_in) ======
                $barSnap = $this->getLatestSnapshot($db, $date, $baritoriId, $productId);

                $barIn = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevId,
                    'to_process_id'   => $baritoriId,
                    'qty'             => $qty,
                    'qty_in'          => $qty,
                    'qty_out'         => 0,

                    // stock tetap nilai saat itu (tidak ditambah)
                    $stockCol         => (int)($barSnap['stock'] ?? 0),

                    'source_table'    => 'daily_schedule_items',
                    'source_id'       => $dailyItemId,
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];

                $barIn = $this->onlyExistingColumns($db, 'production_wip', $barIn);
                $db->table('production_wip')->insert($barIn);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Delivery Baritori tersimpan ✅');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
