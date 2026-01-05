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
        'production_line',
        'process_id',
        'line_position'
    ];

    public function getMachinesWithProducts($keyword = null, $line = null)
    {
        $builder = $this->db->table('machines m')
            ->select('
                m.*,
                GROUP_CONCAT(p.part_no SEPARATOR ", ") AS products
            ')
            ->join('machine_products mp', 'mp.machine_id = m.id', 'left')
            ->join('products p', 'p.id = mp.product_id', 'left')
            ->groupBy('m.id')
            ->orderBy('m.production_line')
            ->orderBy('m.line_position');

        if ($keyword) {
            $builder->groupStart()
                ->like('m.machine_code', $keyword)
                ->orLike('m.machine_name', $keyword)
                ->groupEnd();
        }

        if ($line) {
            $builder->where('m.production_line', $line);
        }

        return $builder->get()->getResultArray();
    }

    public function getLines()
    {
        return $this->select('production_line')
            ->groupBy('production_line')
            ->findAll();
    }
}
