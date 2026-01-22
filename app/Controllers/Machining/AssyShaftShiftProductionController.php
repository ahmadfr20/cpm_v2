<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftShiftProductionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        /* =========================
         * SHIFT MACHINING
         * ========================= */
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($shifts as &$shift) {

            /* =========================
             * DATA SCHEDULE
             * ========================= */
            $shift['items'] = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.machine_id,
                    m.machine_code,
                    m.line_position,
                    dsi.product_id,
                    p.part_no,
                    p.part_name,
                    dsi.target_per_shift
                ')
                ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->join('machines m', 'm.id = dsi.machine_id')
                ->join('products p', 'p.id = dsi.product_id')
                ->where('ds.schedule_date', $date)
                ->where('ds.shift_id', $shift['id'])
                ->where('ds.section', 'Machining')
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            /* =========================
             * AGGREGATE HOURLY → SHIFT
             * ========================= */
            $hourly = $db->table('machining_assy_shaft_hourly')
                ->select('
                    machine_id,
                    product_id,
                    SUM(qty_fg) AS total_fg,
                    SUM(qty_ng) AS total_ng
                ')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->groupBy('machine_id, product_id')
                ->get()
                ->getResultArray();

            $shift['result_map'] = [];
            foreach ($hourly as $h) {
                $shift['result_map']
                    [$h['machine_id']]
                    [$h['product_id']] = $h;
            }
        }

        return view('machining/assy_shaft/shift_production/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }
}
