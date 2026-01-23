<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class LeakTestProductionController extends BaseController
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
             * TARGET (DAILY SCHEDULE)
             * ========================= */
            $targets = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.machine_id,
                    m.machine_code,
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
                ->where('ds.section', 'Leak Test')
                ->orderBy('m.line_position')
                ->get()->getResultArray();

            /* =========================
             * ACTUAL (LEAK TEST HOURLY)
             * ========================= */
            $actuals = $db->table('machining_leak_test_hourly')
                ->select('
                    machine_id,
                    product_id,
                    SUM(qty_ok) total_ok,
                    SUM(qty_ng) total_ng
                ')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->groupBy('machine_id, product_id')
                ->get()->getResultArray();

            $actualMap = [];
            foreach ($actuals as $a) {
                $actualMap[$a['machine_id'].'_'.$a['product_id']] = $a;
            }

            /* =========================
             * MERGE TARGET + ACTUAL
             * ========================= */
            $shift['rows'] = [];

            foreach ($targets as $t) {

                $key = $t['machine_id'].'_'.$t['product_id'];
                $ok  = $actualMap[$key]['total_ok'] ?? 0;
                $ng  = $actualMap[$key]['total_ng'] ?? 0;

                $total = $ok + $ng;
                $eff   = $t['target_per_shift'] > 0
                    ? round(($ok / $t['target_per_shift']) * 100, 1)
                    : 0;

                $shift['rows'][] = [
                    'machine_code'     => $t['machine_code'],
                    'part_no'          => $t['part_no'],
                    'part_name'        => $t['part_name'],
                    'target_per_shift' => $t['target_per_shift'],
                    'ok'               => $ok,
                    'ng'               => $ng,
                    'total'            => $total,
                    'eff'              => $eff
                ];
            }
        }

        return view('machining/leak_test/production_shift/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }
}
