<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\ShiftModel;

class ShiftController extends BaseController
{
    protected $shiftModel;

    public function __construct()
    {
        $this->shiftModel = new ShiftModel();
    }

    // LIST DATA
    public function index()
    {
        return view('master/shift/index', [
            'shifts' => $this->shiftModel->findAll()
        ]);
    }

    // FORM CREATE
    public function create()
    {
        return view('master/shift/create');
    }

    // SIMPAN DATA
    public function store()
    {
        $this->shiftModel->insert([
            'shift_code' => $this->request->getPost('shift_code'),
            'shift_name' => $this->request->getPost('shift_name'),
            'start_time' => $this->request->getPost('start_time'),
            'end_time'   => $this->request->getPost('end_time'),
        ]);

        return redirect()->to('/master/shift')
            ->with('success', 'Shift berhasil ditambahkan');
    }

    // FORM EDIT
    public function edit($id)
    {
        return view('master/shift/edit', [
            'shift' => $this->shiftModel->find($id)
        ]);
    }

    // UPDATE DATA
    public function update($id)
    {
        $this->shiftModel->update($id, [
            'shift_code' => $this->request->getPost('shift_code'),
            'shift_name' => $this->request->getPost('shift_name'),
            'start_time' => $this->request->getPost('start_time'),
            'end_time'   => $this->request->getPost('end_time'),
        ]);

        return redirect()->to('/master/shift')
            ->with('success', 'Shift berhasil diupdate');
    }

    // DELETE DATA
    public function delete($id)
    {
        $this->shiftModel->delete($id);

        return redirect()->to('/master/shift')
            ->with('success', 'Shift berhasil dihapus');
    }
}
