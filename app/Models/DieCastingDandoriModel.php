<?php

namespace App\Models;

use CodeIgniter\Model;

class DieCastingDandoriModel extends Model
{
    protected $table = 'die_casting_dandori';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'dandori_date',
        'shift_id',
        'machine_id',
        'product_id',
        'activity',
        'created_at'
    ];
}
