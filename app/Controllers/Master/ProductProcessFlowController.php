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

    public function index()
    {
        $perPage   = (int) ($this->request->getGet('per_page') ?? 10);
        $keyword   = $this->request->getGet('keyword');
        $sort      = $this->request->getGet('sort') ?? 'part_no';
        $direction = strtoupper($this->request->getGet('dir') ?? 'ASC');

        $allowedSort = ['part_no', 'part_name'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'part_no';
        }
        $direction = $direction === 'DESC' ? 'DESC' : 'ASC';

        // Products (aktif)
        $query = $this->productModel->where('is_active', 1);

        if ($keyword) {
            $query->groupStart()
                ->like('part_no', $keyword)
                ->orLike('part_name', $keyword)
                ->groupEnd();
        }

        $products = $query->orderBy($sort, $direction)
            ->paginate($perPage, 'products');

        $pager = $this->productModel->pager;

        // Master processes
        $processes = $this->processModel->orderBy('id', 'ASC')->findAll();

        // Active flow map
        $flows = $this->flowModel->where('is_active', 1)->findAll();

        $flowMap = [];
        foreach ($flows as $f) {
            $flowMap[$f['product_id']][$f['process_id']] = true;
        }

        return view('master/production_flow/index', compact(
            'products',
            'processes',
            'flowMap',
            'pager',
            'perPage',
            'keyword',
            'sort',
            'direction'
        ));
    }

    /**
     * BULK SAVE FLOW (AMAN + TIDAK HILANG)
     * - Tidak insert kalau kombinasi product-process sudah pernah ada (karena UNIQUE)
     * - Akan re-activate row yang sebelumnya is_active=0
     * - Bisa clear semua flow dengan uncheck semua
     */
    public function bulkUpdate()
    {
        $productIds = $this->request->getPost('product_ids') ?? [];
        $flowsPost  = $this->request->getPost('flows') ?? [];

        if (!is_array($productIds) || count($productIds) === 0) {
            return redirect()->back()->with('error', 'Tidak ada data produk yang dikirim.');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            foreach ($productIds as $productIdRaw) {

                $productId = (int) $productIdRaw;
                if ($productId <= 0) {
                    continue;
                }

                // Ambil processIds dari POST, kalau tidak ada berarti kosong
                $processIds = $flowsPost[$productId] ?? [];

                // Pastikan array
                if (!is_array($processIds)) {
                    $processIds = [];
                }

                // Bersihkan nilai kosong dari hidden input, pastikan integer unik
                $processIds = array_values(array_unique(array_filter($processIds, function ($v) {
                    return ctype_digit((string) $v) && (int)$v > 0;
                })));
                $processIds = array_map('intval', $processIds);

                // ===== Ambil semua flow existing (aktif & nonaktif) untuk produk ini =====
                $existingRows = $this->flowModel
                    ->where('product_id', $productId)
                    ->findAll();

                // Map process_id -> row (id, is_active)
                $existingMap = [];
                foreach ($existingRows as $row) {
                    $existingMap[(int)$row['process_id']] = [
                        'id'        => (int)$row['id'],
                        'is_active' => (int)$row['is_active'],
                    ];
                }

                // ===== 1) Deactivate yang tidak dipilih =====
                // (kalau sebelumnya aktif dan sekarang tidak ada di processIds)
                foreach ($existingMap as $procId => $rowInfo) {
                    if (!in_array($procId, $processIds, true) && $rowInfo['is_active'] === 1) {
                        $this->flowModel->update($rowInfo['id'], ['is_active' => 0]);
                    }
                }

                // ===== 2) Activate + update sequence yang dipilih =====
                $seq = 1;
                foreach ($processIds as $procId) {
                    if (isset($existingMap[$procId])) {
                        // UPDATE row yang sudah ada (aktif/nonaktif) -> re-activate
                        $this->flowModel->update($existingMap[$procId]['id'], [
                            'sequence'  => $seq,
                            'is_active' => 1,
                        ]);
                    } else {
                        // INSERT hanya jika benar-benar belum pernah ada
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

            $db->transCommit();
            return redirect()->back()->with('success', 'Production flow berhasil diperbarui (Bulk Save).');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }
}
