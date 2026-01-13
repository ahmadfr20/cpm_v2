<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleResultController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        /**
         * DATA PLAN + ACTUAL + NG
         */
        $rows = $db->table('daily_schedule_items dsi')
            ->select('
                s.shift_name,
                ds.shift_id,
                m.line_position,
                m.machine_name,
                p.part_no,
                p.part_name,
                dsi.target_per_shift AS plan,
                IFNULL(SUM(mh.qty_fg),0) AS act,
                IFNULL(SUM(mh.qty_ng),0) AS ng
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
            ->groupBy('dsi.id')
            ->orderBy('ds.shift_id, m.line_position')
            ->get()->getResultArray();

        /**
         * GROUP PER SHIFT (UNTUK FOOTER)
         */
        $shiftSummary = [];
        foreach ($rows as $r) {
            $sid = $r['shift_id'];

            if (!isset($shiftSummary[$sid])) {
                $shiftSummary[$sid] = [
                    'shift_name' => $r['shift_name'],
                    'plan' => 0,
                    'act'  => 0,
                    'ng'   => 0
                ];
            }

            $shiftSummary[$sid]['plan'] += $r['plan'];
            $shiftSummary[$sid]['act']  += $r['act'];
            $shiftSummary[$sid]['ng']   += $r['ng'];
        }

        return view('machining/schedule/result', [
            'date'         => $date,
            'rows'         => $rows,
            'shiftSummary' => $shiftSummary
        ]);
    }
}
