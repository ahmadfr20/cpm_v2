<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        return view('dashboard/index', [
            'date'      => $date,
            'asakai'    => $this->getAsakai($db, $date),
            'wip'       => $this->getWIP($db),
            'inventory' => $this->getInventory($db),
        ]);
    }

    /* ================= ASAKAI ================= */
    private function getAsakai($db, $date)
    {
        $summary = function ($process) use ($db, $date) {
            return $db->table('production_outputs po')
                ->selectSum('po.qty_ok')
                ->selectSum('po.qty_ng')
                ->join('production_processes pp', 'pp.id = po.process_id')
                ->where('pp.process_name', $process)
                ->where('po.production_date', $date)
                ->get()->getRowArray();
        };

        return [
            'die_casting' => $summary('Die Casting'),
            'shot_blast'  => $summary('Shot Blast'),
            'machining'   => $summary('Machining'),
        ];
    }

    /* ================= WIP ================= */
    private function getWIP($db)
    {
        return $db->query("
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
    }

    /* ================= INVENTORY ================= */
    private function getInventory($db)
    {
        return $db->query("
            SELECT p.part_no, p.part_name,
            SUM(CASE WHEN mt.transaction_type='RAW_IN' THEN mt.qty ELSE 0 END) -
            SUM(CASE WHEN mt.transaction_type='TRANSFER' THEN mt.qty ELSE 0 END) AS raw_stock,
            SUM(CASE WHEN mt.transaction_type='FINISH_GOOD' THEN mt.qty ELSE 0 END) -
            SUM(CASE WHEN mt.transaction_type='DELIVERY' THEN mt.qty ELSE 0 END) AS fg_stock
            FROM material_transactions mt
            JOIN products p ON p.id = mt.product_id
            GROUP BY p.id
        ")->getResultArray();
    }
}
