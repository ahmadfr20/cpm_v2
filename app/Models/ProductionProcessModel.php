<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionProcessModel extends Model
{
    protected $table      = 'production_processes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['process_code', 'process_name'];
}
