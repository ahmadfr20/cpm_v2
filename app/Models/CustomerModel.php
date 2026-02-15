<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table      = 'customers';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        // ✅ Accurate code (input bebas)
        'customer_code',

        // ✅ App internal code (auto generate: CUST-0001 dst)
        'customer_code_app',

        'customer_name',
        'address',
        'phone',
        'email',

        // kalau ada kolom status di table, aktifkan ini:
        'is_active',
    ];

    /**
     * Ambil list customer untuk dropdown / list biasa
     * - keyword mencari: Accurate code, App code, name, address
     * - urut by customer_name
     */
    public function getCustomers($keyword = null): array
    {
        $builder = $this->builder();

        $keyword = trim((string)$keyword);
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('customer_code', $keyword)        // Accurate
                ->orLike('customer_code_app', $keyword)  // App internal
                ->orLike('customer_name', $keyword)
                ->orLike('address', $keyword)
                ->groupEnd();
        }

        return $builder
            ->orderBy('customer_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
