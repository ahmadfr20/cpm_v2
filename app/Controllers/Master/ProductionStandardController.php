<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\ProductionStandardModel;
use App\Models\MachineModel;
use App\Models\ProductModel;

class ProductionStandardController extends BaseController
{
    protected $standardModel;
    protected $machineModel;
    protected $productModel;

    public function __construct()
    {
        $this->standardModel = new ProductionStandardModel();
        $this->machineModel  = new MachineModel();
        $this->productModel  = new ProductModel();
    }

    /* =========================
     * LIST
     * ========================= */
    public function index()
    {
        return view('master/production_standard/index', [
            'standards' => $this->standardModel->getAllWithRelation()
        ]);
    }

    /* =========================
     * CREATE
     * ========================= */
    public function create()
    {
        return view('master/production_standard/create', [
            'machines' => $this->machineModel->findAll(),
            'products' => $this->productModel->findAll()
        ]);
    }

    /* =========================
     * STORE
     * ========================= */
    public function store()
    {
        $exists = $this->standardModel
            ->where([
                'machine_id' => $this->request->getPost('machine_id'),
                'product_id' => $this->request->getPost('product_id')
            ])->first();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'Standard untuk machine & product ini sudah ada');
        }

        $this->standardModel->insert([
            'machine_id'     => $this->request->getPost('machine_id'),
            'product_id'     => $this->request->getPost('product_id'),
            'cycle_time_sec' => $this->request->getPost('cycle_time_sec'),
            'cavity'         => $this->request->getPost('cavity'),
            'effective_rate' => $this->request->getPost('effective_rate') ?? 1
        ]);

        return redirect()->to('/master/production-standard')
            ->with('success', 'Production standard berhasil ditambahkan');
    }

    /* =========================
     * EDIT
     * ========================= */
    public function edit($id)
    {
        return view('master/production_standard/edit', [
            'standard' => $this->standardModel->find($id),
            'machines' => $this->machineModel->findAll(),
            'products' => $this->productModel->findAll()
        ]);
    }

    /* =========================
     * UPDATE
     * ========================= */
    public function update($id)
    {
        $this->standardModel->update($id, [
            'cycle_time_sec' => $this->request->getPost('cycle_time_sec'),
            'cavity'         => $this->request->getPost('cavity'),
            'effective_rate' => $this->request->getPost('effective_rate')
        ]);

        return redirect()->to('/master/production-standard')
            ->with('success', 'Production standard berhasil diupdate');
    }

    /* =========================
     * DELETE
     * ========================= */
    public function delete($id)
    {
        $this->standardModel->delete($id);

        return redirect()->to('/master/production-standard')
            ->with('success', 'Production standard berhasil dihapus');
    }
}
