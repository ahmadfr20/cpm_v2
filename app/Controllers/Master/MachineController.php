<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\MachineModel;
use App\Models\ProductModel;
use App\Models\MachineProductModel;
use App\Models\ProductionProcessModel;

class MachineController extends BaseController
{
    protected $machineModel;
    protected $productModel;
    protected $machineProductModel;
    protected $processModel;

    public function __construct()
    {
        $this->machineModel        = new MachineModel();
        $this->productModel        = new ProductModel();
        $this->machineProductModel = new MachineProductModel();
        $this->processModel        = new ProductionProcessModel();
    }

    /* ===============================
     * LIST MACHINE
     * =============================== */
    public function index()
    {
        return view('master/machine/index', [
            'machines'  => $this->machineModel->getMachinesFiltered(),
            'keyword'   => '',
            'processId' => '',
            'processes' => $this->processModel->findAll(),
        ]);
    }

    /* ===============================
     * CREATE MACHINE (INI YANG HILANG)
     * =============================== */
    public function create()
    {
        return view('master/machine/create', [
            'processes' => $this->processModel->findAll()
        ]);
    }

    public function store()
    {
        $this->machineModel->insert([
            'machine_code'  => $this->request->getPost('machine_code'),
            'machine_name'  => $this->request->getPost('machine_name'),
            'process_id'    => $this->request->getPost('process_id'),
            'line_position' => $this->request->getPost('line_position'),
        ]);

        return redirect()->to('/master/machine')
            ->with('success', 'Machine berhasil ditambahkan');
    }

    /* ===============================
     * MANAGE PRODUCT (CHECKBOX)
     * =============================== */
    public function manageProducts($machineId)
    {
        $assigned = $this->machineProductModel
            ->where('machine_id', $machineId)
            ->findColumn('product_id');

        return view('master/machine/products', [
            'machine'  => $this->machineModel->find($machineId),
            'products' => $this->productModel->findAll(),
            'assigned' => $assigned ?? []
        ]);
    }

    /* ===============================
     * SAVE PRODUCT (BULK)
     * =============================== */
    public function saveProducts($machineId)
    {
        $selectedProducts = $this->request->getPost('products') ?? [];

        $existing = $this->machineProductModel
            ->where('machine_id', $machineId)
            ->findColumn('product_id') ?? [];

        // DELETE
        $toDelete = array_diff($existing, $selectedProducts);
        if ($toDelete) {
            $this->machineProductModel
                ->where('machine_id', $machineId)
                ->whereIn('product_id', $toDelete)
                ->delete();
        }

        // INSERT
        $toInsert = array_diff($selectedProducts, $existing);
        foreach ($toInsert as $productId) {
            $this->machineProductModel->insert([
                'machine_id' => $machineId,
                'product_id' => $productId,
                'is_active'  => 1
            ]);
        }

        return redirect()->to('/master/machine')
            ->with('success', 'Produk machine berhasil diperbarui');
    }

    /* ===============================
 * EDIT MACHINE
 * =============================== */
    public function edit($id)
    {
        $machine = $this->machineModel->find($id);

        if (!$machine) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Machine tidak ditemukan');
        }

        return view('master/machine/edit', [
            'machine'   => $machine,
            'processes' => $this->processModel->findAll(),
            'lines'     => ['Machining', 'Die Casting']
        ]);
    }

    /* ===============================
    * UPDATE MACHINE
    * =============================== */
    public function update($id)
    {
        $this->machineModel->update($id, [
            'machine_code'    => $this->request->getPost('machine_code'),
            'machine_name'    => $this->request->getPost('machine_name'),
            'process_id'      => $this->request->getPost('process_id'),
            'production_line' => $this->request->getPost('production_line'),
            'line_position'   => $this->request->getPost('line_position'),
        ]);

        return redirect()->to('/master/machine')
            ->with('success', 'Machine berhasil diperbarui');
    }

}
