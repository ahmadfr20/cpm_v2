<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    /* ============================================
     * Helper: ambil process_id Machining
     * ============================================ */
    private function getProcessIdMachining($db): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Machining')
            ->get()
            ->getRowArray();

        if (!$row) {
            throw new \Exception('Process "Machining" belum ada di master production_processes');
        }

        return (int)$row['id'];
    }

    /* ============================================
     * INDEX
     * ============================================ */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // SHIFT MACHINING (MC)
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        // MESIN MACHINING (berdasarkan process_id = Machining)
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        // PLAN EXISTING (dari daily_schedules + daily_schedule_items)
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
            ->get()
            ->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            // 1 row per shift+machine
            $planMap[$e['shift_id'].'_'.$e['machine_id']] = $e;
        }

        // ACTUAL & NG (dari machining_hourly)
        $actuals = $db->table('machining_hourly')
            ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()
            ->getResultArray();

        $actualMap = [];
        foreach ($actuals as $a) {
            $actualMap[$a['shift_id'].'_'.$a['machine_id'].'_'.$a['product_id']] = $a;
        }

        return view('machining/schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /* ============================================
     * AJAX: PRODUCT + TARGET (FILTER by FLOW)
     * ============================================ */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = (int) $this->request->getGet('machine_id');
        $shiftId   = (int) $this->request->getGet('shift_id');

        if ($machineId <= 0 || $shiftId <= 0) {
            return $this->response->setJSON([]);
        }

        // process_id Machining
        $processIdMC = $this->getProcessIdMachining($db);

        /* ===== TOTAL DETIK SHIFT ===== */
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

        if ($totalSecond <= 0) {
            return $this->response->setJSON([]);
        }

        /* ==================================================
         * PRODUK BERDASARKAN FLOW + FILTER MESIN
         * - wajib punya process Machining di product_process_flows (active)
         * - optional: batasi hanya produk yang boleh di mesin (machine_products)
         * ================================================== */
        $products = $db->table('product_process_flows ppf')
            ->select('
                p.id,
                p.part_no,
                p.part_name,
                p.cycle_time,
                p.cavity,
                p.efficiency_rate
            ')
            ->join('products p', 'p.id = ppf.product_id')
            ->join(
                'machine_products mp',
                'mp.product_id = p.id AND mp.machine_id = ' . (int)$machineId . ' AND mp.is_active = 1',
                'inner'
            )
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdMC)   // <<< kunci: hanya produk yang memang ada Machining di flow
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        /* ===== HITUNG TARGET ===== */
        foreach ($products as &$p) {
            $cycle  = (int)($p['cycle_time'] ?? 0);   // detik
            $cavity = (int)($p['cavity'] ?? 0);

            $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            if ($cycle > 0 && $cavity > 0) {
                $targetShift = floor(($totalSecond / $cycle) * $cavity * $eff);
                $targetHour  = floor((3600 / $cycle) * $cavity * $eff);

                $p['target_per_shift'] = min((int)$targetShift, 1200);
                $p['target_per_hour']  = (int)$targetHour;
            } else {
                $p['target_per_shift'] = 0;
                $p['target_per_hour']  = 0;
            }
        }
        unset($p);

        return $this->response->setJSON($products);
    }

    /* ============================================
     * STORE (UPSERT: 1 row per machine per shift per date)
     * ============================================ */
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
            $processIdMC = $this->getProcessIdMachining($db);

            foreach ($items as $row) {
                if (
                    empty($row['shift_id']) ||
                    empty($row['machine_id']) ||
                    empty($row['product_id'])
                ) {
                    continue;
                }

                $shiftId   = (int)$row['shift_id'];
                $machineId = (int)$row['machine_id'];
                $productId = (int)$row['product_id'];

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) {
                    continue;
                }

                /* ===== VALIDASI: product harus punya Machining di flow ===== */
                $flowOk = $db->table('product_process_flows')
                    ->where('product_id', $productId)
                    ->where('process_id', $processIdMC)
                    ->where('is_active', 1)
                    ->countAllResults();

                if (!$flowOk) {
                    // kalau tidak sesuai flow, skip agar data tidak “nyasar”
                    continue;
                }

                /* ===== MASTER PRODUCT ===== */
                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();

                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);

                $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                if ($cycle <= 0 || $cavity <= 0) continue;

                /* ===== TOTAL DETIK SHIFT ===== */
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

                /* ===== TARGET ===== */
                $targetPerHour  = (int) floor((3600 / $cycle) * $cavity * $eff);
                $targetPerShift = (int) min(
                    floor(($totalSecond / $cycle) * $cavity * $eff),
                    1200
                );
                if ($targetPerShift <= 0) continue;

                /* ===== DAILY SCHEDULE HEADER ===== */
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                        // kalau tabelmu punya process_id, boleh tambahkan:
                        // 'process_id' => $processIdMC
                    ])
                    ->get()
                    ->getRowArray();

                if (!$schedule) {
                    $insertHeader = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                        'is_completed'  => 0,
                        'created_at'    => date('Y-m-d H:i:s')
                    ];

                    // jika kolom process_id memang ada di daily_schedules, aktifkan baris ini:
                    if ($db->fieldExists('process_id', 'daily_schedules')) {
                        $insertHeader['process_id'] = $processIdMC;
                    }

                    $db->table('daily_schedules')->insert($insertHeader);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];
                }

                /* ======================================================
                 * DAILY SCHEDULE ITEM UPSERT (1 row per machine)
                 * - jangan kunci by product_id (supaya ganti part tidak bikin row baru)
                 * ====================================================== */
                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'machine_id'        => $machineId
                    ])
                    ->get()
                    ->getRowArray();

                $dataItem = [
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $targetPerShift,
                    'is_selected'       => 1
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')
                        ->where('id', $existItem['id'])
                        ->update($dataItem);
                } else {
                    $db->table('daily_schedule_items')->insert(
                        $dataItem + [
                            'daily_schedule_id' => $scheduleId
                        ]
                    );
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily schedule Machining berhasil disimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
