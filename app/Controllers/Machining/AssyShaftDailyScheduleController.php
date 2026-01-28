<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftDailyScheduleController extends BaseController
{
    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // SHIFT MC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // Mesin (pakai mesin machining, sesuai pola kamu)
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id', 'left')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        // plan existing
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
            ->where('ds.section', 'Assy shaft')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'].'_'.$e['machine_id']] = $e;
        }

        // actual
        $actuals = $db->table('machining_assy_shaft_hourly')
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
            $actualMap[$a['shift_id'].'_'.$a['machine_id'].'_'.$a['product_id']] = $a;
        }

        return view('machining/assy_shaft_schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /* =====================================================
     * AJAX: PRODUCT + TARGET (hanya produk yang ada flow Assy shaft)
     * ===================================================== */
public function getProductAndTarget()
{
    $db        = db_connect();
    $machineId = (int)($this->request->getGet('machine_id') ?? 0);
    $shiftId   = (int)($this->request->getGet('shift_id') ?? 0);
    if ($shiftId <= 0) return $this->response->setJSON([]);

    $assyProcessId = $this->getProcessId($db, 'Assy shaft', 'AB');
    if (!$assyProcessId) return $this->response->setJSON([]);

    // total detik shift (boleh kamu refactor jadi helper seperti Machining)
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
    if ($totalSecond <= 0) return $this->response->setJSON([]);

    // ===== ambil produk BERDASARKAN FLOW Assy shaft =====
    $q = $db->table('product_process_flows ppf')
        ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
        ->join('products p', 'p.id = ppf.product_id')
        ->where('ppf.is_active', 1)
        ->where('p.is_active', 1)
        ->where('ppf.process_id', $assyProcessId);

    // OPTIONAL: kalau mau filter by machine, pakai EXISTS; tapi jangan bikin kosong total
    if ($machineId > 0 && $db->tableExists('machine_products')) {
        $q->join('machine_products mp', 'mp.product_id = p.id AND mp.machine_id = '.$machineId.' AND mp.is_active=1', 'left');
        // tetap groupBy p.id; kalau mapping tidak ada, hasil masih keluar (flow-based)
    }

    $products = $q->groupBy('p.id')
        ->orderBy('p.part_no', 'ASC')
        ->get()->getResultArray();

    foreach ($products as &$p) {
        $cycle  = (int)($p['cycle_time'] ?? 0);
        $cavity = (int)($p['cavity'] ?? 0);

        $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
        $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

        $p['target'] = ($cycle > 0 && $cavity > 0)
            ? (int)min(floor(($totalSecond / $cycle) * $cavity * $eff), 1200)
            : 0;
    }
    unset($p);

    return $this->response->setJSON($products);
}


    /* =====================================================
     * STORE (Schedule + WIP: sama pola Machining)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $date  = $this->request->getPost('date');
        $items = $this->request->getPost('items');

        if (!$date || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $assyProcessId = $this->getProcessId($db, 'Assy Shaft', 'AS');
        if (!$assyProcessId) {
            return redirect()->back()->with('error', 'Process "Assy shaft" belum ada di production_processes');
        }

        $db->transBegin();

        try {
            foreach ($items as $row) {

                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if (!$shiftId || !$machineId || !$productId) continue;

                // master product
                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()->getRowArray();

                if (!$product) continue;

                $cycle  = (int)$product['cycle_time'];
                $cavity = (int)$product['cavity'];
                $eff    = ((float)$product['efficiency_rate']) / 100;

                if ($cycle <= 0 || $cavity <= 0) continue;

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
                if ($totalSecond <= 0) continue;

                // target default
                $targetPerHour  = (int)floor((3600 / $cycle) * $cavity * $eff);
                $targetPerShift = (int)min(floor(($totalSecond / $cycle) * $cavity * $eff), 1200);

                // override manual kalau user isi planning
                $manualPlan = (int)($row['target_per_shift'] ?? 0);
                if ($manualPlan > 0) {
                    $targetPerShift = min($manualPlan, 1200);
                }

                if ($targetPerShift <= 0) continue;

                // daily_schedules header
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy shaft'
                    ])
                    ->get()->getRowArray();

                if (!$schedule) {
                    $insert = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy shaft',
                        'is_completed'  => 0,
                        'created_at'    => date('Y-m-d H:i:s')
                    ];

                    // isi process_id jika kolom ada
                    if ($db->fieldExists('process_id', 'daily_schedules')) {
                        $insert['process_id'] = $assyProcessId;
                    }

                    $db->table('daily_schedules')->insert($insert);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];

                    // backfill process_id kalau perlu
                    if ($db->fieldExists('process_id', 'daily_schedules') && empty($schedule['process_id'])) {
                        $db->table('daily_schedules')->where('id', $scheduleId)->update([
                            'process_id' => $assyProcessId,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                // upsert daily_schedule_items
                $existItem = $db->table('daily_schedule_items')->where([
                    'daily_schedule_id' => $scheduleId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId
                ])->get()->getRowArray();

                $dataItem = [
                    'cycle_time'       => $cycle,
                    'cavity'           => $cavity,
                    'target_per_hour'  => $targetPerHour,
                    'target_per_shift' => $targetPerShift,
                    'is_selected'      => 1
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')->where('id', $existItem['id'])->update($dataItem);
                    $itemId = (int)$existItem['id'];
                } else {
                    $db->table('daily_schedule_items')->insert($dataItem + [
                        'daily_schedule_id' => $scheduleId,
                        'shift_id'          => $shiftId,
                        'machine_id'        => $machineId,
                        'product_id'        => $productId
                    ]);
                    $itemId = (int)$db->insertID();
                }

                // =========================
                // WIP LOGIC (Machining style)
                // =========================
                $flow = $this->getFlowPrevNext($db, $productId, $assyProcessId);
                $prevProcessId = $flow['prev']; // boleh null
                $nextProcessId = $flow['next']; // boleh null

                // inbound: prev -> assy  (SCHEDULED)
                $this->upsertWip($db,
                    [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $prevProcessId,
                        'to_process_id'   => $assyProcessId
                    ],
                    $this->buildWipData($db, [
                        'status'       => 'SCHEDULED',
                        'qty_plan'     => $targetPerShift,
                        'source_table' => 'daily_schedule_items',
                        'source_id'    => $itemId
                    ])
                );

                // outbound: assy -> next (WAITING) (buat kalau belum ada)
                if ($nextProcessId) {
                    $existOut = $db->table('production_wip')->where([
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $assyProcessId,
                        'to_process_id'   => $nextProcessId
                    ])->get()->getRowArray();

                    if (!$existOut) {
                        $db->table('production_wip')->insert(
                            [
                                'production_date' => $date,
                                'product_id'      => $productId,
                                'from_process_id' => $assyProcessId,
                                'to_process_id'   => $nextProcessId,
                            ] + $this->buildWipData($db, [
                                'status'       => 'WAITING',
                                'qty_plan'     => 0,
                                'source_table' => 'daily_schedule_items',
                                'source_id'    => $itemId
                            ]) + [
                                'created_at' => date('Y-m-d H:i:s')
                            ]
                        );
                    }
                }
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily schedule Assy shaft berhasil disimpan + WIP ter-update');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */
private function getProcessId($db, string $processName, ?string $processCode = null): ?int
{
    // 1) by code (paling stabil)
    if ($processCode && $db->fieldExists('process_code', 'production_processes')) {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_code', $processCode)
            ->get()
            ->getRowArray();
        if ($row) return (int)$row['id'];
    }

    // 2) exact name
    $row = $db->table('production_processes')
        ->select('id')
        ->where('process_name', $processName)
        ->get()
        ->getRowArray();
    if ($row) return (int)$row['id'];

    // 3) LIKE fallback (biar tahan variasi nama)
    $row = $db->table('production_processes')
        ->select('id')
        ->like('process_name', $processName)
        ->get()
        ->getRowArray();
    if ($row) return (int)$row['id'];

    return null;
}


    private function getFlowPrevNext($db, int $productId, int $currentProcessId): array
    {
        $cur = $db->table('product_process_flows')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1
            ])
            ->get()->getRowArray();

        if (!$cur) return ['prev' => null, 'next' => null];

        $seq = (int)$cur['sequence'];

        $prev = $db->table('product_process_flows')
            ->where([
                'product_id' => $productId,
                'sequence'   => $seq - 1,
                'is_active'  => 1
            ])->get()->getRowArray();

        $next = $db->table('product_process_flows')
            ->where([
                'product_id' => $productId,
                'sequence'   => $seq + 1,
                'is_active'  => 1
            ])->get()->getRowArray();

        return [
            'prev' => $prev ? (int)$prev['process_id'] : null,
            'next' => $next ? (int)$next['process_id'] : null,
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

    /**
     * build data WIP yg aman terhadap kolom yg belum ada
     */
    private function buildWipData($db, array $opt): array
    {
        $data = [
            'status'       => $opt['status'] ?? 'WAITING',
            'updated_at'   => date('Y-m-d H:i:s')
        ];

        // kolom qty legacy hampir pasti ada
        $data['qty'] = (int)($opt['qty_plan'] ?? 0);

        if ($db->fieldExists('source_table', 'production_wip')) $data['source_table'] = $opt['source_table'] ?? null;
        if ($db->fieldExists('source_id', 'production_wip'))    $data['source_id']    = $opt['source_id'] ?? null;

        // kolom baru opsional (jika ada)
        if ($db->fieldExists('qty_in', 'production_wip'))  $data['qty_in']  = (int)($opt['qty_plan'] ?? 0);
        if ($db->fieldExists('qty_out', 'production_wip')) $data['qty_out'] = 0;
        if ($db->fieldExists('stock', 'production_wip'))   $data['stock']   = 0;

        return $data;
    }
}
