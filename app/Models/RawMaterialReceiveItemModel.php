<?php

namespace App\Models;

use CodeIgniter\Model;

class RawMaterialReceiveItemModel extends Model
{
    protected $table = 'raw_material_receive_items';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'raw_material_receive_id',
        'product_id',
        'qty_received',
        'qty_return'
    ];
}
