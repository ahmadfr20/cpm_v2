<?php

namespace App\Models;

use CodeIgniter\Model;

class NgCategoryModel extends Model
{
    protected $table            = 'ng_categories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'process_name',
        'ng_code',
        'ng_name',
        'is_active',
    ];

    protected $useTimestamps    = false; // kalau tabel kamu ada created_at/updated_at, ubah ke true
    // protected $createdField  = 'created_at';
    // protected $updatedField  = 'updated_at';
}
