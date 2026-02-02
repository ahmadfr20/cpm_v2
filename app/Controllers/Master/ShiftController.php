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
    $shifts = $this->shift->getWithTimeSlots();

    // urutan section yang diinginkan (bisa kamu ubah)
    $sectionPriority = [
        'DC' => 1,
        'MC' => 2,
    ];

    $getSection = function (string $name) use ($sectionPriority) {
        foreach ($sectionPriority as $key => $prio) {
            if (stripos($name, $key) !== false) return [$key, $prio];
        }
        return ['OTHER', 99];
    };

    $getShiftNo = function (array $row) {
        $name = (string)($row['shift_name'] ?? '');
        if (preg_match('/\bshift\s*([0-9]+)/i', $name, $m)) {
            return (int)$m[1];
        }
        return (int)($row['shift_code'] ?? 0);
    };

    usort($shifts, function ($a, $b) use ($getSection, $getShiftNo) {
        [$secA, $prioA] = $getSection((string)($a['shift_name'] ?? ''));
        [$secB, $prioB] = $getSection((string)($b['shift_name'] ?? ''));

        // 1) section priority
        if ($prioA !== $prioB) return $prioA <=> $prioB;

        // 2) shift number within section
        $noA = $getShiftNo($a);
        $noB = $getShiftNo($b);
        if ($noA !== $noB) return $noA <=> $noB;

        // 3) fallback: kode
        return ((int)($a['shift_code'] ?? 0)) <=> ((int)($b['shift_code'] ?? 0));
    });

    return view('master/shift/index', [
        'shifts' => $shifts
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
