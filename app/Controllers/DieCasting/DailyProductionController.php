<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        // ===== SHIFT DIE CASTING SAJA =====
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // ===== NG CATEGORY (DIE CASTING) =====
        $ngCategories = $db->table('ng_categories')
            ->where('process_name', 'Die Casting')
            ->orderBy('ng_code')
            ->get()
            ->getResultArray();


        foreach ($shifts as &$shift) {

            // ===== TIME SLOT =====
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()->getResultArray();

            // ===== TOTAL MENIT SHIFT =====
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $slot['minute'] = ($end - $start) / 60;
                $totalMinute += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            // ===== TARGET PER SHIFT =====
            $shift['items'] = $db->table('die_casting_production dcp')
                ->select('
                    dcp.machine_id,
                    m.machine_code,
                    dcp.product_id,
                    p.part_no,
                    p.part_name,
                    dcp.qty_p
                ')
                ->join('machines m', 'm.id = dcp.machine_id')
                ->join('products p', 'p.id = dcp.product_id')
                ->where('dcp.production_date', $date)
                ->where('dcp.shift_id', $shift['id'])
                ->where('dcp.qty_p >', 0)
                ->orderBy('m.line_position')
                ->get()->getResultArray();

            // ===== HOURLY MAP =====
            $hourly = $db->table('die_casting_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map']
                    [$h['machine_id']]
                    [$h['product_id']]
                    [$h['time_slot_id']] = $h;
            }
        }

        return view('die_casting/daily_production/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories
        ]);
    }

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        foreach ($items as $row) {
            $db->table('die_casting_hourly')->replace([
                'production_date' => $row['date'],
                'shift_id'        => $row['shift_id'],
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'time_slot_id'    => $row['time_slot_id'],
                'qty_fg'          => (int)($row['fg'] ?? 0),
                'qty_ng'          => (int)($row['ng'] ?? 0),
                'ng_category_id'  => $row['ng_category_id'] ?? null,
                'created_at'      => date('Y-m-d H:i:s')
            ]);
        }

        if (!empty($items)) {
            $this->syncDailyScheduleActual(
                $items[array_key_first($items)]['date'],
                $items[array_key_first($items)]['shift_id']
            );
        }

        return redirect()->back()->with('success', 'Daily production tersimpan');
    }

    private function syncDailyScheduleActual($date, $shiftId)
    {
        $db = db_connect();

        $actuals = $db->table('die_casting_hourly')
            ->select('machine_id, product_id,
                      SUM(qty_fg) total_fg,
                      SUM(qty_ng) total_ng')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->groupBy('machine_id, product_id')
            ->get()->getResultArray();

        foreach ($actuals as $a) {
            $db->table('die_casting_production')
                ->where([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $a['machine_id'],
                    'product_id'      => $a['product_id']
                ])
                ->update([
                    'qty_a'  => $a['total_fg'],
                    'qty_ng' => $a['total_ng']
                ]);
        }
    }
}
