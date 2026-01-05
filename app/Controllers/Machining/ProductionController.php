<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // ================= SHIFT =================
        $shifts = $db->table('shifts')
            ->orderBy('id')
            ->get()
            ->getResultArray();

        // ================= PLAN + ACTUAL =================
        $rows = $db->table('daily_schedule_items dsi')
            ->select('
                ds.shift_id,
                s.shift_name,
                m.machine_code,
                p.part_no,
                p.part_name,
                dsi.target_per_shift,
                IFNULL(SUM(mh.qty_fg),0) AS total_fg,
                IFNULL(SUM(mh.qty_ng),0) AS total_ng,
                IFNULL(SUM(mh.downtime),0) AS total_downtime
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('shifts s', 's.id = ds.shift_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->join('products p', 'p.id = dsi.product_id')
            ->join(
                'machining_hourly mh',
                'mh.production_date = ds.schedule_date
                 AND mh.shift_id = ds.shift_id
                 AND mh.machine_id = dsi.machine_id
                 AND mh.product_id = dsi.product_id',
                'left'
            )
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Machining')
            ->groupBy('ds.shift_id, dsi.machine_id, dsi.product_id')
            ->orderBy('s.id, m.machine_code')
            ->get()
            ->getResultArray();

        // ================= FORMAT PER SHIFT =================
        $data = [];
        foreach ($rows as $r) {
            $data[$r['shift_id']][] = $r;
        }

        return view('machining/production/index', [
            'date'   => $date,
            'shifts' => $shifts,
            'data'   => $data
        ]);
    }
}
