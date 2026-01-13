<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        /**
         * 🔥 SHIFT MACHINING SAJA (MC)
         */
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        $result = [];

        /* ===== GRAND TOTAL ===== */
        $grandTarget = 0;
        $grandFG     = 0;
        $grandNG     = 0;
        $grandDT     = 0;

        foreach ($shifts as $shift) {

            /**
             * ================= JAM SHIFT
             */
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()
                ->getResultArray();

            $startTime = null;
            $endTime   = null;

            foreach ($slots as $s) {
                if (!$startTime || $s['time_start'] < $startTime) {
                    $startTime = $s['time_start'];
                }
                if (!$endTime || $s['time_end'] > $endTime) {
                    $endTime = $s['time_end'];
                }
            }

            /**
             * ================= DATA PLAN + ACTUAL
             */
            $rows = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.id              AS schedule_item_id,
                    dsi.target_per_shift,
                    m.id                AS machine_id,
                    m.machine_code,
                    m.line_position,
                    p.id                AS product_id,
                    p.part_no,
                    p.part_name,
                    IFNULL(SUM(mh.qty_fg),0) AS fg,
                    IFNULL(SUM(mh.qty_ng),0) AS ng,
                    IFNULL(SUM(mh.downtime),0) AS downtime
                ')
                ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->join('machines m', 'm.id = dsi.machine_id')
                ->join('products p', 'p.id = dsi.product_id')
                ->join(
                    'machining_hourly mh',
                    'mh.machine_id = dsi.machine_id
                     AND mh.product_id = dsi.product_id
                     AND mh.shift_id = ds.shift_id
                     AND mh.production_date = "'.$date.'"',
                    'left'
                )
                ->where('ds.schedule_date', $date)
                ->where('ds.section', 'Machining')
                ->where('ds.shift_id', $shift['id'])
                ->groupBy('dsi.id')
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            /**
             * ================= TOTAL SHIFT
             */
            $totalTarget = 0;
            $totalFG     = 0;
            $totalNG     = 0;
            $totalDT     = 0;

            foreach ($rows as $r) {
                $totalTarget += (int)$r['target_per_shift'];
                $totalFG     += (int)$r['fg'];
                $totalNG     += (int)$r['ng'];
                $totalDT     += (int)$r['downtime'];
            }

            /* ===== GRAND ===== */
            $grandTarget += $totalTarget;
            $grandFG     += $totalFG;
            $grandNG     += $totalNG;
            $grandDT     += $totalDT;

            $result[] = [
                'shift'       => $shift,
                'slots'       => $slots,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'rows'        => $rows,
                'totalTarget' => $totalTarget,
                'totalFG'     => $totalFG,
                'totalNG'     => $totalNG,
                'totalDT'     => $totalDT,
                'efficiency'  => $totalTarget > 0
                    ? round(($totalFG / $totalTarget) * 100, 1)
                    : 0,
                'canEdit'     => $this->canEdit($date, $startTime, $endTime)
            ];
        }

        /* ================= DAILY EFFICIENCY ================= */
        $dailyEfficiency = $grandTarget > 0
            ? round(($grandFG / $grandTarget) * 100, 1)
            : 0;

        return view('machining/production/index', [
            'date'            => $date,
            'operator'        => $operator,
            'data'            => $result,
            'dailyTarget'     => $grandTarget,
            'dailyFG'         => $grandFG,
            'dailyNG'         => $grandNG,
            'dailyDT'         => $grandDT,
            'dailyEfficiency' => $dailyEfficiency
        ]);
    }

    /**
     * ================= WINDOW KOREKSI
     */
    private function canEdit($date, $start, $end)
    {
        if (!$start || !$end) return false;

        $now   = strtotime(date('Y-m-d H:i:s'));
        $start = strtotime("$date $start");
        $end   = strtotime("$date $end");

        if ($end <= $start) {
            $end += 86400; // shift malam
        }

        return ($now >= ($end - 3600) && $now <= ($end + 3600));
    }
}
