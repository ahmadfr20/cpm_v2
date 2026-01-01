<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\ShiftModel;
use App\Models\TimeSlotModel;
use App\Models\ShiftTimeSlotModel;

class ShiftController extends BaseController
{
    protected $shift;
    protected $timeSlot;
    protected $pivot;

    public function __construct()
    {
        $this->shift = new ShiftModel();
        $this->timeSlot = new TimeSlotModel();
        $this->pivot = new ShiftTimeSlotModel();
    }

    public function index()
    {
        return view('master/shift/index', [
            'shifts' => $this->shift->getWithTimeSlots()
        ]);
    }

    public function create()
    {
        return view('master/shift/create', [
            'timeSlots' => $this->timeSlot->orderBy('time_start')->findAll()
        ]);
    }

    public function store()
    {
        $shiftId = $this->shift->insert([
            'shift_code' => $this->request->getPost('shift_code'),
            'shift_name' => $this->request->getPost('shift_name'),
            'is_active'  => 1
        ]);

        foreach ($this->request->getPost('time_slots') as $ts) {
            $this->pivot->insert([
                'shift_id'     => $shiftId,
                'time_slot_id'=> $ts
            ]);
        }

        return redirect()->to('/master/shift')
            ->with('success', 'Shift berhasil disimpan');
    }

    public function edit($id)
    {
        $selected = array_column(
            $this->pivot->where('shift_id', $id)->findAll(),
            'time_slot_id'
        );

        return view('master/shift/edit', [
            'shift'      => $this->shift->find($id),
            'timeSlots'  => $this->timeSlot->orderBy('time_start')->findAll(),
            'selected'   => $selected
        ]);
    }

    public function update($id)
    {
        $this->shift->update($id, [
            'shift_code' => $this->request->getPost('shift_code'),
            'shift_name' => $this->request->getPost('shift_name'),
            'is_active'  => $this->request->getPost('is_active')
        ]);

        $this->pivot->where('shift_id', $id)->delete();

        foreach ($this->request->getPost('time_slots') as $ts) {
            $this->pivot->insert([
                'shift_id' => $id,
                'time_slot_id' => $ts
            ]);
        }

        return redirect()->to('/master/shift')
            ->with('success', 'Shift berhasil diperbarui');
    }
}
