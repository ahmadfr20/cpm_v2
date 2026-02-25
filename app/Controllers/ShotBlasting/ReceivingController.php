<?php

namespace App\Controllers\ShotBlasting;

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

    private function getSandBlastingProcessId($db): ?int
    {
        return $this->findProcessId($db, ['SB'], ['SAND BLASTING', 'Sand Blasting', 'SHOT BLASTING', 'Shot Blasting']);
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

        // 1) WAITING/OPEN today
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

        // 2) fallback latest <= date
        $qb = $db->table('production_wip')
            ->select($select)
            ->where($procCol, $processId)
            ->where('product_id', $productId)
            ->where("$wipDateCol <=", $date);

        if ($hasStatus) {
            $qb->where("(status <> 'SCHEDULED' OR status IS NULL)", null, false);
        }

        $row2 = $qb->orderBy($wipDateCol, 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

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
     * INDEX (SAMA DENGAN BARITORI)
     * ========================= */

    public function index()
    {
        $db = db_connect();

        // vendor disimpan di material_transactions.process_to
        $hasVendors = $db->tableExists('vendors');

        $builder = $db->table('material_transactions mt')
            ->select("
                mt.shift_id,
                s.shift_name,

                mt.product_id,
                p.part_no,
                p.part_name,

                mt.process_to AS vendor_id,
                " . ($hasVendors ? "COALESCE(v.vendor_name,'-')" : "'-'") . " AS vendor_name,

                SUM(CASE WHEN mt.transaction_type='VENDOR_OUT' THEN mt.qty ELSE 0 END) AS qty_out,
                SUM(CASE WHEN mt.transaction_type='VENDOR_IN'  THEN mt.qty ELSE 0 END) AS qty_in
            ")
            ->join('products p', 'p.id = mt.product_id', 'left')
            ->join('shifts s', 's.id = mt.shift_id', 'left')
            ->whereIn('mt.transaction_type', ['VENDOR_OUT', 'VENDOR_IN'])
            ->groupBy('mt.shift_id, mt.product_id, mt.process_to')
            ->orderBy('vendor_name', 'ASC')
            ->orderBy('p.part_no', 'ASC');

        if ($hasVendors) {
            $builder->join('vendors v', 'v.id = mt.process_to', 'left');
        }

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$d) {
            $qtyOut = (int)($d['qty_out'] ?? 0);
            $qtyIn  = (int)($d['qty_in'] ?? 0);
            $outstanding = max(0, $qtyOut - $qtyIn);

            $d['outstanding'] = $outstanding;
            $d['status'] = ($qtyOut > 0 && $outstanding <= 0) ? 'RECEIVED' : 'OUTSTANDING';
        }
        unset($d);

        return view('shot_blasting/receiving/index', [
            'deliveries' => $rows,
        ]);
    }

    /* =========================
     * STORE (SAMA DENGAN BARITORI)
     * - insert VENDOR_IN
     * - update WIP stock SB
     * ========================= */

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!is_array($items) || empty($items)) {
            return redirect()->back()->with('error', 'Data kosong');
        }

        $sbId = $this->getSandBlastingProcessId($db);
        if (!$sbId) {
            return redirect()->back()->with('error', 'Process Sand Blasting tidak ditemukan (process_code SB).');
        }

        if (!$db->tableExists('production_wip')) {
            return redirect()->back()->with('error', 'Tabel production_wip tidak ditemukan.');
        }

        $stockCol    = $this->detectWipStockColumn($db);
        $transferCol = $this->detectWipTransferColumn($db);

        if (!$stockCol) {
            return redirect()->back()->with('error', 'Kolom stock tidak ditemukan di production_wip.');
        }

        $date      = date('Y-m-d');
        $wipDateCol = $this->detectWipDateColumn($db);
        $now       = date('Y-m-d H:i:s');

        $db->transBegin();

        try {
            foreach ($items as $row) {
                $qty       = (int)($row['qty'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $vendorId  = (int)($row['vendor_id'] ?? 0);

                if ($qty <= 0 || $productId <= 0 || $shiftId <= 0 || $vendorId <= 0) continue;

                // outstanding berdasarkan shift+product+vendor(process_to)
                $sumRow = $db->table('material_transactions')
                    ->select("
                        SUM(CASE WHEN transaction_type='VENDOR_OUT' THEN qty ELSE 0 END) AS qty_out,
                        SUM(CASE WHEN transaction_type='VENDOR_IN'  THEN qty ELSE 0 END) AS qty_in
                    ")
                    ->where('shift_id', $shiftId)
                    ->where('product_id', $productId)
                    ->where('process_to', $vendorId)
                    ->whereIn('transaction_type', ['VENDOR_OUT', 'VENDOR_IN'])
                    ->get()->getRowArray();

                $qtyOut = (int)($sumRow['qty_out'] ?? 0);
                $qtyIn  = (int)($sumRow['qty_in'] ?? 0);
                $outstanding = max(0, $qtyOut - $qtyIn);

                if ($outstanding <= 0) continue;
                if ($qty > $outstanding) {
                    throw new \Exception("Qty receive melebihi outstanding. Max: {$outstanding}");
                }

                // 1) insert VENDOR_IN
                $trxPayload = [
                    'transaction_date' => $date,
                    'shift_id'         => $shiftId,
                    'product_id'       => $productId,
                    'qty'              => $qty,
                    'transaction_type' => 'VENDOR_IN',
                    'created_at'       => $now,

                    // vendor disimpan di process_to
                    'process_to'       => $vendorId,

                    // optional tracking (kalau kolom ada)
                    'process_from'     => $sbId,
                ];
                $trxPayload = $this->onlyExistingColumns($db, 'material_transactions', $trxPayload);

                $db->table('material_transactions')->insert($trxPayload);
                $trxId = (int)$db->insertID();

                // 2) update stock WIP SB (receiving yang isi stock)
                $sbSnap       = $this->getLatestSnapshot($db, $date, $sbId, $productId);
                $sbBefore     = (int)($sbSnap['stock'] ?? 0);
                $sbTransfer   = (int)($sbSnap['transfer'] ?? 0);
                $sbAfterStock = $sbBefore + $qty;

                $this->upsertWaitingSnapshot(
                    $db,
                    $date,
                    $sbId,
                    $productId,
                    $sbId,
                    $sbAfterStock,
                    $sbTransfer,
                    'material_transactions',
                    $trxId
                );

                // history DONE IN
                $sbIn = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $sbId,
                    'to_process_id'   => $sbId,
                    'qty'             => $qty,
                    'qty_in'          => $qty,
                    'qty_out'         => 0,
                    $stockCol         => $sbAfterStock,
                    'source_table'    => 'material_transactions',
                    'source_id'       => $trxId,
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];
                if ($transferCol) $sbIn[$transferCol] = 0;

                $sbIn = $this->onlyExistingColumns($db, 'production_wip', $sbIn);
                $db->table('production_wip')->insert($sbIn);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Receiving Shot Blasting berhasil disimpan ✅');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
