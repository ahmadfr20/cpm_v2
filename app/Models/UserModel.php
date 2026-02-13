<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table      = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'username',
        'fullname',
        'password',
        'role',
        'created_at',
    ];

    protected $useTimestamps = false;
}
