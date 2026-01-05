<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'part_no',
        'part_name',
        'customer_id',
        'weight',
        'notes'
    ];

    /**
     * LIST PRODUCT (FILTER + JOIN CUSTOMER)
     */
    public function getProducts($keyword = null, $customerId = null)
    {
        $builder = $this->db->table('products p')
            ->select('
                p.*,
                c.customer_name
            ')
            ->join('customers c', 'c.id = p.customer_id', 'left');

        if ($keyword) {
            $builder->groupStart()
                ->like('p.part_no', $keyword)
                ->orLike('p.part_name', $keyword)
                ->groupEnd();
        }

        if ($customerId) {
            $builder->where('p.customer_id', $customerId);
        }

        return $builder
            ->orderBy('p.part_name')
            ->get()
            ->getResultArray();
    }

    /**
     * PRODUCT BY MACHINE (UNTUK DAILY SCHEDULE)
     */
    public function getByMachine($machineId)
    {
        return $this->db->table('machine_products mp')
            ->select('p.id, p.part_no, p.part_name')
            ->join('products p', 'p.id = mp.product_id')
            ->where('mp.machine_id', $machineId)
            ->where('mp.is_active', 1)
            ->orderBy('p.part_name')
            ->get()
            ->getResultArray();
    }
}
