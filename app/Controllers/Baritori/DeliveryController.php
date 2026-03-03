<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;

class DeliveryController extends BaseController
{
    /* =====================================================
     * HELPERS
     * ===================================================== */

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
        return $this->findProcessId($db, ['BT'], ['BURRYTORY', 'BARITORI', 'Baritori']);
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

    private function detectWipDateColumn($db): string { return $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date'; }
    private function detectWipProcessColumn($db): string { return $db->fieldExists('to_process_id', 'production_wip') ? 'to_process_id' : 'process_id'; }
    private function detectWipStockColumn($db): ?string { return $db->fieldExists('stock', 'production_wip') ? 'stock' : null; }
    private function detectWipTransferColumn($db): ?string { return $db->fieldExists('transfer', 'production_wip') ? 'transfer' : null; }

    private function onlyExistingColumns($db, string $table, array $data): array
    {
        $clean = [];
        foreach ($data as $k => $v) if ($db->fieldExists($k, $table)) $clean[$k] = $v;
        return $clean;
    }

    private function getLatestSnapshot($db, string $date, int $processId, int $productId): array
    {
        if (!$db->tableExists('production_wip')) return ['id' => 0, 'stock' => 0, 'transfer' => 0];

        $wipDateCol  = $this->detectWipDateColumn($db);
        $procCol     = $this->detectWipProcessColumn($db);
        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);

        if (!$stockCol) return ['id' => 0, 'stock' => 0, 'transfer' => 0];

        $select = "id, COALESCE($stockCol,0) AS stock_val";
        if ($transferCol) $select .= ", COALESCE($transferCol,0) AS transfer_val";

        $row = $db->table('production_wip')->select($select)
            ->where($procCol, $processId)->where('product_id', $productId)->where("$wipDateCol <=", $date)
            ->orderBy($wipDateCol, 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

        if ($row) return ['id' => (int)$row['id'], 'stock' => (int)$row['stock_val'], 'transfer' => (int)($row['transfer_val'] ?? 0)];
        return ['id' => 0, 'stock' => 0, 'transfer' => 0];
    }

    private function upsertCurrentSnapshot($db, string $date, int $processId, int $productId, int $fromProcessId, int $newStock, int $newTransfer, string $sourceTable, int $sourceId): void
    {
        if (!$db->tableExists('production_wip')) return;
        $wipDateCol  = $this->detectWipDateColumn($db);
        $procCol     = $this->detectWipProcessColumn($db);
        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);
        if (!$stockCol) return;

        $targetId = 0;
        $r = $db->table('production_wip')->select('id')->where($wipDateCol, $date)->where($procCol, $processId)->where('product_id', $productId)
                ->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        if ($r) $targetId = (int)$r['id'];

        $now = date('Y-m-d H:i:s');

        if ($targetId > 0) {
            $upd = [$stockCol => $newStock];
            if ($transferCol) $upd[$transferCol] = $newTransfer;
            $db->table('production_wip')->where('id', $targetId)->update($this->onlyExistingColumns($db, 'production_wip', $upd));
            return;
        }

        $ins = [
            $wipDateCol => $date, 'product_id' => $productId, 'from_process_id' => $fromProcessId, 'to_process_id' => $processId,
            'qty' => 0, 'qty_in' => 0, 'qty_out' => 0, $stockCol => $newStock, 'source_table' => $sourceTable, 'source_id' => $sourceId, 'created_at' => $now,
        ];
        if ($transferCol) $ins[$transferCol] = $newTransfer;
        $db->table('production_wip')->insert($this->onlyExistingColumns($db, 'production_wip', $ins));
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
            return view('baritori/delivery/index', ['date' => $date, 'schedules' => [], 'availableMap' => [], 'errorMsg' => 'Process Baritori tidak ditemukan.']);
        }

        $schedules = [];
        $availableMap = [];

        if ($db->tableExists('daily_schedule_items')) {
            $query = $db->table('daily_schedule_items dsi')
                ->select('dsi.id as schedule_item_id, dsi.product_id, p.part_no, p.part_name, dsi.target_per_shift as scheduled_qty, ds.shift_id, s.shift_name');
            
            if ($db->fieldExists('vendor_id', 'daily_schedule_items')) {
                $query->select('dsi.vendor_id, v.vendor_name, v.vendor_code')->join('vendors v', 'v.id = dsi.vendor_id', 'left');
            }

            $schedules = $query->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->join('products p', 'p.id = dsi.product_id')
                ->join('shifts s', 's.id = ds.shift_id', 'left')
                ->where('ds.schedule_date', $date)
                ->where('ds.process_id', $baritoriId)
                ->get()->getResultArray();

            foreach ($schedules as $sch) {
                $pid = (int)$sch['product_id'];
                if (!isset($availableMap[$pid])) {
                    // Cari Process Sebelumnya (Prev Flow)
                    $flow   = $this->getPrevNextProcessByFlow($db, $pid, $baritoriId);
                    $prevId = (int)($flow['prev'] ?? 0);

                    // Ambil stock dari prev process, bukan dari Baritori
                    if ($prevId > 0) {
                        $snap = $this->getLatestSnapshot($db, $date, $prevId, $pid);
                        $availableMap[$pid] = (int)($snap['stock'] ?? 0);
                    } else {
                        $availableMap[$pid] = 0;
                    }
                }
            }
        }

