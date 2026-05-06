<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\MasterOperatorModel;

class OperatorController extends BaseController
{
    protected $operatorModel;

    public function __construct()
    {
        $this->operatorModel = new MasterOperatorModel();
    }

    public function index()
    {
        $data = [
            'operators' => $this->operatorModel->orderBy('section', 'ASC')->orderBy('operator_name', 'ASC')->findAll(),
        ];
        return view('master/operator/index', $data);
    }

    public function store()
    {
        $this->operatorModel->save([
            'operator_name' => $this->request->getPost('operator_name'),
            'section'       => $this->request->getPost('section'),
        ]);

        return redirect()->to('/master/operator')->with('success', 'Data Operator berhasil ditambahkan.');
    }

    public function update($id)
    {
        $this->operatorModel->update($id, [
            'operator_name' => $this->request->getPost('operator_name'),
            'section'       => $this->request->getPost('section'),
        ]);

        return redirect()->to('/master/operator')->with('success', 'Data Operator berhasil diubah.');
    }

    public function delete($id)
    {
        $this->operatorModel->delete($id);
        return redirect()->to('/master/operator')->with('success', 'Data Operator berhasil dihapus.');
    }
}
