<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        /* ================= SHIFT AKTIF ================= */
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get()->getResultArray();

        $result = [];

        /* ===== GRAND TOTAL (HARIAN) ===== */
        $grandTarget = 0;
        $grandFG     = 0;
        $grandWeight = 0;

        foreach ($shifts as $shift) {

            /* ================= JAM SHIFT ================= */
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

            /* ================= DATA ================= */
            $rows = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.id schedule_item_id,
                    m.machine_code,
                    m.line_position,
                    p.part_no,
                    p.part_name,
                    p.weight,
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

            /* ================= TOTAL SHIFT ================= */
            $totalTarget = 0;
            $totalFG     = 0;
            $totalWeight = 0;

            foreach ($rows as &$r) {
                $totalTarget += (int)$r['target_per_shift'];
                $totalFG     += (int)$r['fg'];
                $r['total_weight'] = $r['fg'] * $r['weight'];
                $totalWeight += $r['total_weight'];
            }
            unset($r);

            $grandTarget += $totalTarget;
            $grandFG     += $totalFG;
            $grandWeight += $totalWeight;

            $result[] = [
                'shift'       => $shift,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'rows'        => $rows,
                'totalTarget' => $totalTarget,
                'totalFG'     => $totalFG,
                'totalWeight' => $totalWeight,
                'efficiency'  => $totalTarget > 0 ? round(($totalFG / $totalTarget) * 100, 1) : 0,
                'canEdit'     => $this->canEdit($date, $startTime, $endTime)
            ];
        }

        return view('die_casting/production/index', [
            'date'            => $date,
            'data'            => $result,
            'dailyTarget'     => $grandTarget,
            'dailyFG'         => $grandFG,
            'dailyWeight'     => $grandWeight,
            'dailyEfficiency' => $grandTarget > 0 ? round(($grandFG / $grandTarget) * 100, 1) : 0
        ]);
    }

    /* ================= KOREKSI WINDOW ================= */
    private function canEdit($date, $start, $end)
    {
        if (!$start || !$end) return false;

        $now   = strtotime(date('Y-m-d H:i:s'));
        $start = strtotime("$date $start");
        $end   = strtotime("$date $end");

        // SHIFT MALAM
        if ($end <= $start) {
            $end += 86400;
        }

        return ($now >= ($end - 3600) && $now <= ($end + 3600));
    }

    /* ================= SIMPAN KOREKSI ================= */
    public function saveCorrection()
    {
        $db = db_connect();

        foreach ($this->request->getPost('items') as $item) {
            $db->table('die_casting_hourly')
                ->where('id', $item['hourly_id'])
                ->update([
                    'qty_fg'          => $item['fg'],
                    'qty_ng'          => $item['ng'],
                    'downtime_minute' => $item['downtime']
                ]);
        }

        return redirect()->back()->with('success', 'Koreksi produksi berhasil disimpan');
    }
}
