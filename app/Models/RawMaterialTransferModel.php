<?php

namespace App\Models;

use CodeIgniter\Model;

class RawMaterialTransferModel extends Model
{
    protected $table = 'raw_material_transfers';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'transfer_date',
        'shift_id'
    ];
}
