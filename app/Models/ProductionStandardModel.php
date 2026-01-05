<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionStandardModel extends Model
{
    protected $table = 'production_standards';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'machine_id',
        'product_id',
        'cycle_time_sec',
        'cavity',
        'effective_rate',
        'created_at',
        'updated_at'
    ];

    /* ==========================================
     * GET 1 STANDARD (UNTUK HITUNG TARGET)
     * ========================================== */
    public function getStandard($machineId, $productId)
    {
        return $this->where('machine_id', $machineId)
                    ->where('product_id', $productId)
                    ->first();
    }

    /* ==========================================
     * GET PRODUCT BY MACHINE (UNTUK DAILY SCHEDULE)
     * ========================================== */
    public function getProductsByMachine($machineId)
    {
        return $this->db->table('production_standards ps')
            ->select('p.id, p.part_no, p.part_name')
            ->join('products p', 'p.id = ps.product_id')
            ->where('ps.machine_id', $machineId)
            ->orderBy('p.part_no')
            ->get()
            ->getResultArray();
    }

    /* ==========================================
     * ✅ INI YANG KURANG & MENYEBABKAN ERROR
     * UNTUK HALAMAN MASTER PRODUCTION STANDARD
     * ========================================== */
    public function getAllWithRelation()
    {
        return $this->db->table('production_standards ps')
            ->select('
                ps.id,
                ps.cycle_time_sec,
                ps.cavity,
                ps.effective_rate,
                m.machine_code,
                m.machine_name,
                m.production_line,
                p.part_no,
                p.part_name
            ')
            ->join('machines m', 'm.id = ps.machine_id')
            ->join('products p', 'p.id = ps.product_id')
            ->orderBy('m.machine_code')
            ->orderBy('p.part_no')
            ->get()
            ->getResultArray();
    }
}