        return view('baritori/delivery/index', [
            'date'         => $date,
            'schedules'    => $schedules,
            'availableMap' => $availableMap,
            'errorMsg'     => null,
        ]);
    }

    /* =====================================================
     * STORE (Deduct Prev Process Logic)
     * ===================================================== */

    public function store()
    {
        $db = db_connect();
        $date  = date('Y-m-d');
        $items = $this->request->getPost('items');

        if (!is_array($items) || empty($items)) return redirect()->back()->with('error', 'Tidak ada data item untuk dikirim.');

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) return redirect()->back()->with('error', 'Process Baritori tidak ditemukan.');

        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);
        $wipDateCol  = $this->detectWipDateColumn($db);
        $now         = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            foreach ($items as $row) {
                $scheduleItemId = (int)($row['schedule_item_id'] ?? 0);
                $qty            = (int)($row['qty'] ?? 0);
                $do             = (string)($row['do_number'] ?? '');
                
                if ($scheduleItemId <= 0 || $qty <= 0) continue;

                $dsi = $db->table('daily_schedule_items dsi')
                    ->select('dsi.product_id, dsi.vendor_id, ds.shift_id')
                    ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                    ->where('dsi.id', $scheduleItemId)->get()->getRowArray();

                if (!$dsi) throw new \Exception("Jadwal ID {$scheduleItemId} tidak valid.");

                $productId = (int)$dsi['product_id'];
                $vendorId  = (int)($dsi['vendor_id'] ?? 0);
                $shiftId   = (int)$dsi['shift_id'];

                if ($vendorId <= 0) throw new \Exception("Vendor untuk produk ID {$productId} tidak tersetting di jadwal.");

                // 1. Cari Process Sebelumnya
                $flow   = $this->getPrevNextProcessByFlow($db, $productId, $baritoriId);
                $prevId = (int)($flow['prev'] ?? 0);

                if ($prevId <= 0) throw new \Exception("Flow process sebelumnya untuk produk ID {$productId} tidak ditemukan.");

                // 2. Cek Stock di Process Sebelumnya
                $prevSnap = $this->getLatestSnapshot($db, $date, $prevId, $productId);
                $availablePrev = (int)($prevSnap['stock'] ?? 0);
                $prevTransfer  = (int)($prevSnap['transfer'] ?? 0);

                if ($qty > $availablePrev) throw new \Exception("Qty delivery melebihi stock pada process sebelumnya. Available: {$availablePrev}");

                // 3. Transaksi: Dari Prev -> Vendor (Melewati Baritori)
                $trxPayload = [
                    'transaction_date' => $date, 'shift_id' => $shiftId, 'product_id' => $productId, 'qty' => $qty,
                    'transaction_type' => 'VENDOR_OUT', 
                    'process_from'     => $prevId,   // Dari Process Sebelumnya
                    'process_to'       => $vendorId, // Ke Vendor
                    'do_number' => $do, 'source_table' => 'daily_schedule_items', 'source_id' => $scheduleItemId, 'created_at' => $now,
                ];
                $db->table('material_transactions')->insert($this->onlyExistingColumns($db, 'material_transactions', $trxPayload));
                $trxId = (int)$db->insertID();

                // 4. Update Stock Prev Process (WIP: Prev -> Out)
                $prevAfterStock    = max(0, $availablePrev - $qty);
                $prevAfterTransfer = $prevTransfer + $qty; // Anggap pindah ke transfer/buffer vendor

                $this->upsertCurrentSnapshot($db, $date, $prevId, $productId, $prevId, $prevAfterStock, $prevAfterTransfer, 'material_transactions', $trxId);

                $prevWip = [
                    $wipDateCol => $date, 'product_id' => $productId, 
                    'from_process_id' => $prevId, 
                    'to_process_id'   => $prevId,
                    'qty' => $qty, 'qty_in' => 0, 'qty_out' => $qty, 
                    $stockCol => $prevAfterStock,
                    'source_table' => 'material_transactions', 'source_id' => $trxId, 'status' => 'DONE', 'created_at' => $now,
                ];
                if ($transferCol) $prevWip[$transferCol] = $qty;
                $db->table('production_wip')->insert($this->onlyExistingColumns($db, 'production_wip', $prevWip));

                // 5. Update WIP Baritori (Hanya Lewat: In = Qty, Out = Qty)
                // Agar tercatat bahwa barang melewati Baritori, tapi tidak mengendap di stock Baritori
                $btSnap = $this->getLatestSnapshot($db, $date, $baritoriId, $productId);
                $btCurrentStock = (int)($btSnap['stock'] ?? 0);

                $btWip = [
                    $wipDateCol => $date, 'product_id' => $productId, 
                    'from_process_id' => $prevId, 
                    'to_process_id'   => $baritoriId,
                    'qty' => $qty, 
                    'qty_in'  => $qty, // Masuk Baritori
                    'qty_out' => $qty, // Langsung Keluar ke Vendor
                    $stockCol => $btCurrentStock, // Stock Baritori Tetap (Tidak bertambah)
                    'source_table' => 'material_transactions', 'source_id' => $trxId, 'status' => 'DONE', 'created_at' => $now,
                ];
                $db->table('production_wip')->insert($this->onlyExistingColumns($db, 'production_wip', $btWip));
            }

            if ($db->transStatus() === false) throw new \Exception('DB error saat proses delivery.');

            $db->transCommit();
            return redirect()->back()->with('success', 'Delivery Baritori berhasil disimpan. Stock process sebelumnya telah dikurangi.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}