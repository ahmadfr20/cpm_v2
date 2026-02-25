<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\ProductProcessFlowModel;
use App\Models\ProductModel;
use App\Models\ProductionProcessModel;

class ProductProcessFlowController extends BaseController
{
    protected $flowModel;
    protected $productModel;
    protected $processModel;

    public function __construct()
    {
        $this->flowModel    = new ProductProcessFlowModel();
        $this->productModel = new ProductModel();
        $this->processModel = new ProductionProcessModel();
    }

    /* =====================================================
     * WIP COLUMN DETECT (STOCK)
     * ===================================================== */
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

    /**
     * Ambil stock terbaru per (product_id, process_id) berdasarkan MAX(id)
     * Return: [product_id][process_id] => stock_int
     */
    private function getLatestStockMap($db, array $productIds, array $processIds): array
    {
        $stockMap = [];

        if (empty($productIds) || empty($processIds)) return $stockMap;
        if (!$db->tableExists('production_wip')) return $stockMap;

        $procCol  = $this->detectWipProcessColumn($db);
        $stockCol = $this->detectWipStockColumn($db);
        if (!$stockCol) return $stockMap;

        $sub = $db->table('production_wip')
            ->select('MAX(id) AS id')
            ->whereIn('product_id', $productIds)
            ->whereIn($procCol, $processIds)
            ->groupBy(['product_id', $procCol])
            ->getCompiledSelect();

        $sql = "
            SELECT
                product_id,
                {$procCol} AS process_id,
                COALESCE({$stockCol},0) AS stock_val
            FROM production_wip
            WHERE id IN ({$sub})
        ";

        $rows = $db->query($sql)->getResultArray();

        foreach ($rows as $r) {
            $pid = (int)($r['product_id'] ?? 0);
            $prc = (int)($r['process_id'] ?? 0);
            $stk = (int)($r['stock_val'] ?? 0);
            if ($pid > 0 && $prc > 0) {
                $stockMap[$pid][$prc] = $stk;
            }
        }

        return $stockMap;
    }

    /* =====================================================
     * ✅ PRODUCTION STANDARD (PCS/MONTH, PCS/DAY, BOX/DAY, MIN, MAX)
     * - table name kamu bisa beda, jadi dibuat auto-detect
     * ===================================================== */

    private function detectProductionStandardTable($db): ?string
    {
        // umum dipakai
        $candidates = [
            'production_standards',
            'production_standard',
            'production_standard_items',
            'production_standard_master',
        ];

        foreach ($candidates as $t) {
            if ($db->tableExists($t)) return $t;
        }
        return null;
    }

