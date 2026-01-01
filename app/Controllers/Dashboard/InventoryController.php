<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class InventoryController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $inventory = $db->query("
            SELECT p.part_no, p.part_name,
            SUM(CASE WHEN mt.transaction_type='RAW_IN' THEN mt.qty ELSE 0 END) -
            SUM(CASE WHEN mt.transaction_type='TRANSFER' THEN mt.qty ELSE 0 END) AS raw_stock,
            SUM(CASE WHEN mt.transaction_type='FINISH_GOOD' THEN mt.qty ELSE 0 END) -
            SUM(CASE WHEN mt.transaction_type='DELIVERY' THEN mt.qty ELSE 0 END) AS fg_stock
            FROM material_transactions mt
            JOIN products p ON p.id = mt.product_id
            GROUP BY p.id
        ")->getResultArray();

        return view('dashboard/inventory/index', compact('inventory'));
    }
}
