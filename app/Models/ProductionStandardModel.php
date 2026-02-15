<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionStandardModel extends Model
{
    protected $table      = 'production_standards';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'product_id',
        'machine_id',
        'cycle_time_sec',
        'cycle_time_die_casting_sec',
        'cycle_time_machining_sec',
        'created_at',
    ];

    public function getWithRelation()
    {
        return $this->select('
                production_standards.*,
                machines.machine_code,
                machines.production_line,
                products.part_no,
                products.part_name,
                products.weight_ascas,
                products.weight_runner,
                products.weight_die_casting,
                products.weight_machining
            ')
            ->join('machines', 'machines.id = production_standards.machine_id', 'left')
            ->join('products', 'products.id = production_standards.product_id', 'left')
            ->orderBy('production_standards.id', 'DESC');
    }
}
