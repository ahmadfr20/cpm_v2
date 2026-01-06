<?php

namespace App\Models;

use CodeIgniter\Model;

class MachineModel extends Model
{
    protected $table = 'machines';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'machine_code',
        'machine_name',
        'process_id',
        'line_position',
    ];

    public function getMachinesFiltered($keyword = null, $processId = null)
    {
        $builder = $this->select('
                machines.*,
                COALESCE(production_processes.process_name, "-") AS process_name,
                GROUP_CONCAT(products.part_no SEPARATOR ", ") AS products
            ')
            ->join(
                'production_processes',
                'production_processes.id = machines.process_id',
                'left' // 🔴 PENTING
            )
            ->join(
                'machine_products mp',
                'mp.machine_id = machines.id',
                'left'
            )
            ->join(
                'products',
                'products.id = mp.product_id',
                'left'
            )
            ->groupBy('machines.id')
            ->orderBy('machines.line_position');

        if ($keyword) {
            $builder->groupStart()
                ->like('machines.machine_code', $keyword)
                ->orLike('machines.machine_name', $keyword)
                ->groupEnd();
        }

        if ($processId) {
            $builder->where('machines.process_id', $processId);
        }

        return $builder->findAll();
    }

}
