<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyBushingDailyScheduleController extends BaseController
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
            ->where('ds.section', 'Assy Bushing')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'].'_'.$e['machine_id']] = $e;
        }

        // actual
        $actuals = $db->table('machining_assy_bushing_hourly')
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

        return view('machining/assy_bushing_schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /* =====================================================
     * AJAX: PRODUCT + TARGET (hanya produk yang ada flow Assy Bushing)
     * ===================================================== */
public function getProductAndTarget()
{
    $db        = db_connect();
    $machineId = (int)($this->request->getGet('machine_id') ?? 0);
    $shiftId   = (int)($this->request->getGet('shift_id') ?? 0);
    if ($shiftId <= 0) return $this->response->setJSON([]);

    $assyProcessId = $this->getProcessId($db, 'Assy Bushing', 'AB');
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

    // ===== ambil produk BERDASARKAN FLOW Assy Bushing =====
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

        $assyProcessId = $this->getProcessId($db, 'Assy Bushing', 'AB');
        if (!$assyProcessId) {
            return redirect()->back()->with('error', 'Process "Assy Bushing" belum ada di production_processes');
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
                        'section'       => 'Assy Bushing'
                    ])
                    ->get()->getRowArray();

                if (!$schedule) {
                    $insert = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Bushing',
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
            return redirect()->back()->with('success', 'Daily schedule Assy Bushing berhasil disimpan + WIP ter-update');

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

    /**
 * Detect kolom tanggal yang dipakai di production_wip
 */
private function detectWipDateColumn($db): string
{
    if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
    if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
    if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
    throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
}

/**
 * JSON helper
 */
private function jsonFail(string $message, int $status = 400)
{
    return $this->response->setStatusCode($status)->setJSON([
        'status'  => false,
        'message' => $message,
        'data'    => []
    ]);
}

/**
 * GET /machining/assy-bushing/schedule/incoming-wip?date=YYYY-MM-DD
 * Ambil WIP incoming (prev -> Assy Bushing) status WAITING, qty > 0
 */
public function incomingWip()
{
    $db   = db_connect();
    $date = (string)($this->request->getGet('date') ?? '');

    if ($date === '') {
        return $this->jsonFail('date kosong', 400);
    }

    try {
        // Process Assy Bushing: pakai code AB jika ada
        $assyProcessId = $this->getProcessId($db, 'Assy Bushing', 'AB');
        if (!$assyProcessId) {
            return $this->jsonFail('Process Assy Bushing tidak ditemukan', 404);
        }

        if (!$db->tableExists('production_wip')) {
            return $this->jsonFail('Tabel production_wip tidak ditemukan', 500);
        }

        $wipDateCol = $this->detectWipDateColumn($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');

        $rows = $db->table('production_wip pw')
            ->select('
                pw.id,
                pw.product_id,
                pw.from_process_id,
                pw.to_process_id,
                pw.qty,
                '.($hasQtyIn  ? 'pw.qty_in,'  : '0 AS qty_in,').'
                '.($hasQtyOut ? 'pw.qty_out,' : '0 AS qty_out,').'
                '.($hasStock  ? 'pw.stock,'   : '0 AS stock,').'
                pw.status,
                p.part_no,
                p.part_name
            ')
            ->join('products p', 'p.id = pw.product_id')
            ->where("pw.$wipDateCol", $date)
            ->where('pw.to_process_id', $assyProcessId)
            ->where('pw.status', 'WAITING')
            ->orderBy('p.part_no', 'ASC')
            ->get()->getResultArray();

        $data = [];
        foreach ($rows as $r) {
            $qty     = (int)($r['qty'] ?? 0);
            $qtyIn   = (int)($r['qty_in'] ?? 0);
            $stock   = (int)($r['stock'] ?? 0);

            // available: stock > 0 ? stock : qty_in > 0 ? qty_in : qty
            $avail = 0;
            if ($hasStock && $stock > 0) $avail = $stock;
            else if ($hasQtyIn && $qtyIn > 0) $avail = $qtyIn;
            else $avail = $qty;

            if ($avail <= 0) continue;

            $data[] = [
                'wip_id'          => (int)$r['id'],
                'product_id'      => (int)$r['product_id'],
                'part_no'         => (string)$r['part_no'],
                'part_name'       => (string)$r['part_name'],
                'avail'           => $avail,
                'from_process_id' => (int)($r['from_process_id'] ?? 0),
            ];
        }

        return $this->response->setJSON([
            'status' => true,
            'data'   => $data
        ]);

    } catch (\Throwable $e) {
        return $this->jsonFail('Server error: '.$e->getMessage(), 500);
    }
}

/**
 * POST /machining/assy-bushing/schedule/assign-incoming-wip
 * Payload: date, shift_id, machine_id, product_id, qty, wip_id
 *
 * - Upsert daily_schedules (section Assy Bushing)
 * - Upsert daily_schedule_items (target_per_shift=qty)
 * - Buat/Update WIP inbound untuk schedule (source_table daily_schedule_items)
 * - Kurangi available dari WIP incoming (wip_id)
 */
public function assignIncomingWip()
{
    $db = db_connect();

    $date      = (string)($this->request->getPost('date') ?? '');
    $shiftId   = (int)($this->request->getPost('shift_id') ?? 0);
    $machineId = (int)($this->request->getPost('machine_id') ?? 0);
    $productId = (int)($this->request->getPost('product_id') ?? 0);
    $qty       = (int)($this->request->getPost('qty') ?? 0);
    $wipId     = (int)($this->request->getPost('wip_id') ?? 0);

    if ($date === '' || $shiftId <= 0 || $machineId <= 0 || $productId <= 0 || $qty <= 0 || $wipId <= 0) {
        return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
    }

    if (!$db->tableExists('production_wip')) {
        return $this->response->setJSON(['status' => false, 'message' => 'Tabel production_wip tidak ditemukan']);
    }

    $db->transBegin();

    try {
        $assyProcessId = $this->getProcessId($db, 'Assy Bushing', 'AB');
        if (!$assyProcessId) throw new \Exception('Process Assy Bushing tidak ditemukan');

        $wipDateCol = $this->detectWipDateColumn($db);

        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        // 1) ambil incoming untuk validasi avail + prev process
        $incoming = $db->table('production_wip')->where('id', $wipId)->get()->getRowArray();
        if (!$incoming) throw new \Exception('Incoming WIP tidak ditemukan');

        $prevProcessId = (int)($incoming['from_process_id'] ?? 0);
        if ($prevProcessId <= 0) throw new \Exception('Prev process pada incoming WIP tidak valid');

        $curQty   = (int)($incoming['qty'] ?? 0);
        $curIn    = $hasQtyIn ? (int)($incoming['qty_in'] ?? 0) : 0;
        $curStock = $hasStock ? (int)($incoming['stock'] ?? 0) : 0;

        $avail = 0;
        if ($hasStock && $curStock > 0) $avail = $curStock;
        else if ($hasQtyIn && $curIn > 0) $avail = $curIn;
        else $avail = $curQty;

        if ($qty > $avail) throw new \Exception("Qty melebihi available ({$avail})");

        // 2) upsert header daily_schedules (Assy Bushing)
        $schedule = $db->table('daily_schedules')
            ->where([
                'schedule_date' => $date,
                'shift_id'      => $shiftId,
                'section'       => 'Assy Bushing',
            ])->get()->getRowArray();

        if (!$schedule) {
            $header = [
                'schedule_date' => $date,
                'shift_id'      => $shiftId,
                'section'       => 'Assy Bushing',
                'is_completed'  => 0,
                'created_at'    => $now,
            ];
            if ($db->fieldExists('process_id', 'daily_schedules')) $header['process_id'] = $assyProcessId;
            if ($db->fieldExists('updated_at', 'daily_schedules')) $header['updated_at'] = $now;

            $db->table('daily_schedules')->insert($header);
            $scheduleId = (int)$db->insertID();
        } else {
            $scheduleId = (int)$schedule['id'];
        }

        // 3) master product untuk CT/cavity/eff → target_per_hour
        $product = $db->table('products')
            ->select('cycle_time, cavity, efficiency_rate')
            ->where('id', $productId)
            ->get()->getRowArray();
        if (!$product) throw new \Exception('Product master tidak ditemukan');

        $cycle  = (int)($product['cycle_time'] ?? 0);
        $cavity = (int)($product['cavity'] ?? 0);
        $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
        $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

        $targetPerHour = ($cycle > 0 && $cavity > 0)
            ? (int)floor((3600 / $cycle) * $cavity * $eff)
            : 0;

        // 4) upsert daily_schedule_items (per mesin)
        $existItem = $db->table('daily_schedule_items')
            ->where([
                'daily_schedule_id' => $scheduleId,
                'machine_id'        => $machineId,
            ])->get()->getRowArray();

        $dataItem = [
            'daily_schedule_id' => $scheduleId,
            'shift_id'          => $shiftId,
            'machine_id'        => $machineId,
            'product_id'        => $productId,
            'cycle_time'        => $cycle,
            'cavity'            => $cavity,
            'target_per_hour'   => $targetPerHour,
            'target_per_shift'  => min($qty, 1200),
        ];
        if ($db->fieldExists('is_selected', 'daily_schedule_items')) $dataItem['is_selected'] = 1;

        if ($existItem) {
            $db->table('daily_schedule_items')->where('id', (int)$existItem['id'])->update($dataItem);
            $itemId = (int)$existItem['id'];
        } else {
            $db->table('daily_schedule_items')->insert($dataItem);
            $itemId = (int)$db->insertID();
        }

        // 5) buat/update WIP inbound untuk schedule (source daily_schedule_items)
        $keyScheduleWip = [
            $wipDateCol       => $date,
            'product_id'      => $productId,
            'from_process_id' => $prevProcessId,
            'to_process_id'   => $assyProcessId,
            'source_table'    => 'daily_schedule_items',
            'source_id'       => $itemId,
        ];

        $existScheduleWip = $db->table('production_wip')->where($keyScheduleWip)->get()->getRowArray();

        $payloadScheduleWip = [
            'status' => 'SCHEDULED',
            'qty'    => min($qty, 1200),
        ];
        if ($hasQtyIn)  $payloadScheduleWip['qty_in']  = min($qty, 1200);
        if ($hasQtyOut) $payloadScheduleWip['qty_out'] = 0;
        if ($hasStock)  $payloadScheduleWip['stock']   = 0;
        if ($hasUpdatedAt) $payloadScheduleWip['updated_at'] = $now;

        if ($existScheduleWip) {
            if (strtoupper((string)($existScheduleWip['status'] ?? '')) !== 'DONE') {
                $db->table('production_wip')->where('id', (int)$existScheduleWip['id'])->update($payloadScheduleWip);
            }
        } else {
            if ($hasCreatedAt) $payloadScheduleWip['created_at'] = $now;
            $db->table('production_wip')->insert($keyScheduleWip + $payloadScheduleWip);
        }

        // 6) kurangi available dari WIP incoming (wip_id)
        $updIncoming = [];
        if ($hasStock && $curStock > 0) {
            $newStock = max(0, $curStock - $qty);
            $updIncoming['stock'] = $newStock;
            if ($newStock === 0) $updIncoming['status'] = 'DONE';
        } elseif ($hasQtyIn && $curIn > 0) {
            $newIn = max(0, $curIn - $qty);
            $updIncoming['qty_in'] = $newIn;
            if ($newIn === 0) $updIncoming['status'] = 'DONE';
        } else {
            $newQty = max(0, $curQty - $qty);
            $updIncoming['qty'] = $newQty;
            if ($newQty === 0) $updIncoming['status'] = 'DONE';
        }

        if ($hasUpdatedAt) $updIncoming['updated_at'] = $now;
        $db->table('production_wip')->where('id', $wipId)->update($updIncoming);

        if ($db->transStatus() === false) throw new \Exception('DB error');

        $db->transCommit();
        return $this->response->setJSON(['status' => true, 'message' => 'Assign berhasil']);

    } catch (\Throwable $e) {
        $db->transRollback();
        return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
    }
}

}
