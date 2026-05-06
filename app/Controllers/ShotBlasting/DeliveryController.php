<?php

namespace App\Controllers\ShotBlasting;

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
                if ($row) return (int)$row['id'];
            }
        }
        return null;
    }

    private function getSandBlastingProcessId($db): ?int
    {
        return $this->findProcessId($db, ['SB'], ['SAND BLASTING', 'SHOT BLASTING']);
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

        // Mengambil baris data terakhir sebelum atau sama dengan tanggal transaksi
        $row = $db->table('production_wip')->select($select)
            ->where($procCol, $processId)->where('product_id', $productId)->where("$wipDateCol <=", $date)
            ->orderBy($wipDateCol, 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

        if ($row) return ['id' => (int)$row['id'], 'stock' => (int)$row['stock_val'], 'transfer' => (int)($row['transfer_val'] ?? 0)];
        return ['id' => 0, 'stock' => 0, 'transfer' => 0];
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

    private function upsertCurrentSnapshot($db, string $date, int $processId, int $productId, int $fromProcessId, int $newStock, int $newTransfer, string $sourceTable, int $sourceId): void
    {
        if (!$db->tableExists('production_wip')) return;
        $wipDateCol  = $this->detectWipDateColumn($db);
        $procCol     = $this->detectWipProcessColumn($db);
        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);
        if (!$stockCol) return;

        $targetId = 0;
        $r = $db->table('production_wip')->select('id')
                ->where($wipDateCol, $date)
                ->where($procCol, $processId)
                ->where('product_id', $productId)
                ->where('status !=', 'DONE') // Hindari update baris DONE
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
        $role = strtoupper((string)(session()->get('role') ?? ''));
        $isAdmin = ($role === 'ADMIN');

        $sbId = $this->getSandBlastingProcessId($db);
        if (!$sbId) {
            return view('shot_blasting/delivery/index', ['date' => $date, 'schedules' => [], 'availableMap' => [], 'errorMsg' => 'Process Sand Blasting tidak ditemukan.']);
        }

        $schedules = [];
        $availableMap = [];

        if ($db->tableExists('daily_schedule_items')) {
            $query = $db->table('daily_schedule_items dsi')
                ->select('dsi.id as schedule_item_id, dsi.product_id, p.part_no, p.part_name, dsi.target_per_shift as scheduled_qty, ds.shift_id, s.shift_name');
            
            $schedules = $query->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->join('products p', 'p.id = dsi.product_id')
                ->join('shifts s', 's.id = ds.shift_id', 'left')
                ->where('ds.schedule_date', $date)
                ->where('ds.process_id', $sbId)
                ->get()->getResultArray();

            foreach ($schedules as $sch) {
                $pid = (int)$sch['product_id'];
                if (!isset($availableMap[$pid])) {
                    $flow = $this->getPrevNextProcessByFlow($db, $pid, $sbId);
                    $prevId = (int)($flow['prev'] ?? 0);
                    $available = 0;
                    if ($prevId > 0) {
                        $snap = $this->getLatestSnapshot($db, $date, $prevId, $pid);
                        $available = (int)($snap['stock'] ?? 0);
                    }
                    $availableMap[$pid] = $available;
                }
            }
        }

        return view('shot_blasting/delivery/index', [
            'date'         => $date,
            'schedules'    => $schedules,
            'availableMap' => $availableMap,
            'errorMsg'     => null,
            'isAdmin'      => $isAdmin,
        ]);
    }

    /* =====================================================
     * STORE (Deduct Shot Blasting Stock Internal)
     * ===================================================== */

    public function store()
    {
        $db = db_connect();
        $date  = date('Y-m-d');
        $items = $this->request->getPost('items');

        // Server-side: hanya ADMIN yang boleh bypass stock
        $role = strtoupper((string)(session()->get('role') ?? ''));
        $bypassStock = ($role === 'ADMIN') ? (int)($this->request->getPost('bypass_stock') ?? 0) : 0;

        if (!is_array($items) || empty($items)) return redirect()->back()->with('error', 'Tidak ada data item untuk diproses.');

        $sbId = $this->getSandBlastingProcessId($db);
        if (!$sbId) return redirect()->back()->with('error', 'Process Shot Blasting tidak ditemukan.');

        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);
        $wipDateCol  = $this->detectWipDateColumn($db);
        $procCol     = $this->detectWipProcessColumn($db);
        $now         = date('Y-m-d H:i:s');

        $db->transStart();
        try {
            foreach ($items as $row) {
                $scheduleItemId = (int)($row['schedule_item_id'] ?? 0);
                $qty            = (int)($row['qty'] ?? 0);
                
                if ($scheduleItemId <= 0 || $qty <= 0) continue;

                $dsi = $db->table('daily_schedule_items dsi')
                    ->select('dsi.product_id, ds.shift_id')
                    ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                    ->where('dsi.id', $scheduleItemId)->get()->getRowArray();

                if (!$dsi) throw new \Exception("Jadwal ID {$scheduleItemId} tidak valid.");

                $productId = (int)$dsi['product_id'];
                $shiftId   = (int)$dsi['shift_id'];

                $flow = $this->getPrevNextProcessByFlow($db, $productId, $sbId);
                $prevId = (int)($flow['prev'] ?? 0);
                if ($prevId <= 0 && !$bypassStock) throw new \Exception("Flow sebelumnya tidak ditemukan untuk produk ID {$productId}.");

                // 1. Cek Stock di Proses Sebelumnya (misal Die Casting)
                $prevSnap      = $prevId > 0 ? $this->getLatestSnapshot($db, $date, $prevId, $productId) : ['stock' => 0, 'transfer' => 0, 'id' => 0];
                $availablePrev = (int)($prevSnap['stock'] ?? 0);
                $prevTransfer  = (int)($prevSnap['transfer'] ?? 0);

                if (!$bypassStock && $qty > $availablePrev) {
                    throw new \Exception("Gagal: Qty proses ($qty) melebihi Ready Stock di proses sebelumnya ($availablePrev) untuk produk ID {$productId}.");
                }

                // 2. Transaksi: TRANSFER (Proses Internal Shot Blasting)
                $trxPayload = [
                    'transaction_date' => $date, 
                    'shift_id'         => $shiftId, 
                    'product_id'       => $productId, 
                    'qty'              => $qty,
                    'transaction_type' => 'TRANSFER', 
                    'process_from'     => $prevId > 0 ? $prevId : $sbId,
                    'process_to'       => $sbId,
                    'created_at'       => $now,
                ];

                $db->table('material_transactions')->insert($this->onlyExistingColumns($db, 'material_transactions', $trxPayload));
                $trxId = (int)$db->insertID();

                // 3. Update Stock PrevId (WIP: Prev -> Out)
                $prevAfterStock = max(0, $availablePrev - $qty);

                if ($prevId > 0) {
                    $wipExist = $db->table('production_wip')->select('id')
                                   ->where($wipDateCol, $date)
                                   ->where($procCol, $prevId)
                                   ->where('product_id', $productId)
                                   ->where('status !=', 'DONE')
                                   ->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

                    if ($wipExist) {
                        $upd = [$stockCol => $prevAfterStock];
                        $db->table('production_wip')->where('id', $wipExist['id'])->update($this->onlyExistingColumns($db, 'production_wip', $upd));
                    }

                    $prevWipOut = [
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
                    if ($transferCol) $prevWipOut[$transferCol] = $prevTransfer; 
                    $db->table('production_wip')->insert($this->onlyExistingColumns($db, 'production_wip', $prevWipOut));
                }

                // 4. Update Transfer Shot Blasting (Barang sudah diproses, masuk buffer)
                $sbSnap = $this->getLatestSnapshot($db, $date, $sbId, $productId);
                $sbStock = (int)($sbSnap['stock'] ?? 0);
                $sbTransfer = (int)($sbSnap['transfer'] ?? 0);
                $sbAfterTransfer = $sbTransfer + $qty;

                $sbWipTransfer = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevId > 0 ? $prevId : $sbId,
                    'to_process_id'   => $sbId,
                    'qty'             => $qty,
                    'qty_in'          => 0,
                    'qty_out'         => 0,
                    $stockCol         => $sbStock,       // stock tetap
                    'source_table'    => 'material_transactions',
                    'source_id'       => $trxId,
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];
                if ($transferCol) $sbWipTransfer[$transferCol] = $sbAfterTransfer;
                $db->table('production_wip')->insert($this->onlyExistingColumns($db, 'production_wip', $sbWipTransfer));
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return redirect()->back()->with('error', 'DB error: transaksi gagal, silakan coba lagi.');
            }

            return redirect()->back()->with('success', 'Eksekusi Shot Blasting tersimpan. Stock telah diproses (Transfer).');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}