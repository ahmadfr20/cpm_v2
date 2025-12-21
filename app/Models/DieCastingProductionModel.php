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
        'product_id',
        'qty_ok',
        'qty_ng',
        'created_at'
    ];
}
