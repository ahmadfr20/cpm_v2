<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyProductionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        /**
         * 🔥 SHIFT MACHINING SAJA
         */
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($shifts as &$shift) {

            /**
             * TIME SLOT
             */
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()->getResultArray();

            /**
             * TOTAL MENIT SHIFT
             */
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $slot['minute'] = ($end - $start) / 60;
                $totalMinute += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            /**
             * 🔥 DAILY SCHEDULE MACHINING
             */
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
                ->where('dsi.target_per_shift >', 0)
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            /**
             * 🔥 HOURLY MAP
             */
            $hourly = $db->table('machining_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()
                ->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map']
                    [$h['machine_id']]
                    [$h['product_id']]
                    [$h['time_slot_id']] = $h;
            }
        }

        return view('machining/daily_production/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }

    /**
     * SIMPAN HOURLY
     */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        foreach ($items as $row) {
            $db->table('machining_hourly')->replace([
                'production_date' => $row['date'],
                'shift_id'        => $row['shift_id'],
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'time_slot_id'    => $row['time_slot_id'],
                'qty_fg'          => (int)($row['fg'] ?? 0),
                'qty_ng'          => (int)($row['ng'] ?? 0),
                'ng_category'     => $row['ng_remark'] ?? null,
                'created_at'      => date('Y-m-d H:i:s')
            ]);
        }

        return redirect()->back()
            ->with('success', 'Hourly production machining tersimpan');
    }
}
