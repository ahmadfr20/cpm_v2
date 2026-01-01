<?php

namespace App\Models;

use CodeIgniter\Model;

class ShiftTimeSlotModel extends Model
{
    protected $table = 'shift_time_slots';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'shift_id',
        'time_slot_id'
    ];
}
