<?php

namespace App\Models;

use CodeIgniter\Model;

class UserPrivilegeModel extends Model
{
    protected $table      = 'user_privileges';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id','menu_id','can_read','can_create','can_update','can_delete','data_access','updated_at'
    ];
    protected $returnType = 'array';
}
