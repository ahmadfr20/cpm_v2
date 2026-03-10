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

        // Cari baris terakhir berdasarkan tanggal <= tanggal saat ini
        $row = $db->table('production_wip')->select($select)
            ->where($procCol, $processId)->where('product_id', $productId)->where("$wipDateCol <=", $date)
            ->orderBy($wipDateCol, 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

        if ($row) return ['id' => (int)$row['id'], 'stock' => (int)$row['stock_val'], 'transfer' => (int)($row['transfer_val'] ?? 0)];
        return ['id' => 0, 'stock' => 0, 'transfer' => 0];
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
                    // AMBIL STOCK LANGSUNG DARI BARITORI (karena di Schedule Controller sudah masuk ke Baritori)
                    $snap = $this->getLatestSnapshot($db, $date, $baritoriId, $pid);
                    $availableMap[$pid] = (int)($snap['stock'] ?? 0);
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
     * STORE (Deduct Baritori Stock & Vendor Out)
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
        $procCol     = $this->detectWipProcessColumn($db);
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

                // 1. Cek Stock Langsung di Baritori
                $btSnap      = $this->getLatestSnapshot($db, $date, $baritoriId, $productId);
                $availableBt = (int)($btSnap['stock'] ?? 0);
                $btTransfer  = (int)($btSnap['transfer'] ?? 0);

                if ($qty > $availableBt) {
                    throw new \Exception("Gagal: Qty delivery ($qty) melebihi Ready Stock di Baritori ($availableBt) untuk produk ID {$productId}.");
                }

                // 2. Transaksi: VENDOR_OUT (Keluar dari Baritori ke Vendor)
                $trxPayload = [
                    'transaction_date' => $date, 
                    'shift_id'         => $shiftId, 
                    'product_id'       => $productId, 
                    'qty'              => $qty,
                    'transaction_type' => 'VENDOR_OUT', 
                    'process_from'     => $baritoriId, // Barang keluar dari Baritori
                    'created_at'       => $now,
                ];

                // Cek ketersediaan kolom vendor di material_transactions
                $hasVendorCol = $db->fieldExists('vendor_id', 'material_transactions');
                if ($hasVendorCol) {
                    $trxPayload['vendor_id']  = $vendorId;
                } else {
                    $trxPayload['process_to'] = $vendorId; // Fallback jika tidak ada kolom vendor_id
                }

                $trxPayload['do_number']    = $do;
                $trxPayload['source_table'] = 'daily_schedule_items';
                $trxPayload['source_id']    = $scheduleItemId;

                $db->table('material_transactions')->insert($this->onlyExistingColumns($db, 'material_transactions', $trxPayload));
                $trxId = (int)$db->insertID();

                // 3. Update Stock Baritori (WIP: Baritori -> Out)
                // Karena barang dikirim ke Vendor, Stock di Baritori BERKURANG
                // dan dipindahkan ke Transfer Baritori (sebagai penanda sedang di subcon)
                $btAfterStock    = max(0, $availableBt - $qty);
                $btAfterTransfer = $btTransfer + $qty;

                // Cek apakah hari ini sudah ada record Waiting/Open
                // Agar tidak selalu Insert baru jika melakukan update di hari yang sama
                $wipExist = $db->table('production_wip')->select('id')
                               ->where($wipDateCol, $date)
                               ->where($procCol, $baritoriId)
                               ->where('product_id', $productId)
                               ->where('status !=', 'DONE')
                               ->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

                if ($wipExist) {
                    // Update baris terakhir hari ini (jika bukan DONE)
                    $upd = [$stockCol => $btAfterStock];
                    if ($transferCol) $upd[$transferCol] = $btAfterTransfer;
                    $db->table('production_wip')->where('id', $wipExist['id'])->update($this->onlyExistingColumns($db, 'production_wip', $upd));
                }

                // Insert History Pengurangan Stock (DONE)
                $btWipOut = [
                    $wipDateCol       => $date, 
                    'product_id'      => $productId, 
                    'from_process_id' => $baritoriId, 
                    'to_process_id'   => $baritoriId, // Tetap di baritori, cuma ubah bentuk dari stock ke transfer
                    'qty'             => $qty, 
                    'qty_in'          => 0, 
                    'qty_out'         => $qty, // Dicatat sebagai Barang Keluar dari Stock
                    $stockCol         => $btAfterStock, // Nilai stock setelah dikurangi
                    'source_table'    => 'material_transactions', 
                    'source_id'       => $trxId, 
                    'status'          => 'DONE', 
                    'created_at'      => $now,
                ];
                if ($transferCol) $btWipOut[$transferCol] = $btAfterTransfer; // Nilai transfer setelah ditambah
                
                $db->table('production_wip')->insert($this->onlyExistingColumns($db, 'production_wip', $btWipOut));
            }

            if ($db->transStatus() === false) throw new \Exception('DB error saat proses delivery.');

            $db->transCommit();
            return redirect()->back()->with('success', 'Delivery Baritori berhasil disimpan. Stock Baritori telah dikurangi sesuai input aktual.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}