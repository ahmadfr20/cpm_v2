<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyBushingProductionShiftController extends BaseController
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
            ->get()->getResultArray();

        foreach ($shifts as &$shift) {

            /* =========================
             * ITEM SCHEDULE
             * ========================= */
            $items = $db->table('daily_schedule_items dsi')
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
                ->where('ds.section', 'Assy Bushing')
                ->orderBy('m.line_position')
                ->get()->getResultArray();

            /* =========================
             * ACTUAL (AGGREGATE HOURLY)
             * ========================= */
            foreach ($items as &$item) {

                $actual = $db->table('machining_assy_bushing_hourly')
                    ->select('
                        SUM(qty_fg) AS total_ok,
                        SUM(qty_ng) AS total_ng
                    ')
                    ->where('production_date', $date)
                    ->where('shift_id', $shift['id'])
                    ->where('machine_id', $item['machine_id'])
                    ->where('product_id', $item['product_id'])
                    ->get()->getRowArray();

                $item['ok'] = (int) ($actual['total_ok'] ?? 0);
                $item['ng'] = (int) ($actual['total_ng'] ?? 0);
            }

            $shift['items'] = $items;
        }

        return view('machining/assy_bushing/production_shift/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }
}
