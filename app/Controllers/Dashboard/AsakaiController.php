<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class AsakaiController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        return view('dashboard/asakai/index', [
            'date'        => $date,
            'dieCasting'  => $this->summary($db, 'Die Casting', $date),
            'machining'   => $this->summary($db, 'Machining', $date),
        ]);
    }

    private function summary($db, $section, $date)
    {
        /* ================= PLAN ================= */
        $planRow = $db->table('daily_schedules ds')
            ->select('SUM(dsi.target_per_shift) AS target')
            ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id')
            ->where('ds.section', $section)
            ->where('ds.schedule_date', $date)
            ->get()
            ->getRowArray();

        $target = (int) ($planRow['target'] ?? 0);

        /* ================= ACTUAL ================= */
        $actualRow = $db->table('production_outputs po')
            ->select('
                SUM(po.qty_ok) AS fg,
                SUM(po.qty_ng) AS ng
            ')
            ->join('production_processes pp', 'pp.id = po.process_id')
            ->where('pp.process_name', $section)
            ->where('po.production_date', $date)
            ->get()
            ->getRowArray();

        $fg = (int) ($actualRow['fg'] ?? 0);
        $ng = (int) ($actualRow['ng'] ?? 0);

        /* ================= EFF ================= */
        $eff = $target > 0 ? round(($fg / $target) * 100, 1) : 0;

        return compact('target','fg','ng','eff');
    }
}
