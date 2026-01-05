<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        /* ===============================
           AMBIL SEMUA SHIFT AKTIF
        =============================== */
        $shifts = $db->table('shifts s')
            ->select('s.id, s.shift_name')
            ->where('s.is_active', 1)
            ->orderBy('s.id')
            ->get()->getResultArray();

        /* ===============================
           LOOP PER SHIFT
        =============================== */
        $result = [];

        foreach ($shifts as $shift) {

            // ===============================
            // AMBIL JAM SHIFT DARI TIME SLOT
            // ===============================
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->get()->getResultArray();

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

            /* ===============================
               DATA SCHEDULED PART
            =============================== */
            $rows = $db->table('daily_schedule_items dsi')
                ->select('
                    m.machine_code,
                    m.line_position,
                    p.part_no,
                    p.part_name,
                    dsi.target_per_shift,
                    IFNULL(SUM(h.qty_fg),0) fg,
                    IFNULL(SUM(h.qty_ng),0) ng,
                    IFNULL(SUM(h.downtime_minute),0) downtime
                ')
                ->join('daily_schedules ds', 'ds.id=dsi.daily_schedule_id')
                ->join('machines m', 'm.id=dsi.machine_id')
                ->join('products p', 'p.id=dsi.product_id')
                ->join(
                    'die_casting_hourly h',
                    'h.machine_id=dsi.machine_id
                     AND h.product_id=dsi.product_id
                     AND h.shift_id=ds.shift_id
                     AND h.production_date="'.$date.'"',
                    'left'
                )
                ->where('ds.schedule_date', $date)
                ->where('ds.section', 'Die Casting')
                ->where('ds.shift_id', $shift['id'])
                ->groupBy('dsi.id')
                ->orderBy('m.line_position')
                ->get()->getResultArray();

            /* ===============================
               HITUNG TOTAL SHIFT
            =============================== */
            $totalTarget = 0;
            $totalFG     = 0;

            foreach ($rows as $r) {
                $totalTarget += (int)$r['target_per_shift'];
                $totalFG     += (int)$r['fg'];
            }

            $efficiency = $totalTarget > 0
                ? round(($totalFG / $totalTarget) * 100, 1)
                : 0;

            $result[] = [
                'shift'       => $shift,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'rows'        => $rows,
                'totalTarget' => $totalTarget,
                'totalFG'     => $totalFG,
                'efficiency'  => $efficiency,
                'canEdit'     => $this->canEdit($endTime)
            ];
        }

        return view('die_casting/production/index', [
            'date'  => $date,
            'data'  => $result
        ]);
    }

    /* ===============================
       OPERATOR HANYA BOLEH EDIT AKHIR SHIFT
    =============================== */
    private function canEdit($endTime)
    {
        if (!$endTime) return false;

        $now = strtotime(date('H:i:s'));
        $end = strtotime($endTime);

        return ($now >= ($end - 3600) && $now <= ($end + 3600));
    }
}
