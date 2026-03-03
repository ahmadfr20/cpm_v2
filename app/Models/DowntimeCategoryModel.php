<?php

namespace App\Models;

use CodeIgniter\Model;

class DowntimeCategoryModel extends Model
{
    protected $table            = 'downtime_categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // Field diperbarui menjadi process_id
    protected $allowedFields    = [
        'process_id', 
        'downtime_code', 
        'downtime_name', 
        'is_active'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}