<?php

namespace App\Models;

use CodeIgniter\Model;

class RawMaterialReceiveModel extends Model
{
    protected $table = 'raw_material_receives';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'receive_date',
        'shift_id',
        'po_number',
        'supplier_id',
        'do_number'
    ];
}
