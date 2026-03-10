<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;

class ReceivingController extends BaseController
{
    /* =========================
     * HELPERS: process + wip
     * ========================= */

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

        // WAITING/OPEN (today)
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

        // fallback latest
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

        return ['id' => 0, 'stock' => 0, 'transfer' => 0, 'status' => null];
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

    /* =========================
     * INDEX
     * ========================= */

    public function index()
    {
        $db = db_connect();

        $baritoriId = $this->getBaritoriProcessId($db);
        if (!$baritoriId) {
            return view('baritori/receiving/index', ['deliveries' => [], 'errorMsg' => 'Process Baritori tidak ditemukan.']);
        }

        $hasVendors   = $db->tableExists('vendors');
        $hasVendorCol = $db->fieldExists('vendor_id', 'material_transactions');
        
        // Membaca kolom vendor (tergantung schema database, fallback ke process_to)
        $vendorCol = $hasVendorCol ? 'mt.vendor_id' : 'mt.process_to';

        $builder = $db->table('material_transactions mt')
            ->select("
                mt.shift_id,
                s.shift_name,
                mt.product_id,
                p.part_no,
                p.part_name,
                {$vendorCol} AS vendor_id,
                " . ($hasVendors ? "COALESCE(v.vendor_name,'-')" : "'-'") . " AS vendor_name,
                SUM(CASE WHEN mt.transaction_type='VENDOR_OUT' THEN mt.qty ELSE 0 END) AS qty_out,
                SUM(CASE WHEN mt.transaction_type='VENDOR_IN'  THEN mt.qty ELSE 0 END) AS qty_in
            ")
            ->join('products p', 'p.id = mt.product_id', 'left')
            ->join('shifts s', 's.id = mt.shift_id', 'left')
            ->whereIn('mt.transaction_type', ['VENDOR_OUT', 'VENDOR_IN']);

        if ($hasVendors) {
            $builder->join('vendors v', "v.id = {$vendorCol}", 'left');
        }

        // AMBIL SEMUA PRODUK YANG MEMILIKI FLOW BARITORI
        $baritoriFlows = $db->table('product_process_flows')
            ->select('product_id')
            ->where('process_id', $baritoriId)
            ->where('is_active', 1)
            ->get()->getResultArray();

        $validConditions = [];
        foreach ($baritoriFlows as $f) {
            $pid = (int)$f['product_id'];
            $flow = $this->getPrevNextProcessByFlow($db, $pid, $baritoriId);
            $prevId = (int)($flow['prev'] ?? 0);
            
            if ($prevId > 0) {
                // Syarat: Transaksi terkait dengan Produk ini, dan process_from nya adalah 
                // ID Proses Sebelumnya (saat Delivery/OUT) ATAU ID Baritori (saat Receive/IN)
                $validConditions[] = "(mt.product_id = {$pid} AND mt.process_from IN ({$prevId}, {$baritoriId}))";
            }
        }

        // Jika tidak ada produk yang terkait Baritori sama sekali, return kosong
        if (empty($validConditions)) {
            return view('baritori/receiving/index', ['deliveries' => [], 'errorMsg' => null]);
        }

        // Terapkan filter Baritori
        $builder->where('(' . implode(' OR ', $validConditions) . ')');

        $builder->groupBy("mt.shift_id, mt.product_id, {$vendorCol}")
                ->orderBy('vendor_name', 'ASC')
                ->orderBy('p.part_no', 'ASC');

        $rows = $builder->get()->getResultArray();

        // hitung outstanding + status
        foreach ($rows as &$d) {
            $qtyOut = (int)($d['qty_out'] ?? 0);
            $qtyIn  = (int)($d['qty_in'] ?? 0);
            $outstanding = max(0, $qtyOut - $qtyIn);

            $d['outstanding'] = $outstanding;
            $d['status'] = ($qtyOut > 0 && $outstanding <= 0) ? 'RECEIVED' : 'OUTSTANDING';
        }
        unset($d);

        return view('baritori/receiving/index', [
            'deliveries' => $rows,
            'errorMsg'   => null
        ]);
    }

    /* =========================
     * STORE
     * ========================= */

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!is_array($items) || empty($items)) {
            return redirect()->back()->with('error', 'Data kosong');
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

        $date       = date('Y-m-d');
        $wipDateCol = $this->detectWipDateColumn($db);
        $now        = date('Y-m-d H:i:s');

        $db->transBegin();

        try {
            foreach ($items as $row) {
                $qty       = (int)($row['qty'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $vendorId  = (int)($row['vendor_id'] ?? 0);

                if ($qty <= 0 || $productId <= 0 || $shiftId <= 0 || $vendorId <= 0) continue;

                $hasVendorCol = $db->fieldExists('vendor_id', 'material_transactions');
                $vendorColName = $hasVendorCol ? 'vendor_id' : 'process_to';

                // outstanding berdasarkan shift+product+vendor
                $sumRow = $db->table('material_transactions')
                    ->select("
                        SUM(CASE WHEN transaction_type='VENDOR_OUT' THEN qty ELSE 0 END) AS qty_out,
                        SUM(CASE WHEN transaction_type='VENDOR_IN'  THEN qty ELSE 0 END) AS qty_in
                    ")
                    ->where('shift_id', $shiftId)
                    ->where('product_id', $productId)
                    ->where($vendorColName, $vendorId)
                    ->whereIn('transaction_type', ['VENDOR_OUT', 'VENDOR_IN'])
                    ->get()->getRowArray();

                $qtyOut = (int)($sumRow['qty_out'] ?? 0);
                $qtyIn  = (int)($sumRow['qty_in'] ?? 0);
                $outstanding = max(0, $qtyOut - $qtyIn);

                if ($outstanding <= 0) continue;
                if ($qty > $outstanding) {
                    throw new \Exception("Qty receive melebihi outstanding. Max: {$outstanding}");
                }

                // 1) Dapatkan proses sebelumnya untuk mengurangi nilai Transfer
                $flow   = $this->getPrevNextProcessByFlow($db, $productId, $baritoriId);
                $prevId = (int)($flow['prev'] ?? 0);

                // 2) insert VENDOR_IN
                $trxPayload = [
                    'transaction_date' => $date,
                    'shift_id'         => $shiftId,
                    'product_id'       => $productId,
                    'qty'              => $qty,
                    'transaction_type' => 'VENDOR_IN',
                    'created_at'       => $now,
                    'process_from'     => $baritoriId, // Set identitas Baritori agar difilter di Index
                ];
                
                if ($hasVendorCol) {
                    $trxPayload['vendor_id']  = $vendorId;
                    $trxPayload['process_to'] = $baritoriId;
                } else {
                    $trxPayload['process_to'] = $vendorId; // Fallback simpan vendor di process_to
                }

                $trxPayload = $this->onlyExistingColumns($db, 'material_transactions', $trxPayload);
                $db->table('material_transactions')->insert($trxPayload);
                $trxId = (int)$db->insertID();

                // 3) Kurangi nilai transfer di Proses Sebelumnya (karena barang sudah diterima di Baritori)
                if ($prevId > 0 && $transferCol) {
                    $prevSnap = $this->getLatestSnapshot($db, $date, $prevId, $productId);
                    $prevStock = (int)($prevSnap['stock'] ?? 0);
                    $prevTransfer = (int)($prevSnap['transfer'] ?? 0);
                    $newPrevTransfer = max(0, $prevTransfer - $qty);

                    $this->upsertWaitingSnapshot(
                        $db, $date, $prevId, $productId, $prevId, $prevStock, $newPrevTransfer, 'material_transactions', $trxId
                    );
                }

                // 4) Update stock WIP Baritori (Stock bertambah)
                $barSnap       = $this->getLatestSnapshot($db, $date, $baritoriId, $productId);
                $barBefore     = (int)($barSnap['stock'] ?? 0);
                $barTransfer   = (int)($barSnap['transfer'] ?? 0);
                $barAfterStock = $barBefore + $qty;

                $this->upsertWaitingSnapshot(
                    $db, $date, $baritoriId, $productId, $prevId, $barAfterStock, $barTransfer, 'material_transactions', $trxId
                );

                // 5) Insert History Log DONE IN (Masuk Baritori)
                $barIn = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevId,
                    'to_process_id'   => $baritoriId,
                    'qty'             => $qty,
                    'qty_in'          => $qty,
                    'qty_out'         => 0,
                    $stockCol         => $barAfterStock,
                    'source_table'    => 'material_transactions',
                    'source_id'       => $trxId,
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];
                if ($transferCol) $barIn[$transferCol] = $barTransfer;

                $barIn = $this->onlyExistingColumns($db, 'production_wip', $barIn);
                $db->table('production_wip')->insert($barIn);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Receiving Baritori berhasil disimpan. Transfer dikurangi & Stock bertambah ✅');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}