<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductProcessFlowModel extends Model
{
    protected $table      = 'product_process_flows';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'product_id',
        'process_id',
        'sequence',
        'is_active'
    ];

    protected $useTimestamps = true;

    /* =====================================
     * FLOW PER PRODUCT (ORDERED)
     * ===================================== */
    public function getFlowByProduct($productId)
    {
        return $this->select('
                product_process_flows.*,
                production_processes.process_name
            ')
            ->join(
                'production_processes',
                'production_processes.id = product_process_flows.process_id'
            )
            ->where('product_process_flows.product_id', $productId)
            ->where('product_process_flows.is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->findAll();
    }

    /* =====================================
     * FIRST PROCESS
     * ===================================== */
    public function getFirstProcess($productId)
    {
        return $this->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->first();
    }

    /* =====================================
     * NEXT PROCESS
     * ===================================== */
    public function getNextProcess($productId, $currentProcessId)
    {
        $current = $this->where([
            'product_id' => $productId,
            'process_id' => $currentProcessId,
            'is_active'  => 1
        ])->first();

        if (!$current) {
            return null;
        }

        return $this->where('product_id', $productId)
            ->where('sequence', $current['sequence'] + 1)
            ->where('is_active', 1)
            ->first();
    }
}
