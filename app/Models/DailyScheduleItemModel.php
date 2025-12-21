<?php

namespace App\Models;

use CodeIgniter\Model;

class DailyScheduleItemModel extends Model
{
    protected $table = 'daily_schedule_items';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'daily_schedule_id',
        'machine_id',
        'product_id',
        'cycle_time',
        'cavity',
        'target_per_hour',
        'target_per_shift'
    ];
}
