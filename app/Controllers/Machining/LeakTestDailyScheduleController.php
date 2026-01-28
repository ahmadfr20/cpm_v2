<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class LeakTestDailyScheduleController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // Mesin Leak Test: kamu pakai mesin machining (tetap)
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Leak Test')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        $actuals = $db->table('machining_leak_test_hourly')
            ->select('shift_id, machine_id, product_id, SUM(qty_ok) act, SUM(qty_ng) ng')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()->getResultArray();

        $actualMap = [];
        foreach ($actuals as $a) {
            $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
        }

        return view('machining/leak_test_schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /**
     * AJAX: PRODUCT + TARGET
     * ✅ Produk hanya yang punya flow (product_process_flows) untuk process Leak Test
     * ✅ Aman dari mismatch "LEAK TEST" vs "Leak Test"
     * ✅ Kalau error, tetap return JSON (bukan HTML) agar UI tidak blank
     */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = (int) ($this->request->getGet('machine_id') ?? 0);
        $shiftId   = (int) ($this->request->getGet('shift_id') ?? 0);

        if (!$machineId || !$shiftId) {
            return $this->jsonFail('machine_id / shift_id kosong', 400);
        }

        try {
            // ✅ Paling aman: pakai process_code LT
            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT');
            if (!$leakTestProcessId) {
                // fallback by name (case insensitive)
                $leakTestProcessId = $this->getProcessIdByNameLike($db, 'LEAK TEST');
            }
            if (!$leakTestProcessId) {
                return $this->jsonFail('Process Leak Test tidak ditemukan (cek production_processes)', 404);
            }

            // total detik shift
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
             * 1) Prioritas: produk yang terdaftar di machine_products
             * + wajib ada flow Leak Test (product_process_flows)
             */
            $products = $db->table('machine_products mp')
                ->distinct()
                ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
                ->join('products p', 'p.id = mp.product_id')
                ->join('product_process_flows ppf', 'ppf.product_id = p.id', 'inner')
                ->where('mp.machine_id', $machineId)
                ->where('mp.is_active', 1)
                ->where('p.is_active', 1)
                ->where('ppf.process_id', $leakTestProcessId)
                ->orderBy('p.part_no')
                ->get()->getResultArray();

            /**
             * 2) Fallback: kalau machine_products kosong,
             * tampilkan semua produk yang punya flow Leak Test
             */
            if (empty($products)) {
                $products = $db->table('products p')
                    ->distinct()
                    ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
                    ->join('product_process_flows ppf', 'ppf.product_id = p.id', 'inner')
                    ->where('p.is_active', 1)
                    ->where('ppf.process_id', $leakTestProcessId)
                    ->orderBy('p.part_no')
                    ->get()->getResultArray();
            }

            foreach ($products as &$p) {
                $cycle  = (int) $p['cycle_time'];
                $cavity = (int) $p['cavity'];
                $eff    = ((float) $p['efficiency_rate']) / 100;

                $p['cycle_time'] = $cycle;
                $p['target'] = ($cycle > 0 && $cavity > 0 && $totalSecond > 0)
                    ? min((int) floor(($totalSecond / $cycle) * $cavity * $eff), 1200)
                    : 0;
            }

            return $this->response->setJSON([
                'ok'      => true,
                'items'   => $products,
                'message' => empty($products) ? 'Tidak ada produk dengan flow Leak Test' : 'OK'
            ]);

        } catch (\Throwable $e) {
            // ✅ supaya FE tidak dapat HTML error (yang bikin JSON parse gagal)
            return $this->jsonFail('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * STORE (SCHEDULE + WIP) — tetap seperti sebelumnya,
     * tapi process id leak test pakai code LT agar match DB kamu.
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
            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT')
                ?? $this->getProcessIdByNameLike($db, 'LEAK TEST');

            if (!$leakTestProcessId) {
                throw new \RuntimeException('Process Leak Test tidak ditemukan (cek production_processes)');
            }

            foreach ($items as $row) {
                if (empty($row['shift_id']) || empty($row['machine_id']) || empty($row['product_id'])) {
                    continue;
                }

                $shiftId   = (int) $row['shift_id'];
                $machineId = (int) $row['machine_id'];
                $productId = (int) $row['product_id'];

                // ✅ wajib punya flow leak test
                $hasFlow = $db->table('product_process_flows')
                    ->where(['product_id' => $productId, 'process_id' => $leakTestProcessId])
                    ->countAllResults();

                if (!$hasFlow) continue;

                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()->getRowArray();

                if (!$product) continue;

                $cycle  = (int) $product['cycle_time'];
                $cavity = (int) $product['cavity'];
                $eff    = ((float) $product['efficiency_rate']) / 100;
                if ($cycle <= 0 || $cavity <= 0) continue;

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
                if ($totalSecond <= 0) continue;

                $targetPerHour  = (int) floor((3600 / $cycle) * $cavity * $eff);
                $targetPerShift = (int) min((int) floor(($totalSecond / $cycle) * $cavity * $eff), 1200);
                if ($targetPerShift <= 0) continue;

                $schedule = $db->table('daily_schedules')
                    ->where(['schedule_date' => $date, 'shift_id' => $shiftId, 'section' => 'Leak Test'])
                    ->get()->getRowArray();

                if (!$schedule) {
                    $db->table('daily_schedules')->insert([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'process_id'    => $leakTestProcessId,
                        'section'       => 'Leak Test',
                        'is_completed'  => 0,
                        'created_at'    => date('Y-m-d H:i:s')
                    ]);
                    $scheduleId = (int) $db->insertID();
                } else {
                    $scheduleId = (int) $schedule['id'];
                    if (empty($schedule['process_id'])) {
                        $db->table('daily_schedules')->where('id', $scheduleId)->update([
                            'process_id' => $leakTestProcessId,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                $existItem = $db->table('daily_schedule_items')
                    ->where(['daily_schedule_id' => $scheduleId, 'machine_id' => $machineId, 'product_id' => $productId])
                    ->get()->getRowArray();

                $dataItem = [
                    'cycle_time'       => $cycle,
                    'cavity'           => $cavity,
                    'target_per_hour'  => $targetPerHour,
                    'target_per_shift' => $targetPerShift,
                    'is_selected'      => 1
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')->where('id', $existItem['id'])->update($dataItem);
                    $itemId = (int) $existItem['id'];
                } else {
                    $db->table('daily_schedule_items')->insert($dataItem + [
                        'daily_schedule_id' => $scheduleId,
                        'shift_id'          => $shiftId,
                        'machine_id'        => $machineId,
                        'product_id'        => $productId
                    ]);
                    $itemId = (int) $db->insertID();
                }

                // WIP logic (tetap)
                $flow = $this->getFlowPrevNext($db, $productId, $leakTestProcessId);
                if (empty($flow['sequence'])) continue;

                $prevProcessId = $flow['prev'];
                $nextProcessId = $flow['next'];

                $this->upsertWip($db,
                    [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $prevProcessId,
                        'to_process_id'   => $leakTestProcessId,
                    ],
                    [
                        'status'       => 'SCHEDULED',
                        'qty_in'       => $targetPerShift,
                        'qty'          => $targetPerShift,
                        'source_table' => 'daily_schedule_items',
                        'source_id'    => $itemId,
                        'updated_at'   => date('Y-m-d H:i:s'),
                    ]
                );

                if (!empty($nextProcessId)) {
                    $existOut = $db->table('production_wip')->where([
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $leakTestProcessId,
                        'to_process_id'   => $nextProcessId,
                    ])->get()->getRowArray();

                    if (!$existOut) {
                        $db->table('production_wip')->insert([
                            'production_date' => $date,
                            'product_id'      => $productId,
                            'from_process_id' => $leakTestProcessId,
                            'to_process_id'   => $nextProcessId,
                            'status'          => 'WAITING',
                            'qty'             => 0,
                            'qty_in'          => 0,
                            'qty_out'         => 0,
                            'stock'           => 0,
                            'source_table'    => 'daily_schedule_items',
                            'source_id'       => $itemId,
                            'created_at'      => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily schedule Leak Test tersimpan + WIP ter-update');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ======================= HELPERS =======================

    private function jsonFail(string $message, int $status = 400)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'      => false,
            'items'   => [],
            'message' => $message
        ]);
    }

    private function getProcessIdByCode($db, string $code): ?int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_code', $code)
            ->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    private function getProcessIdByNameLike($db, string $name): ?int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->like('process_name', $name)
            ->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    private function getFlowPrevNext($db, int $productId, int $currentProcessId): array
    {
        $cur = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'process_id' => $currentProcessId])
            ->get()->getRowArray();

        if (!$cur) return ['prev' => null, 'next' => null, 'sequence' => null];

        $seq = (int) $cur['sequence'];

        $prev = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'sequence' => $seq - 1])
            ->get()->getRowArray();

        $next = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'sequence' => $seq + 1])
            ->get()->getRowArray();

        return [
            'prev'     => $prev ? (int) $prev['process_id'] : null,
            'next'     => $next ? (int) $next['process_id'] : null,
            'sequence' => $seq
        ];
    }

    private function upsertWip($db, array $key, array $data): void
    {
        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

        if ($exist) {
            $db->table('production_wip')->where('id', $exist['id'])->update($data);
        } else {
            $db->table('production_wip')->insert($key + $data + [
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
