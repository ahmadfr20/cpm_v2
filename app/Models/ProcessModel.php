<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessModel extends Model
{
    protected $table      = 'production_processes';
    protected $primaryKey = 'id';
    protected $allowedFields = ['process_code','process_name'];
    protected $returnType = 'array';
}
