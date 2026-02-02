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

    // LIST + FILTER + PAGINATION
    public function index()
    {
        $perPageOptions = [10, 25, 50, 100];

        $perPage = (int)($this->request->getGet('perPage') ?? 10);
        if (!in_array($perPage, $perPageOptions, true)) $perPage = 10;

        $q = trim((string)($this->request->getGet('q') ?? ''));

        $builder = $this->timeSlotModel;

        // Search (kode)
        if ($q !== '') {
            $builder = $builder->like('time_code', $q);
        }

        // Order rapi
        $builder = $builder->orderBy('time_start', 'ASC')->orderBy('time_code', 'ASC');

        $timeSlots = $builder->paginate($perPage, 'timeslots');

        return view('master/time_slot/index', [
            'timeSlots'      => $timeSlots,
            'pager'          => $this->timeSlotModel->pager,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'q'              => $q,
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
            'time_code'  => $this->request->getPost('time_code'),
            'time_start' => $this->request->getPost('time_start'),
            'time_end'   => $this->request->getPost('time_end'),
        ]);

        return redirect()->to('/master/time-slot')->with('success', 'Time Slot berhasil ditambahkan');
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
            'time_code'  => $this->request->getPost('time_code'),
            'time_start' => $this->request->getPost('time_start'),
            'time_end'   => $this->request->getPost('time_end'),
        ]);

        return redirect()->to('/master/time-slot')->with('success', 'Time Slot berhasil diupdate');
    }

    // DELETE
    public function delete($id)
    {
        $this->timeSlotModel->delete($id);

        return redirect()->to('/master/time-slot')->with('success', 'Time Slot berhasil dihapus');
    }
}
