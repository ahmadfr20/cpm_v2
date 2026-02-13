<?php

namespace App\Models;

use CodeIgniter\Model;

class UserProcessModel extends Model
{
    protected $table      = 'user_processes';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id','process_id'];
    protected $returnType = 'array';
}
