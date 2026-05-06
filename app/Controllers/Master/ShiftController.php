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

            // Ambil slot sesuai urutan pivot — include is_break (raw query untuk hindari CI4 alias issue)
            $db = db_connect();
            $selected = $db->query(
                "SELECT sts.id AS pivot_id, sts.time_slot_id, sts.is_break,
                        ts.time_start, ts.time_end
                 FROM shift_time_slots sts
                 LEFT JOIN time_slots ts ON ts.id = sts.time_slot_id
                 WHERE sts.shift_id = ?
                 ORDER BY sts.id ASC",
                [$shiftId]
            )->getResultArray();

            $slots = [];
            $totalMinutes = 0;

            foreach ($selected as $s) {
                $st = substr((string)($s['time_start'] ?? ''), 0, 5);
                $en = substr((string)($s['time_end'] ?? ''), 0, 5);
                $isBreak = (int)($s['is_break'] ?? 0);

                $mins = 0;
                if ($st && $en) {
                    $sArr = explode(':', $st);
                    $eArr = explode(':', $en);
                    $mStart = ((int)$sArr[0] * 60) + (int)$sArr[1];
                    $mEnd   = ((int)$eArr[0] * 60) + (int)$eArr[1];
                    if ($mEnd <= $mStart) $mEnd += 1440;
                    $mins = $mEnd - $mStart;
                }

                $slots[] = [
                    'time_slot_id' => (int)($s['time_slot_id'] ?? 0),
                    'start'        => $st,
                    'end'          => $en,
                    'minutes'      => $mins,
                    'is_break'     => $isBreak,
                ];

                // Hanya hitung menit aktif (bukan istirahat)
                if (!$isBreak) $totalMinutes += $mins;
            }

            // Begin dan End secara presisi diambil dari array index pertama dan terakhir
            $begin = '-';
            $end   = '-';
            if (!empty($slots)) {
                $first = reset($slots);
                $last  = end($slots);
                $begin = $first['start'] ?: '-';
                $end   = $last['end'] ?: '-';
            }

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
        $id     = (int)$id;
        $slots  = $this->request->getPost('slots') ?? [];  // array of time_slot_id
        $db     = db_connect();

        // Hapus data pivot lama
        $db->table('shift_time_slots')->where('shift_id', $id)->delete();

        // Insert slot + is_break per slot
        foreach ($slots as $idx => $tsId) {
            $tsId = (int)$tsId;
            if ($tsId <= 0) continue;

            // Checkbox name: is_break_{idx}_{shiftId}
            $isBreak = (int)($this->request->getPost("is_break_{$idx}_{$id}") == '1');

            $db->table('shift_time_slots')->insert([
                'shift_id'     => $id,
                'time_slot_id' => $tsId,
                'is_break'     => $isBreak,
            ]);
        }

        return redirect()->to('/master/shift')->with('success', 'Time slot shift berhasil diperbarui');
    }
}