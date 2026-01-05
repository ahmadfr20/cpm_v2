<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'customer_code',
        'customer_name',
        'address',
        'phone',
        'email'
    ];

    public function getCustomers($keyword = null)
    {
        $builder = $this->builder();

        if ($keyword) {
            $builder->groupStart()
                ->like('customer_code', $keyword)
                ->orLike('customer_name', $keyword)
                ->orLike('address', $keyword)
                ->groupEnd();
        }

        return $builder
            ->orderBy('customer_name')
            ->get()
            ->getResultArray();
    }
}
