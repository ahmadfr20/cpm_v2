<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\MachineModel;
use App\Models\ProductModel;
use App\Models\MachineProductModel;

class MachineController extends BaseController
{
    protected $machineModel;
    protected $productModel;
    protected $machineProductModel;

    public function __construct()
    {
        $this->machineModel = new MachineModel();
        $this->productModel = new ProductModel();
        $this->machineProductModel = new MachineProductModel();
    }

    public function index()
    {
        return view('master/machine/index', [
            'machines' => $this->machineModel->getMachinesWithProducts(
                $this->request->getGet('keyword'),
                $this->request->getGet('production_line')
            ),
            'lines' => $this->machineModel->getLines(),
            'keyword' => $this->request->getGet('keyword'),
            'line' => $this->request->getGet('production_line')
        ]);
    }

    public function create()
    {
        return view('master/machine/create');
    }

    public function store()
    {
        $this->machineModel->insert([
            'machine_code'    => $this->request->getPost('machine_code'),
            'machine_name'    => $this->request->getPost('machine_name'),
            'production_line' => $this->request->getPost('production_line'),
            'line_position'   => $this->request->getPost('line_position'),
        ]);

        return redirect()->to('/master/machine')
            ->with('success', 'Machine berhasil ditambahkan');
    }

    public function edit($id)
    {
        return view('master/machine/edit', [
            'machine' => $this->machineModel->find($id)
        ]);
    }

    public function update($id)
    {
        $this->machineModel->update($id, [
            'machine_code'    => $this->request->getPost('machine_code'),
            'machine_name'    => $this->request->getPost('machine_name'),
            'production_line' => $this->request->getPost('production_line'),
            'line_position'   => $this->request->getPost('line_position'),
        ]);

        return redirect()->to('/master/machine')
            ->with('success', 'Machine berhasil diupdate');
    }

    public function delete($id)
    {
        $this->machineModel->delete($id);
        return redirect()->to('/master/machine')
            ->with('success', 'Machine berhasil dihapus');
    }
}
