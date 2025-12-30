<?php

namespace App\Models;

use CodeIgniter\Model;

class DieCastingProductionModel extends Model
{
    protected $table = 'die_casting_production';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'production_date',
        'shift_id',
        'machine_id',
        'product_id',
        'qty_p',
        'qty_a',
        'qty_ng',
        'weight_kg'
    ];
}