    private function pickFirstExistingColumn($db, string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if ($db->fieldExists($c, $table)) return $c;
        }
        return null;
    }

    /**
     * Return:
     * [
     *   product_id => [
     *     'pcs_month' => int|null,
     *     'pcs_day'   => int|null,
     *     'box_day'   => int|null,
     *     'min'       => int|null,
     *     'max'       => int|null,
     *   ],
     * ]
     */
    private function getStandardMap($db, array $productIds): array
    {
        $map = [];
        if (empty($productIds)) return $map;

        $tbl = $this->detectProductionStandardTable($db);
        if (!$tbl) return $map;

        // harus ada product_id
        if (!$db->fieldExists('product_id', $tbl)) return $map;

        // deteksi nama kolom yang mungkin berbeda
        $colPcsMonth = $this->pickFirstExistingColumn($db, $tbl, [
            'pcs_month', 'pcs_per_month', 'qty_month', 'monthly_pcs', 'target_month'
        ]);
        $colPcsDay = $this->pickFirstExistingColumn($db, $tbl, [
            'pcs_day', 'pcs_per_day', 'qty_day', 'daily_pcs', 'target_day'
        ]);
        $colBoxDay = $this->pickFirstExistingColumn($db, $tbl, [
            'box_day', 'box_per_day', 'boxes_day', 'daily_box', 'target_box_day'
        ]);
        $colMin = $this->pickFirstExistingColumn($db, $tbl, [
            'min', 'min_stock', 'min_qty', 'stock_min', 'min_wip'
        ]);
        $colMax = $this->pickFirstExistingColumn($db, $tbl, [
            'max', 'max_stock', 'max_qty', 'stock_max', 'max_wip'
        ]);

        // kalau semuanya tidak ada, return kosong
        if (!$colPcsMonth && !$colPcsDay && !$colBoxDay && !$colMin && !$colMax) return $map;

        $select = ['product_id'];
        if ($colPcsMonth) $select[] = "{$colPcsMonth} AS pcs_month";
        if ($colPcsDay)   $select[] = "{$colPcsDay} AS pcs_day";
        if ($colBoxDay)   $select[] = "{$colBoxDay} AS box_day";
        if ($colMin)      $select[] = "{$colMin} AS min_val";
        if ($colMax)      $select[] = "{$colMax} AS max_val";

        $rows = $db->table($tbl)
            ->select(implode(',', $select))
            ->whereIn('product_id', $productIds)
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $pid = (int)($r['product_id'] ?? 0);
            if ($pid <= 0) continue;

            $map[$pid] = [
                'pcs_month' => isset($r['pcs_month']) ? (int)$r['pcs_month'] : null,
                'pcs_day'   => isset($r['pcs_day'])   ? (int)$r['pcs_day']   : null,
                'box_day'   => isset($r['box_day'])   ? (int)$r['box_day']   : null,
                'min'       => isset($r['min_val'])   ? (int)$r['min_val']   : null,
                'max'       => isset($r['max_val'])   ? (int)$r['max_val']   : null,
            ];
        }

        return $map;
    }

    /* =====================================================
     * ✅ METHOD YANG SUDAH ADA: index()
     * (tidak diganti nama / signature-nya)
     * ===================================================== */
    public function index()
    {
        $perPage   = (int) ($this->request->getGet('per_page') ?? 10);
        $keyword   = $this->request->getGet('keyword');
        $sort      = $this->request->getGet('sort') ?? 'part_no';
        $direction = strtoupper($this->request->getGet('dir') ?? 'ASC');

        $allowedSort = ['part_no', 'part_name'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'part_no';
        $direction = $direction === 'DESC' ? 'DESC' : 'ASC';

        $query = $this->productModel->where('is_active', 1);

        if ($keyword) {
            $query->groupStart()
                ->like('part_no', $keyword)
                ->orLike('part_name', $keyword)
                ->groupEnd();
        }

        $products = $query->orderBy($sort, $direction)->paginate($perPage, 'products');
        $pager    = $this->productModel->pager;

        $processes = $this->processModel->orderBy('id', 'ASC')->findAll();

        // Ambil flow aktif dengan urutan sequence
        $flows = $this->flowModel
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->findAll();

        $flowMap   = [];
        $flowOrder = [];
        foreach ($flows as $f) {
            $pid = (int)$f['product_id'];
            $prc = (int)$f['process_id'];
            $flowMap[$pid][$prc] = true;
            $flowOrder[$pid][]   = $prc;
        }

        // ✅ tambahan data untuk view (TANPA mengubah method yang sudah ada)
        $db = db_connect();

        $productIds = [];
        foreach (($products ?? []) as $p) {
            $pid = (int)($p['id'] ?? 0);
            if ($pid > 0) $productIds[] = $pid;
        }

        $processIds = [];
        foreach (($processes ?? []) as $pr) {
            $prc = (int)($pr['id'] ?? 0);
            if ($prc > 0) $processIds[] = $prc;
        }

        // ✅ stock per process per product (di bawah checkbox)
        $stockMap = $this->getLatestStockMap($db, $productIds, $processIds);

        // ✅ kolom KPI setelah proses (PCS/MONTH, PCS/DAY, BOX/DAY, MIN, MAX)
        $standardMap = $this->getStandardMap($db, $productIds);

        return view('master/production_flow/index_side_chip', compact(
            'products',
            'processes',
            'flowMap',
            'flowOrder',
            'stockMap',
            'standardMap',
            'pager',
            'perPage',
            'keyword',
            'sort',
            'direction'
        ));
    }

    /* =====================================================
     * METHOD YANG SUDAH ADA: bulkUpdate()
     * ===================================================== */
    public function bulkUpdate()
    {
        $productIds   = $this->request->getPost('product_ids') ?? [];
        $selectedPost = $this->request->getPost('flows_selected') ?? [];
        $orderPost    = $this->request->getPost('flows_order') ?? [];

        if (!is_array($productIds) || count($productIds) === 0) {
            return redirect()->back()->with('error', 'Tidak ada data produk yang dikirim.');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            foreach ($productIds as $raw) {
                $productId = (int)$raw;
                if ($productId <= 0) continue;

                // selected[] dari checkbox
                $selected = $selectedPost[$productId] ?? [];
                if (!is_array($selected)) $selected = [];

                $selected = array_values(array_unique(array_filter($selected, function ($v) {
                    return ctype_digit((string)$v) && (int)$v > 0;
                })));
                $selected = array_map('intval', $selected);

                // order string dari hidden (JS)
                $orderStr = (string)($orderPost[$productId] ?? '');
                $orderIds = [];
                if ($orderStr !== '') {
                    foreach (array_filter(array_map('trim', explode(',', $orderStr))) as $v) {
                        if (ctype_digit($v) && (int)$v > 0) $orderIds[] = (int)$v;
                    }
                }

                // final order
                $final = [];
                foreach ($orderIds as $oid) {
                    if (in_array($oid, $selected, true)) $final[] = $oid;
                }
                foreach ($selected as $sid) {
                    if (!in_array($sid, $final, true)) $final[] = $sid;
                }

                $this->saveOneProductFlow($productId, $final);
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Production flow berhasil diperbarui (Bulk Save).');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    /* =====================================================
     * METHOD YANG SUDAH ADA: saveIndividual()
     * ===================================================== */
    public function saveIndividual()
    {
        $productId = (int)$this->request->getPost('product_id');
        $selected  = $this->request->getPost('selected') ?? [];
        $orderStr  = (string)($this->request->getPost('order') ?? '');

        if ($productId <= 0) {
            return $this->response->setJSON([
                'ok' => false,
                'message' => 'Product tidak valid',
                'csrfHash' => csrf_hash(),
            ]);
        }

        if (!is_array($selected)) $selected = [];
        $selected = array_values(array_unique(array_filter($selected, function ($v) {
            return ctype_digit((string)$v) && (int)$v > 0;
        })));
        $selected = array_map('intval', $selected);

        $orderIds = [];
        if ($orderStr !== '') {
            foreach (array_filter(array_map('trim', explode(',', $orderStr))) as $v) {
                if (ctype_digit($v) && (int)$v > 0) $orderIds[] = (int)$v;
            }
        }

        $final = [];
        foreach ($orderIds as $oid) {
            if (in_array($oid, $selected, true)) $final[] = $oid;
        }
        foreach ($selected as $sid) {
            if (!in_array($sid, $final, true)) $final[] = $sid;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->saveOneProductFlow($productId, $final);
            $db->transCommit();

            return $this->response->setJSON([
                'ok'       => true,
                'message'  => 'Flow disimpan',
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'ok'       => false,
                'message'  => $e->getMessage(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /* =====================================================
     * METHOD YANG SUDAH ADA: saveOneProductFlow()
     * ===================================================== */
    private function saveOneProductFlow(int $productId, array $finalOrder): void
    {
        $existingRows = $this->flowModel->where('product_id', $productId)->findAll();

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[(int)$row['process_id']] = [
                'id'        => (int)$row['id'],
                'is_active' => (int)$row['is_active'],
            ];
        }

        // deactivate yang tidak dipilih
        foreach ($existingMap as $procId => $info) {
            if (!in_array($procId, $finalOrder, true) && $info['is_active'] === 1) {
                $this->flowModel->update($info['id'], ['is_active' => 0]);
            }
        }

        // activate + update sequence
        $seq = 1;
        foreach ($finalOrder as $procId) {
            if (isset($existingMap[$procId])) {
                $this->flowModel->update($existingMap[$procId]['id'], [
                    'sequence'  => $seq,
                    'is_active' => 1,
                ]);
            } else {
                $this->flowModel->insert([
                    'product_id' => $productId,
                    'process_id' => $procId,
                    'sequence'   => $seq,
                    'is_active'  => 1,
                ]);
            }
            $seq++;
        }
    }
}
