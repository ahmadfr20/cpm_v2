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

        /* =========================
        * TOTAL DETIK SHIFT
        * ========================= */
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

        /* =========================
        * PRODUCT DARI PRODUCTS
        * ========================= */
        $products = $db->table('machine_products mp')
            ->select('
                p.id,
                p.part_no,
                p.part_name,
                p.cycle_time,
                p.cavity,
                p.efficiency_rate
            ')
            ->join('products p', 'p.id = mp.product_id')
            ->where('mp.machine_id', $machineId)
            ->where('mp.is_active', 1)
            ->orderBy('p.part_no')
            ->get()
            ->getResultArray();

        /* =========================
        * HITUNG TARGET
        * ========================= */
        foreach ($products as &$p) {

            $cycle  = (int) $p['cycle_time'];
            $cavity = (int) $p['cavity'];
            $eff    = ((float) $p['efficiency_rate']) / 100;

            if ($cycle > 0 && $cavity > 0) {
                $p['target'] = min(
                    floor(($totalSecond / $cycle) * $cavity * $eff),
                    1200
                );
            } else {
                $p['target'] = 0;
            }
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
        $db    = db_connect();
        $date  = $this->request->getPost('date');
        $items = $this->request->getPost('items');

        if (!$date || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $db->transBegin();

        try {

            foreach ($items as $row) {

                if (
                    empty($row['shift_id']) ||
                    empty($row['machine_id']) ||
                    empty($row['product_id'])
                ) {
                    continue;
                }

                $shiftId   = (int) $row['shift_id'];
                $machineId = (int) $row['machine_id'];
                $productId = (int) $row['product_id'];

                /* =========================
                * MASTER PRODUCT
                * ========================= */
                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();

                if (!$product) continue;

                $cycle  = (int) $product['cycle_time'];        // detik
                $cavity = (int) $product['cavity'];
                $eff    = ((float) $product['efficiency_rate']) / 100;

                if ($cycle <= 0 || $cavity <= 0) continue;

                /* =========================
                * TOTAL DETIK SHIFT
                * ========================= */
                $slots = $db->table('shift_time_slots sts')
                    ->select('ts.time_start, ts.time_end')
                    ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                    ->where('sts.shift_id', $shiftId)
                    ->get()
                    ->getResultArray();

                $totalSecond = 0;
                foreach ($slots as $s) {
                    $start = strtotime($s['time_start']);
                    $end   = strtotime($s['time_end']);
                    if ($end <= $start) $end += 86400;
                    $totalSecond += ($end - $start);
                }

                if ($totalSecond <= 0) continue;

                /* =========================
                * TARGET PER HOUR & SHIFT
                * ========================= */
                $targetPerHour = floor(
                    (3600 / $cycle) * $cavity * $eff
                );

                $targetPerShift = min(
                    floor(($totalSecond / $cycle) * $cavity * $eff),
                    1200
                );

                if ($targetPerShift <= 0) continue;

                /* =========================
                * DAILY SCHEDULE HEADER
                * ========================= */
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining'
                    ])
                    ->get()
                    ->getRowArray();

                if (!$schedule) {
                    $db->table('daily_schedules')->insert([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                        'is_completed'  => 0,
                        'created_at'    => date('Y-m-d H:i:s')
                    ]);
                    $scheduleId = $db->insertID();
                } else {
                    $scheduleId = $schedule['id'];
                }

                /* =========================
                * DAILY SCHEDULE ITEM (UPSERT)
                * ========================= */
                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'machine_id'        => $machineId,
                        'product_id'        => $productId
                    ])
                    ->get()
                    ->getRowArray();

                $dataItem = [
                    'cycle_time'       => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'  => $targetPerHour,
                    'target_per_shift' => $targetPerShift,
                    'is_selected'      => 1
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')
                        ->where('id', $existItem['id'])
                        ->update($dataItem);
                } else {
                    $db->table('daily_schedule_items')->insert(
                        $dataItem + [
                            'daily_schedule_id' => $scheduleId,
                            'shift_id'          => $shiftId,
                            'machine_id'        => $machineId,
                            'product_id'        => $productId
                        ]
                    );
                }
            }

            $db->transCommit();
            return redirect()->back()
                ->with('success', 'Daily schedule Machining berhasil disimpan');

        } catch (\Throwable $e) {

            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }


}
