<?php

namespace App\Models;

use CodeIgniter\Model;

class DailyScheduleModel extends Model
{
    protected $table = 'daily_schedules';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'schedule_date',
        'shift_id',
        'section',
        'is_completed',
        'created_at',
        'updated_at'
    ];
}
