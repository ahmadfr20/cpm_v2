<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        /**
         * SHIFT MACHINING (MC)
         */
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        /**
         * MESIN MACHINING
         */
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        /**
         * PLAN EXISTING
         */
        $existing = $db->table('daily_schedule_items dsi')
            ->select('
                ds.shift_id,
                dsi.machine_id,
                dsi.product_id,
                dsi.cycle_time,
                dsi.target_per_shift
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Machining')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'].'_'.$e['machine_id']] = $e;
        }

        /**
         * ACTUAL & NG (DARI HOURLY)
         */
        $actuals = $db->table('machining_hourly')
            ->select('
                shift_id,
                machine_id,
                product_id,
                SUM(qty_fg) act,
                SUM(qty_ng) ng
            ')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()->getResultArray();

        $actualMap = [];
        foreach ($actuals as $a) {
            $actualMap[
                $a['shift_id'].'_'.$a['machine_id'].'_'.$a['product_id']
            ] = $a;
        }

        return view('machining/schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /**
     * =========================
     * AJAX: PRODUCT + TARGET
     * =========================
     */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = $this->request->getGet('machine_id');
        $shiftId   = $this->request->getGet('shift_id');

        if (!$machineId || !$shiftId) {
            return $this->response->setJSON([]);
        }

        /**
         * TOTAL DETIK SHIFT
         */
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()->getResultArray();

        $totalSecond = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400;
            $totalSecond += ($end - $start);
        }

        /**
         * PRODUCT SESUAI MESIN
         */
        $products = $db->table('machine_products mp')
            ->select('
                p.id,
                p.part_no,
                p.part_name,
                ps.cycle_time_sec
            ')
            ->join('products p', 'p.id = mp.product_id')
            ->join(
                'production_standards ps',
                'ps.product_id = p.id AND ps.machine_id = mp.machine_id',
                'left'
            )
            ->where('mp.machine_id', $machineId)
            ->where('mp.is_active', 1)
            ->get()->getResultArray();

        foreach ($products as &$p) {
            $p['target'] = !empty($p['cycle_time_sec'])
                ? min(floor($totalSecond / $p['cycle_time_sec']), 1200)
                : 0;
        }

        return $this->response->setJSON($products);
    }

    /**
     * =========================
     * STORE
     * =========================
     */
    public function store()
    {
        $db      = db_connect();
        $date    = $this->request->getPost('date');
        $shiftId = $this->request->getPost('shift_id');

        $db->table('daily_schedules')->insert([
            'schedule_date' => $date,
            'shift_id'      => $shiftId,
            'section'       => 'Machining',
            'created_at'    => date('Y-m-d H:i:s')
        ]);

        $scheduleId = $db->insertID();

        foreach ($this->request->getPost('items') as $row) {
            if (empty($row['product_id'])) continue;

            $db->table('daily_schedule_items')->insert([
                'daily_schedule_id' => $scheduleId,
                'machine_id'        => $row['machine_id'],
                'product_id'        => $row['product_id'],
                'cycle_time'        => $row['cycle_time'],
                'target_per_shift'  => min((int)$row['plan'], 1200)
            ]);
        }

        return redirect()->back()
            ->with('success', 'Daily schedule machining tersimpan');
    }
}
