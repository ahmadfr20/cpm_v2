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
     * INDEX – SINGLE PAGE FLOW
     * ===================================================== */
    public function index()
{
    $effectiveDate = $this->request->getGet('date') ?? date('Y-m-d');
    $perPage       = $this->request->getGet('per_page') ?? 10;
    $keyword       = $this->request->getGet('keyword');
    $sort          = $this->request->getGet('sort') ?? 'part_no';
    $direction     = $this->request->getGet('dir') ?? 'ASC';

    /* =========================
     * BASE QUERY PRODUCT
     * ========================= */
    $productQuery = $this->productModel
        ->where('is_active', 1);

    /* =========================
     * SEARCH
     * ========================= */
    if ($keyword) {
        $productQuery->groupStart()
            ->like('part_no', $keyword)
            ->orLike('part_name', $keyword)
            ->groupEnd();
    }

    /* =========================
     * SORTING (WHITELIST)
     * ========================= */
    $allowedSort = ['part_no', 'part_name'];
    if (!in_array($sort, $allowedSort)) {
        $sort = 'part_no';
    }

    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    $productQuery->orderBy($sort, $direction);

    /* =========================
     * PAGINATION
     * ========================= */
    $products = $productQuery->paginate($perPage, 'products');
    $pager    = $this->productModel->pager;

    /* =========================
     * PROCESS
     * ========================= */
    $processes = $this->processModel
        ->orderBy('id')
        ->findAll();

    /* =========================
     * FLOW MAP
     * ========================= */
    $flows = $this->flowModel
        ->where('is_active', 1)
        ->where('effective_date <=', $effectiveDate)
        ->orderBy('effective_date', 'DESC')
        ->findAll();

    $flowMap = [];
    foreach ($flows as $f) {
        $flowMap[$f['product_id']][$f['process_id']] = true;
    }

    return view('master/production_flow/index', [
        'products'       => $products,
        'processes'      => $processes,
        'flowMap'        => $flowMap,
        'effective_date' => $effectiveDate,
        'pager'          => $pager,
        'perPage'        => $perPage,
        'keyword'        => $keyword,
        'sort'           => $sort,
        'direction'      => $direction
    ]);
}



    /* =====================================================
     * SAVE – BULK CHECKLIST
     * ===================================================== */
    public function save()
    {
        $effectiveDate = $this->request->getPost('effective_date');
        $flows         = $this->request->getPost('flows');

        if (!$effectiveDate || !is_array($flows)) {
            return redirect()->back()
                ->with('error', 'Data flow tidak valid');
        }

        $this->flowModel->db->transBegin();

        try {

            foreach ($flows as $productId => $processIds) {

                /* =========================
                 * NONAKTIFKAN FLOW LAMA
                 * ========================= */
                $this->flowModel
                    ->where('product_id', $productId)
                    ->where('effective_date', $effectiveDate)
                    ->set(['is_active' => 0])
                    ->update();

                /* =========================
                 * INSERT FLOW BARU
                 * ========================= */
                $sequence = 1;
                foreach ($processIds as $processId) {

                    $this->flowModel->insert([
                        'product_id'    => $productId,
                        'process_id'    => $processId,
                        'sequence'      => $sequence++,
                        'effective_date'=> $effectiveDate,
                        'is_active'     => 1
                    ]);
                }
            }

            $this->flowModel->db->transCommit();

            return redirect()->back()
                ->with('success', 'Production flow berhasil disimpan');

        } catch (\Throwable $e) {

            $this->flowModel->db->transRollback();

            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }
}
