<?php

namespace App\Models;

use CodeIgniter\Model;

class MachineProductModel extends Model
{
    protected $table = 'machine_products';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'machine_id',
        'product_id',
        'is_active'
    ];

    public function getAssignedProductIds($machineId)
    {
        return array_column(
            $this->where('machine_id', $machineId)->findAll(),
            'product_id'
        );
    }
}
