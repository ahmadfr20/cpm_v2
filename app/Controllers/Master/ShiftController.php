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
        $this->shift    = new ShiftModel();
        $this->timeSlot = new TimeSlotModel();
        $this->pivot    = new ShiftTimeSlotModel();
    }

    public function index()
    {
        $shiftsRaw = $this->shift->getWithTimeSlots();
        $timeSlots = $this->timeSlot->orderBy('time_start', 'ASC')->findAll();

        $dayLabel = [
            'MON_THU' => 'mon-thu',
            'FRI'     => 'fri',
            'SAT'     => 'sat',
            'SUN'     => 'sun',
        ];

        $getSectionLabel = function (string $shiftName) {
            $name = strtolower($shiftName);
            if (strpos($name, 'dc') !== false || strpos($name, 'die') !== false) return 'die casting';
            if (strpos($name, 'mc') !== false || strpos($name, 'mach') !== false) return 'machining';
            return 'other';
        };

        $getShiftNo = function (array $row) {
            $name = (string)($row['shift_name'] ?? '');
            if (preg_match('/\bshift\s*([0-9]+)/i', $name, $m)) return (int)$m[1];
            return (int)($row['shift_code'] ?? 0);
        };

        $sectionPriority = ['die casting' => 1, 'machining' => 2, 'other' => 99];
        $dayPriority = ['MON_THU' => 1, 'FRI' => 2, 'SAT' => 3, 'SUN' => 4];

        usort($shiftsRaw, function ($a, $b) use ($getSectionLabel, $getShiftNo, $sectionPriority, $dayPriority) {
            $secA = $getSectionLabel((string)($a['shift_name'] ?? ''));
            $secB = $getSectionLabel((string)($b['shift_name'] ?? ''));
            $pA = $sectionPriority[$secA] ?? 99;
            $pB = $sectionPriority[$secB] ?? 99;
            if ($pA !== $pB) return $pA <=> $pB;

            $dgA = (string)($a['day_group'] ?? 'MON_THU');
            $dgB = (string)($b['day_group'] ?? 'MON_THU');
            $dpA = $dayPriority[$dgA] ?? 99;
            $dpB = $dayPriority[$dgB] ?? 99;
            if ($dpA !== $dpB) return $dpA <=> $dpB;

            $noA = $getShiftNo($a);
            $noB = $getShiftNo($b);
            if ($noA !== $noB) return $noA <=> $noB;

            return ((int)($a['shift_code'] ?? 0)) <=> ((int)($b['shift_code'] ?? 0));
        });

        $shifts = [];
        foreach ($shiftsRaw as $row) {
            $shiftId = (int)($row['id'] ?? 0);

            $selected = $this->pivot
                ->select('shift_time_slots.time_slot_id, time_slots.time_start, time_slots.time_end')
                ->join('time_slots', 'time_slots.id = shift_time_slots.time_slot_id', 'left')
                ->where('shift_time_slots.shift_id', $shiftId)
                ->orderBy('shift_time_slots.id', 'ASC')
                ->findAll();

            $slots = [];
            $totalMinutes = 0;

            for ($i = 1; $i <= 10; $i++) {
                $slots[$i] = ['time_slot_id'=>null,'start'=>'','end'=>'','minutes'=>0];
            }

            $idx = 1;
            foreach ($selected as $s) {
                if ($idx > 10) break;

                $start = (string)($s['time_start'] ?? '');
                $end   = (string)($s['time_end'] ?? '');

                $mins = 0;
                if ($start && $end) {
                    $t1 = strtotime($start);
                    $t2 = strtotime($end);
                    if ($t1 !== false && $t2 !== false && $t2 > $t1) $mins = (int)(($t2 - $t1) / 60);
                }

                $slots[$idx] = [
                    'time_slot_id' => (int)($s['time_slot_id'] ?? 0) ?: null,
                    'start'        => $start ? substr($start, 0, 5) : '',
                    'end'          => $end ? substr($end, 0, 5) : '',
                    'minutes'      => $mins,
                ];

                $totalMinutes += $mins;
                $idx++;
            }

            $begin = '';
            $end = '';
            foreach ($slots as $sl) { if ($sl['start']) { $begin = $sl['start']; break; } }
            for ($i=10; $i>=1; $i--) { if ($slots[$i]['end']) { $end = $slots[$i]['end']; break; } }

            $shifts[] = [
                'id'            => $shiftId,
                'section'       => $getSectionLabel((string)($row['shift_name'] ?? '')),
                'day_group'     => (string)($row['day_group'] ?? 'MON_THU'),
                'days_label'    => $dayLabel[(string)($row['day_group'] ?? 'MON_THU')] ?? 'mon-thu',
                'shift_no'      => $getShiftNo($row),
                'begin'         => $begin,
                'end'           => $end,
                'total_minutes' => $totalMinutes,
                'slots'         => $slots,
                'shift_name'    => (string)($row['shift_name'] ?? ''),
                'shift_code'    => (string)($row['shift_code'] ?? ''),
            ];
        }

        return view('master/shift/index', [
            'shifts'       => $shifts,
            'timeSlots'    => $timeSlots,
            'newShiftId'   => (int)(session()->getFlashdata('new_shift_id') ?? 0),
        ]);
    }

    /**
     * Tambah shift dari modal (langsung balik index)
     * Slot belum diisi => row kosong akan muncul
     */
    public function storeFromIndex()
    {
        $shiftCode = trim((string)$this->request->getPost('shift_code'));
        $shiftName = trim((string)$this->request->getPost('shift_name'));
        $dayGroup  = (string)($this->request->getPost('day_group') ?? 'MON_THU');

        if ($shiftCode === '' || $shiftName === '') {
            return redirect()->to('/master/shift')->with('error', 'Shift code & shift name wajib diisi.');
        }

        $shiftId = $this->shift->insert([
            'shift_code' => $shiftCode,
            'shift_name' => $shiftName,
            'day_group'  => $dayGroup,
            'is_active'  => 1,
        ]);

        return redirect()->to('/master/shift')
            ->with('success', 'Shift berhasil ditambahkan. Silakan isi slot pada row baru.')
            ->with('new_shift_id', $shiftId);
    }

    public function updateSlots($id)
    {
        $id = (int)$id;
        $slots = $this->request->getPost('slots') ?? [];

        $this->pivot->where('shift_id', $id)->delete();

        for ($i = 1; $i <= 10; $i++) {
            $tsId = (int)($slots[$i] ?? 0);
            if ($tsId > 0) {
                $this->pivot->insert([
                    'shift_id'     => $id,
                    'time_slot_id' => $tsId,
                ]);
            }
        }

        return redirect()->to('/master/shift')->with('success', 'Time slot shift berhasil diperbarui');
    }
}
