<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\TimeSlotModel;

class TimeSlotController extends BaseController
{
    protected $timeSlotModel;

    public function __construct()
    {
        $this->timeSlotModel = new TimeSlotModel();
    }

    // LIST
    public function index()
    {
        return view('master/time_slot/index', [
            'timeSlots' => $this->timeSlotModel->findAll()
        ]);
    }

    // CREATE FORM
    public function create()
    {
        return view('master/time_slot/create');
    }

    // STORE
    public function store()
    {
        $this->timeSlotModel->insert([
            'time_code' => $this->request->getPost('time_code'),
            'time_name' => $this->request->getPost('time_name'),
            'start_time'=> $this->request->getPost('start_time'),
            'end_time'  => $this->request->getPost('end_time'),
        ]);

        return redirect()->to('/master/time-slot')
            ->with('success', 'Time Slot berhasil ditambahkan');
    }

    // EDIT FORM
    public function edit($id)
    {
        return view('master/time_slot/edit', [
            'timeSlot' => $this->timeSlotModel->find($id)
        ]);
    }

    // UPDATE
    public function update($id)
    {
        $this->timeSlotModel->update($id, [
            'time_code' => $this->request->getPost('time_code'),
            'time_name' => $this->request->getPost('time_name'),
            'start_time'=> $this->request->getPost('start_time'),
            'end_time'  => $this->request->getPost('end_time'),
        ]);

        return redirect()->to('/master/time-slot')
            ->with('success', 'Time Slot berhasil diupdate');
    }

    // DELETE
    public function delete($id)
    {
        $this->timeSlotModel->delete($id);

        return redirect()->to('/master/time-slot')
            ->with('success', 'Time Slot berhasil dihapus');
    }
}
