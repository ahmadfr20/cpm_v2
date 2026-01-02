<?php

namespace App\Models;

use CodeIgniter\Model;

class MachiningHourlyModel extends Model
{
    protected $table = 'machining_hourly';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'production_date',
        'shift_id',
        'time_slot_id',
        'machine_id',
        'product_id',
        'qty_fg',
        'qty_ng',
        'ng_category',
        'downtime_minute',
        'remark'
    ];
}
