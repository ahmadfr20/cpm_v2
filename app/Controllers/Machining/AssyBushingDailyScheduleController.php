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

        // Mesin machining (pp.process_name = Machining)
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id', 'left')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        // plan existing (Assy Bushing)
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

        // actual (hourly)
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

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        if ($totalSecond <= 0) return $this->response->setJSON([]);

        $q = $db->table('product_process_flows ppf')
            ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $assyProcessId);

        // Optional filter by machine_products (kalau ada)
        if ($machineId > 0 && $db->tableExists('machine_products')) {
            $q->join(
                'machine_products mp',
                'mp.product_id = p.id AND mp.machine_id = '.$machineId.' AND mp.is_active=1',
                'left'
            );
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
     * STORE:
     * - UP SERT daily_schedules header per shift
     * - UP SERT daily_schedule_items per machine (ID stabil)
     * - HAPUS item yang tidak ada di submit + hapus WIP terkait item tsb
     * - BUAT WIP inbound (prev->Assy) 2 row (items + schedules)
     * - BUAT placeholder WIP outbound (Assy->next) 2 row (items + schedules)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $date  = (string)($this->request->getPost('date') ?? '');
        $items = $this->request->getPost('items');

        if ($date === '' || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $assyProcessId = $this->getProcessId($db, 'Assy Bushing', 'AB');
        if (!$assyProcessId) {
            return redirect()->back()->with('error', 'Process "Assy Bushing" belum ada di production_processes');
        }

        $wipDateCol = $this->detectWipDateColumn($db);
        $today      = date('Y-m-d');
        $now        = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            // grouping untuk sinkron per header shift
            // $group[shiftId] = ['schedule_id'=>int, 'machine_ids'=>[], 'item_ids'=>[]]
            $group = [];

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                // total shift detik (sekali per shift)
                if (!isset($group[$shiftId])) {
                    $group[$shiftId] = [
                        'schedule_id' => 0,
                        'machine_ids' => [],
                        'item_ids'    => [],
                        'total_second'=> $this->getTotalSecondShift($db, $shiftId),
                    ];
                }
                if (($group[$shiftId]['total_second'] ?? 0) <= 0) continue;

                // master product
                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()->getRowArray();
                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                if ($cycle <= 0 || $cavity <= 0) continue;

                $totalSecond = (int)$group[$shiftId]['total_second'];

                // default target
                $targetPerShiftDefault = (int)min(floor(($totalSecond / $cycle) * $cavity * $eff), 1200);
                $manualPlan = (int)($row['target_per_shift'] ?? 0);
                $targetPerShift = $manualPlan > 0 ? min($manualPlan, 1200) : $targetPerShiftDefault;

                if ($targetPerShift <= 0) continue;

                $hours = $totalSecond / 3600;
                $targetPerHour = $hours > 0 ? (int)ceil($targetPerShift / $hours) : 0;

                // ===== 1) UPSERT daily_schedules header per shift =====
                if ($group[$shiftId]['schedule_id'] <= 0) {
                    $scheduleId = $this->upsertDailyScheduleHeader(
                        $db,
                        $date,
                        $assyProcessId,
                        $shiftId,
                        'Assy Bushing'
                    );
                    if ($scheduleId <= 0) continue;
                    $group[$shiftId]['schedule_id'] = $scheduleId;
                } else {
                    $scheduleId = (int)$group[$shiftId]['schedule_id'];
                }

                // ===== 2) UPSERT daily_schedule_items per machine (ID stabil) =====
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
                    'target_per_shift'  => $targetPerShift,
                    'is_selected'       => 1,
                ];

                if ($existItem) {
                    $itemId = (int)$existItem['id'];
                    $db->table('daily_schedule_items')->where('id', $itemId)->update($dataItem);
                } else {
                    $db->table('daily_schedule_items')->insert($dataItem);
                    $itemId = (int)$db->insertID();
                }

                $group[$shiftId]['machine_ids'][$machineId] = true;
                $group[$shiftId]['item_ids'][$itemId] = true;

                // ===== 3) WIP (Machining style, tapi dibuat 2 row: item + header) =====
                $flow = $this->getFlowPrevNext($db, $productId, $assyProcessId);
                $prevProcessId = $flow['prev']; // nullable
                $nextProcessId = $flow['next']; // nullable

                $wipStatus = ($date === $today) ? 'SCHEDULED' : 'WAITING';

                // inbound (prev -> assy) ITEM
                $this->upsertWipSmart(
                    $db,
                    [
                        $wipDateCol       => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $prevProcessId,
                        'to_process_id'   => $assyProcessId,
                        'source_table'    => 'daily_schedule_items',
                        'source_id'       => $itemId,
                    ],
                    $this->buildWipData($db, [
                        'status'   => $wipStatus,
                        'qty_plan' => $targetPerShift,
                        'qty_in'   => $targetPerShift,
                        'qty_out'  => 0,
                        'stock'    => 0,
                        'now'      => $now,
                    ])
                );

                // inbound (prev -> assy) HEADER
                $this->upsertWipSmart(
                    $db,
                    [
                        $wipDateCol       => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $prevProcessId,
                        'to_process_id'   => $assyProcessId,
                        'source_table'    => 'daily_schedules',
                        'source_id'       => $scheduleId,
                    ],
                    $this->buildWipData($db, [
                        'status'   => $wipStatus,
                        'qty_plan' => $targetPerShift,
                        'qty_in'   => $targetPerShift,
                        'qty_out'  => 0,
                        'stock'    => 0,
                        'now'      => $now,
                    ])
                );

                // outbound placeholder (assy -> next) dibuat agar flow kebaca downstream
                if ($nextProcessId) {
                    // ITEM placeholder
                    $this->upsertWipSmart(
                        $db,
                        [
                            $wipDateCol       => $date,
                            'product_id'      => $productId,
                            'from_process_id' => $assyProcessId,
                            'to_process_id'   => (int)$nextProcessId,
                            'source_table'    => 'daily_schedule_items',
                            'source_id'       => $itemId,
                        ],
                        $this->buildWipData($db, [
                            'status'   => 'WAITING',
                            'qty_plan' => 0,
                            'qty_in'   => 0,
                            'qty_out'  => 0,
                            'stock'    => 0,
                            'now'      => $now,
                        ])
                    );

                    // HEADER placeholder
                    $this->upsertWipSmart(
                        $db,
                        [
                            $wipDateCol       => $date,
                            'product_id'      => $productId,
                            'from_process_id' => $assyProcessId,
                            'to_process_id'   => (int)$nextProcessId,
                            'source_table'    => 'daily_schedules',
                            'source_id'       => $scheduleId,
                        ],
                        $this->buildWipData($db, [
                            'status'   => 'WAITING',
                            'qty_plan' => 0,
                            'qty_in'   => 0,
                            'qty_out'  => 0,
                            'stock'    => 0,
                            'now'      => $now,
                        ])
                    );
                }
            }

            // ===== 4) CLEANUP: item yang tidak ada di submit (per shift header) =====
            foreach ($group as $shiftId => $meta) {
                $scheduleId = (int)($meta['schedule_id'] ?? 0);
                if ($scheduleId <= 0) continue;

                $keepMachines = array_keys($meta['machine_ids'] ?? []);
                // ambil item existing
                $existingItems = $db->table('daily_schedule_items')
                    ->select('id, machine_id')
                    ->where('daily_schedule_id', $scheduleId)
                    ->get()->getResultArray();

                $deleteItemIds = [];
                foreach ($existingItems as $it) {
                    $mid = (int)$it['machine_id'];
                    if (!in_array($mid, $keepMachines, true)) {
                        $deleteItemIds[] = (int)$it['id'];
                    }
                }

                if ($deleteItemIds) {
                    // hapus WIP yang mengacu ke item yang dihapus
                    if ($db->tableExists('production_wip') && $db->fieldExists('source_table', 'production_wip') && $db->fieldExists('source_id', 'production_wip')) {
                        $db->table('production_wip')
                            ->where($wipDateCol, $date)
                            ->where('source_table', 'daily_schedule_items')
                            ->whereIn('source_id', $deleteItemIds)
                            ->delete();
                    }

                    // hapus item
                    $db->table('daily_schedule_items')->whereIn('id', $deleteItemIds)->delete();
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with('success', 'Daily schedule Assy Bushing tersimpan + daily_schedules/items + WIP ter-update');
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
        // by code
        if ($processCode && $db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_code', $processCode)
                ->get()->getRowArray();
            if ($row) return (int)$row['id'];
        }

        // exact
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', $processName)
            ->get()->getRowArray();
        if ($row) return (int)$row['id'];

        // like
        $row = $db->table('production_processes')
            ->select('id')
            ->like('process_name', $processName)
            ->get()->getRowArray();
        if ($row) return (int)$row['id'];

        return null;
    }

    private function getTotalSecondShift($db, int $shiftId): int
    {
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
        return (int)$totalSecond;
    }

    private function getFlowPrevNext($db, int $productId, int $currentProcessId): array
    {
        if (!$db->tableExists('product_process_flows')) return ['prev' => null, 'next' => null];

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

    /**
     * Upsert header daily_schedules
     * key: (schedule_date, process_id, shift_id, section)
     */
    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;

        $where = [
            'schedule_date' => $date,
            'process_id'    => $processId,
            'shift_id'      => $shiftId,
            'section'       => $section,
        ];

        $exist = $db->table('daily_schedules')->where($where)->get()->getRowArray();
        $now   = date('Y-m-d H:i:s');

        if ($exist) {
            $upd = ['is_completed' => 0];
            if ($db->fieldExists('updated_at', 'daily_schedules')) $upd['updated_at'] = $now;
            $db->table('daily_schedules')->where('id', (int)$exist['id'])->update($upd);
            return (int)$exist['id'];
        }

        $payload = $where + [
            'is_completed' => 0,
        ];

        if ($db->fieldExists('created_at', 'daily_schedules')) $payload['created_at'] = $now;
        if ($db->fieldExists('updated_at', 'daily_schedules')) $payload['updated_at'] = $now;

        $db->table('daily_schedules')->insert($payload);
        return (int)$db->insertID();
    }

    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    /**
     * build data WIP yang aman terhadap kolom yang mungkin tidak ada
     */
    private function buildWipData($db, array $opt): array
    {
        $now = (string)($opt['now'] ?? date('Y-m-d H:i:s'));

        $data = [
            'status' => (string)($opt['status'] ?? 'WAITING'),
            'qty'    => (int)($opt['qty_plan'] ?? 0),
        ];

        if ($db->fieldExists('qty_in', 'production_wip'))  $data['qty_in']  = (int)($opt['qty_in']  ?? ($opt['qty_plan'] ?? 0));
        if ($db->fieldExists('qty_out', 'production_wip')) $data['qty_out'] = (int)($opt['qty_out'] ?? 0);
        if ($db->fieldExists('stock', 'production_wip'))   $data['stock']   = (int)($opt['stock']   ?? 0);

        if ($db->fieldExists('updated_at', 'production_wip')) $data['updated_at'] = $now;

        return $data;
    }

    /**
     * Upsert WIP dengan key yang mencakup source_table+source_id (kalau kolom ada)
     */
    private function upsertWipSmart($db, array $key, array $data): void
    {
        if (!$db->tableExists('production_wip')) return;

        // jika source_table/source_id tidak ada, buang dari key agar query tidak error
        if (!$db->fieldExists('source_table', 'production_wip')) unset($key['source_table']);
        if (!$db->fieldExists('source_id', 'production_wip'))    unset($key['source_id']);

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();
        $now   = date('Y-m-d H:i:s');

        if ($exist) {
            // jangan timpa DONE jadi non-DONE
            if (strtoupper((string)($exist['status'] ?? '')) === 'DONE' && strtoupper((string)($data['status'] ?? '')) !== 'DONE') {
                return;
            }
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($data);
            return;
        }

        $payload = $key + $data;
        if ($db->fieldExists('created_at', 'production_wip') && !isset($payload['created_at'])) $payload['created_at'] = $now;
        $db->table('production_wip')->insert($payload);
    }

    /* =====================================================
     * (Opsional) API incoming/assign WIP — tetap kamu punya
     * ===================================================== */

    private function jsonFail(string $message, int $status = 400)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status'  => false,
            'message' => $message,
            'data'    => []
        ]);
    }

    public function incomingWip()
    {
        $db   = db_connect();
        $date = (string)($this->request->getGet('date') ?? '');

        if ($date === '') return $this->jsonFail('date kosong', 400);

        try {
            $assyProcessId = $this->getProcessId($db, 'Assy Bushing', 'AB');
            if (!$assyProcessId) return $this->jsonFail('Process Assy Bushing tidak ditemukan', 404);
            if (!$db->tableExists('production_wip')) return $this->jsonFail('Tabel production_wip tidak ditemukan', 500);

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

            // upsert header daily_schedules
            $scheduleId = $this->upsertDailyScheduleHeader($db, $date, $assyProcessId, $shiftId, 'Assy Bushing');

            // upsert daily_schedule_items by machine
            $existItem = $db->table('daily_schedule_items')
                ->where([
                    'daily_schedule_id' => $scheduleId,
                    'machine_id'        => $machineId,
                ])->get()->getRowArray();

            // product master -> target per hour
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

            $dataItem = [
                'daily_schedule_id' => $scheduleId,
                'shift_id'          => $shiftId,
                'machine_id'        => $machineId,
                'product_id'        => $productId,
                'cycle_time'        => $cycle,
                'cavity'            => $cavity,
                'target_per_hour'   => $targetPerHour,
                'target_per_shift'  => min($qty, 1200),
                'is_selected'       => 1,
            ];

            if ($existItem) {
                $itemId = (int)$existItem['id'];
                $db->table('daily_schedule_items')->where('id', $itemId)->update($dataItem);
            } else {
                $db->table('daily_schedule_items')->insert($dataItem);
                $itemId = (int)$db->insertID();
            }

            // WIP inbound schedule (ITEM + HEADER)
            $keyItem = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $assyProcessId,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $itemId,
            ];
            $payloadItem = [
                'status' => 'SCHEDULED',
                'qty'    => min($qty, 1200),
            ];
            if ($hasQtyIn)  $payloadItem['qty_in']  = min($qty, 1200);
            if ($hasQtyOut) $payloadItem['qty_out'] = 0;
            if ($hasStock)  $payloadItem['stock']   = 0;
            if ($hasUpdatedAt) $payloadItem['updated_at'] = $now;

            $existScheduleWip = $db->table('production_wip')->where($keyItem)->get()->getRowArray();
            if ($existScheduleWip) {
                if (strtoupper((string)($existScheduleWip['status'] ?? '')) !== 'DONE') {
                    $db->table('production_wip')->where('id', (int)$existScheduleWip['id'])->update($payloadItem);
                }
            } else {
                if ($hasCreatedAt) $payloadItem['created_at'] = $now;
                $db->table('production_wip')->insert($keyItem + $payloadItem);
            }

            $keyHdr = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $assyProcessId,
                'source_table'    => 'daily_schedules',
                'source_id'       => $scheduleId,
            ];
            $existHdr = $db->table('production_wip')->where($keyHdr)->get()->getRowArray();
            if ($existHdr) {
                if (strtoupper((string)($existHdr['status'] ?? '')) !== 'DONE') {
                    $db->table('production_wip')->where('id', (int)$existHdr['id'])->update($payloadItem);
                }
            } else {
                $hdrPayload = $payloadItem;
                if ($hasCreatedAt) $hdrPayload['created_at'] = $now;
                $db->table('production_wip')->insert($keyHdr + $hdrPayload);
            }

            // kurangi incoming
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
