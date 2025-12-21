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

    public function getProducts($keyword = null, $customerId = null)
    {
        $builder = $this->db->table('products p')
            ->select('p.*, c.customer_name')
            ->join('customers c', 'c.id = p.customer_id');

        if ($keyword) {
            $builder->groupStart()
                ->like('p.part_no', $keyword)
                ->orLike('p.part_name', $keyword)
                ->groupEnd();
        }

        if ($customerId) {
            $builder->where('p.customer_id', $customerId);
        }

        return $builder->get()->getResultArray();
    }
}
