<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class WipController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $wip = $db->query("
            SELECT p.part_no, p.part_name,
            SUM(CASE WHEN pp.process_name='Die Casting' THEN po.qty_ok ELSE 0 END) -
            SUM(CASE WHEN pp.process_name='Shot Blast' THEN po.qty_ok ELSE 0 END) AS wip_shotblast,
            SUM(CASE WHEN pp.process_name='Shot Blast' THEN po.qty_ok ELSE 0 END) -
            SUM(CASE WHEN pp.process_name='Machining' THEN po.qty_ok ELSE 0 END) AS wip_machining
            FROM production_outputs po
            JOIN products p ON p.id = po.product_id
            JOIN production_processes pp ON pp.id = po.process_id
            GROUP BY p.id
        ")->getResultArray();

        return view('dashboard/wip/index', compact('wip'));
    }
}
