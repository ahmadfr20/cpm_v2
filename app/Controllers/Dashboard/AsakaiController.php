<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class AsakaiController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $casting   = $this->getEfficiency($db, 'Die Casting', $date);
        $machining = $this->getEfficiency($db, 'Machining', $date);

        return view('dashboard/asakai/index', [
            'date'      => $date,
            'casting'   => $casting,
            'machining' => $machining,
        ]);
    }

    private function getEfficiency($db, $process, $date)
    {
        $plan = $db->table('daily_schedules ds')
            ->selectSum('dsi.target_per_shift')
            ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id')
            ->where('ds.section', $process)
            ->where('ds.schedule_date', $date)
            ->get()->getRow()->target_per_shift ?? 0;

        $actual = $db->table('production_outputs po')
            ->selectSum('po.qty_ok')
            ->join('production_processes pp', 'pp.id = po.process_id')
            ->where('pp.process_name', $process)
            ->where('po.production_date', $date)
            ->get()->getRow()->qty_ok ?? 0;

        $eff = $plan > 0 ? round(($actual / $plan) * 100, 2) : 0;

        return compact('plan','actual','eff');
    }
}
