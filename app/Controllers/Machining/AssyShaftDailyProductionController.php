<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftDailyProductionController extends BaseController
{
    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($shifts as &$shift) {

            /* ===== TIME SLOT ===== */
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()
                ->getResultArray();

            /* ===== TOTAL MENIT ===== */
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $s = strtotime($slot['time_start']);
                $e = strtotime($slot['time_end']);
                if ($e <= $s) $e += 86400;

                $slot['minute'] = ($e - $s) / 60;
                $totalMinute   += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            /* ===== ITEM DARI DAILY SCHEDULE MACHINING ===== */
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

            /* ===== HOURLY MAP – ASSY SHAFT ===== */
            $hourly = $db->table('machining_assy_shaft_hourly')
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

        return view('machining/assy_shaft/daily_production/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }

    /* =====================================================
     * STORE
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        if (empty($items)) {
            return redirect()->back()->with('error', 'Tidak ada data disimpan');
        }

        $db->transBegin();

        try {
            foreach ($items as $row) {

                if (
                    empty($row['date']) ||
                    empty($row['shift_id']) ||
                    empty($row['machine_id']) ||
                    empty($row['product_id']) ||
                    empty($row['time_slot_id'])
                ) {
                    continue;
                }

                $where = [
                    'production_date' => $row['date'],
                    'shift_id'        => $row['shift_id'],
                    'machine_id'      => $row['machine_id'],
                    'product_id'      => $row['product_id'],
                    'time_slot_id'    => $row['time_slot_id'],
                ];

                $data = [
                    'qty_fg'     => (int) ($row['ok'] ?? 0),
                    'qty_ng'     => (int) ($row['ng'] ?? 0),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $exist = $db->table('machining_assy_shaft_hourly')
                    ->where($where)
                    ->get()
                    ->getRowArray();

                if ($exist) {
                    $db->table('machining_assy_shaft_hourly')
                        ->where('id', $exist['id'])
                        ->update($data);
                } else {
                    $db->table('machining_assy_shaft_hourly')
                        ->insert(array_merge($where, $data));
                }
            }

            $db->transCommit();
            return redirect()->back()
                ->with('success', 'Assy Shaft hourly production berhasil disimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
