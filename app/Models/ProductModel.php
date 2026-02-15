<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table      = 'products';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'part_no',
        'part_name',
        'part_prod',
        'customer_id',

        // ✅ cycle time
        'cycle_time',            // CT die casting (existing)
        'cycle_time_machining',  // ✅ baru: CT machining

        'cavity',
        'efficiency_rate',

        // ✅ weight
        'weight_ascas',
        'weight_runner',
        'weight_die_casting', // auto dari ascas + runner
        'weight_machining',   // input user

        'notes',
        'is_active'
    ];

    protected $useTimestamps = true;

    /**
     * FILTER + PAGINATION
     */
    public function filterProducts($keyword = null, $customerId = null)
    {
        $builder = $this
            ->select('products.*, customers.customer_name')
            ->join('customers', 'customers.id = products.customer_id', 'left');

        if ($keyword) {
            $builder->groupStart()
                ->like('products.part_no', $keyword)
                ->orLike('products.part_name', $keyword)
                ->orLike('products.part_prod', $keyword) // ✅ bonus: cari juga part_prod
                ->groupEnd();
        }

        if ($customerId) {
            $builder->where('products.customer_id', $customerId);
        }

        return $builder
            ->where('products.is_active', 1)
            ->orderBy('products.part_name');
    }

    /**
     * PRODUCT BY MACHINE (UNTUK DAILY SCHEDULE)
     * ✅ ditambah CT machining biar bisa dipakai schedule juga
     */
    public function getByMachine($machineId)
    {
        return $this->db->table('machine_products mp')
            ->select('
                p.id,
                p.part_no,
                p.part_prod,
                p.part_name,

                p.weight_ascas,
                p.weight_runner,
                p.weight_die_casting,
                p.weight_machining,

                p.cycle_time,
                p.cycle_time_machining,

                p.cavity,
                p.efficiency_rate
            ')
            ->join('products p', 'p.id = mp.product_id')
            ->where('mp.machine_id', $machineId)
            ->where('mp.is_active', 1)
            ->where('p.is_active', 1)
            ->orderBy('p.part_name')
            ->get()
            ->getResultArray();
    }

    /**
     * GET 1 PRODUCT + CUSTOMER
     */
    public function getOneWithCustomer($id)
    {
        return $this->select('products.*, customers.customer_name')
            ->join('customers', 'customers.id = products.customer_id', 'left')
            ->where('products.id', $id)
            ->first();
    }
}
