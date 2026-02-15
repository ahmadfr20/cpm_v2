<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorModel extends Model
{
    protected $table      = 'vendors';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'vendor_code',      // accurate (manual)
        'vendor_code_app',  // app code (auto)
        'vendor_name',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    public function getVendors($keyword = null)
    {
        $builder = $this->builder();

        if ($keyword) {
            $builder->groupStart()
                ->like('vendor_code', $keyword)
                ->orLike('vendor_code_app', $keyword)
                ->orLike('vendor_name', $keyword)
                ->orLike('address', $keyword)
                ->groupEnd();
        }

        return $builder
            ->orderBy('vendor_name')
            ->get()
            ->getResultArray();
    }
}
