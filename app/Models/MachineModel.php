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
        'production_line'
    ];

    public function getMachines($keyword = null, $line = null)
    {
        $builder = $this->builder();

        if ($keyword) {
            $builder->groupStart()
                ->like('machine_code', $keyword)
                ->orLike('machine_name', $keyword)
                ->groupEnd();
        }

        if ($line) {
            $builder->where('production_line', $line);
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
