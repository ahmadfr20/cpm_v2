<?php

namespace App\Models;

use CodeIgniter\Model;

class TimeSlotModel extends Model
{
    protected $table = 'time_slots';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'time_code',
        'time_name',
        'start_time',
        'end_time'
    ];
}
