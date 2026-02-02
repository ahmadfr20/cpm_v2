<?php

namespace App\Models;

use CodeIgniter\Model;

class MachineModel extends Model
{
    protected $table      = 'machines';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'machine_code',
        'machine_name',
        'process_id',
        'line_position',
    ];

    /**
     * ✅ RETURN MODEL CHAIN (builder) untuk paginate()
     * Controller bisa: $this->machineModel->getMachinesFilteredBuilder(...)->paginate(...)
     */
    public function getMachinesFilteredBuilder($keyword = null, $processId = null)
    {
        // reset query biar tidak numpuk kondisi sebelumnya
        $this->resetQuery();

        $this->select('
                machines.*,
                COALESCE(pp.process_name, "-") AS process_name,
                GROUP_CONCAT(p.part_no SEPARATOR ", ") AS products
            ')
            ->join('production_processes pp', 'pp.id = machines.process_id', 'left')
            ->join('machine_products mp', 'mp.machine_id = machines.id', 'left')
            ->join('products p', 'p.id = mp.product_id', 'left')
            ->groupBy('machines.id')
            ->orderBy('machines.line_position', 'ASC');

        if (!empty($keyword)) {
            $this->groupStart()
                ->like('machines.machine_code', $keyword)
                ->orLike('machines.machine_name', $keyword)
                ->groupEnd();
        }

        if (!empty($processId)) {
            $this->where('machines.process_id', (int)$processId);
        }

        // ✅ penting: return $this, bukan findAll()
        return $this;
    }

    /**
     * (Opsional) kalau masih ada bagian lain yang butuh array (tanpa paginate)
     */
    public function getMachinesFiltered($keyword = null, $processId = null)
    {
        return $this->getMachinesFilteredBuilder($keyword, $processId)->findAll();
    }
}
