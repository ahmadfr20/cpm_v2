<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionOutputModel extends Model
{
    protected $table = 'production_outputs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'production_date',
        'shift_id',
        'time_slot_id',
        'product_id',
        'machine_id',
        'process_id',
        'qty_ok',
        'qty_ng'
    ];
}
