<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\MachineModel;

class MachineController extends BaseController
{
    protected $machineModel;

    public function __construct()
    {
        $this->machineModel = new MachineModel();
    }

    public function index()
    {
        $keyword = $this->request->getGet('keyword');
        $line    = $this->request->getGet('production_line');

        return view('master/machine/index', [
            'machines' => $this->machineModel->getMachines($keyword, $line),
            'lines'    => $this->machineModel->getLines(),
            'keyword'  => $keyword,
            'line'     => $line
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
