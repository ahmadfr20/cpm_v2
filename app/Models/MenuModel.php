<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuModel extends Model
{
    protected $table      = 'menus';
    protected $primaryKey = 'id';
    protected $allowedFields = ['parent_id','name','route','icon','sort_order'];
    protected $returnType = 'array';
}
