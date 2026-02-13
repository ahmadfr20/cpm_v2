<?php

namespace App\Models;

use CodeIgniter\Model;

class ShiftModel extends Model
{
    protected $table = 'shifts';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'shift_code',
        'shift_name',
        'day_group',
        'is_active',
    ];

    protected $useTimestamps = false;

    public function getWithTimeSlots()
    {
        return $this->select('
                shifts.*,
                MIN(ts.time_start) AS shift_start,
                MAX(ts.time_end)   AS shift_end
            ')
            ->join('shift_time_slots sts', 'sts.shift_id = shifts.id', 'left')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id', 'left')
            ->groupBy('shifts.id')
            ->findAll();
    }

    public static function dayGroupLabel(string $val): string
    {
        return match ($val) {
            'MON_THU' => 'Senin - Kamis',
            'FRI'     => 'Jumat',
            'SAT'     => 'Sabtu',
            'SUN'     => 'Minggu',
            default   => $val,
        };
    }
}
