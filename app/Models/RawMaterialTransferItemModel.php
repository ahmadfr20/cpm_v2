<?php

namespace App\Models;

use CodeIgniter\Model;

class RawMaterialTransferItemModel extends Model
{
    protected $table = 'raw_material_transfer_items';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'raw_material_transfer_id',
        'product_id',
        'qty_transfer'
    ];
}
