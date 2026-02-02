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

        // Mesin Machining
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id', 'left')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        // Plan existing
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
            ->like('ds.section', 'Assy Shaft')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'].'_'.$e['machine_id']] = $e;
        }

        // Actual
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
     * AJAX: PRODUCT + TARGET (produk yg ada flow Assy Shaft)
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = (int)($this->request->getGet('machine_id') ?? 0);
        $shiftId   = (int)($this->request->getGet('shift_id') ?? 0);
        if ($shiftId <= 0) return $this->response->setJSON([]);

        $assyProcessId = $this->getProcessIdByCodeOrName($db, 'AS', 'Assy Shaft');
        if ($assyProcessId <= 0) return $this->response->setJSON([]);

        $totalSecond = $this->getTotalShiftSeconds($db, $shiftId);
        if ($totalSecond <= 0) return $this->response->setJSON([]);

        $q = $db->table('product_process_flows ppf')
            ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $assyProcessId);

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
     * STORE (Schedule + WIP)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $date  = (string)($this->request->getPost('date') ?? '');
        $items = $this->request->getPost('items');

        if (!$date || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $assyProcessId = $this->getProcessIdByCodeOrName($db, 'AS', 'Assy Shaft');
        if ($assyProcessId <= 0) {
            return redirect()->back()->with('error', 'Process "Assy Shaft" belum ada di production_processes');
        }

        $hasWip     = $db->tableExists('production_wip');
        $wipDateCol = $hasWip ? $this->detectWipDateColumn($db) : null;

        $db->transBegin();
        try {
            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);
                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()->getRowArray();
                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                $eff    = ((float)($product['efficiency_rate'] ?? 100)) / 100;

                if ($cycle <= 0 || $cavity <= 0) continue;

                $totalSecond = $this->getTotalShiftSeconds($db, $shiftId);
                if ($totalSecond <= 0) continue;

                $targetPerHour  = (int)floor((3600 / $cycle) * $cavity * $eff);
                $targetPerShift = (int)min(floor(($totalSecond / $cycle) * $cavity * $eff), 1200);

                $manualPlan = (int)($row['target_per_shift'] ?? 0);
                if ($manualPlan > 0) $targetPerShift = min($manualPlan, 1200);
                if ($targetPerShift <= 0) continue;

                $scheduleId = $this->getOrCreateDailySchedule($db, $date, $shiftId, 'Assy Shaft', $assyProcessId);

                $itemId = $this->upsertScheduleItemPerMachine(
                    $db,
                    $scheduleId,
                    $shiftId,
                    $machineId,
                    $productId,
                    [
                        'cycle_time'       => $cycle,
                        'cavity'           => $cavity,
                        'target_per_hour'  => $targetPerHour,
                        'target_per_shift' => $targetPerShift,
                        'is_selected'      => 1,
                    ]
                );

                if ($hasWip && $wipDateCol) {
                    $pn = $this->resolvePrevNextBySequence($db, $productId, $assyProcessId);
                    $prevProcessId = $pn['prev'];
                    $nextProcessId = $pn['next'];

                    if ($prevProcessId) {
                        $baseInbound = [
                            $wipDateCol       => $date,
                            'product_id'      => $productId,
                            'from_process_id' => (int)$prevProcessId,
                            'to_process_id'   => (int)$assyProcessId,
                        ];

                        $dataInbound = $this->buildWipData($db, [
                            'status'   => 'SCHEDULED',
                            'qty_plan' => $targetPerShift,
                        ]);

                        $this->upsertWipSafeGeneric($db, $baseInbound, $dataInbound, 'daily_schedule_items', $itemId);
                        $this->upsertWipSafeGeneric($db, $baseInbound, $dataInbound, 'daily_schedules', $scheduleId);
                    }

                    if ($nextProcessId) {
                        $baseOut = [
                            $wipDateCol       => $date,
                            'product_id'      => $productId,
                            'from_process_id' => (int)$assyProcessId,
                            'to_process_id'   => (int)$nextProcessId,
                        ];

                        $dataOut = $this->buildWipData($db, [
                            'status'   => 'WAITING',
                            'qty_plan' => 0,
                        ]);

                        $this->upsertWipSafeGeneric($db, $baseOut, $dataOut, 'daily_schedule_items', $itemId);
                        $this->upsertWipSafeGeneric($db, $baseOut, $dataOut, 'daily_schedules', $scheduleId);
                    }
                }
            }

            if ($db->transStatus() === false) {
                $err = $db->error();
                throw new \Exception($err['message'] ?? 'DB error');
            }
            $db->transCommit();

            return redirect()->back()->with('success', 'Daily schedule Assy Shaft tersimpan + WIP ter-update');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * INCOMING WIP (FOR MODAL)
     * ===================================================== */
    public function incomingWip()
    {
        $db   = db_connect();
        $date = (string)($this->request->getGet('date') ?? date('Y-m-d'));

        try {
            if (!$db->tableExists('production_wip')) {
                return $this->response->setJSON(['status' => false, 'message' => 'Tabel production_wip tidak ditemukan']);
            }

            $wipDateCol    = $this->detectWipDateColumn($db);
            $assyProcessId = $this->getProcessIdByCodeOrName($db, 'AS', 'Assy Shaft');
            if ($assyProcessId <= 0) {
                return $this->response->setJSON(['status' => false, 'message' => 'Process "Assy Shaft" belum ada']);
            }

            $hasStock = $db->fieldExists('stock', 'production_wip');
            $hasQtyIn = $db->fieldExists('qty_in', 'production_wip');

            $availExpr = $hasStock ? 'pw.stock' : ($hasQtyIn ? 'pw.qty_in' : 'pw.qty');

            $rows = $db->table('production_wip pw')
                ->select("
                    pw.id AS wip_id,
                    pw.product_id,
                    pw.from_process_id,
                    p.part_no,
                    p.part_name,
                    {$availExpr} AS avail
                ")
                ->join('products p', 'p.id = pw.product_id', 'left')
                ->where("pw.{$wipDateCol}", $date)
                ->where('pw.to_process_id', (int)$assyProcessId)
                ->where('pw.status', 'WAITING')
                ->where("{$availExpr} >", 0)
                ->orderBy('p.part_no', 'ASC')
                ->get()->getResultArray();

            return $this->response->setJSON([
                'status' => true,
                'data'   => array_map(function ($r) {
                    return [
                        'wip_id'          => (int)$r['wip_id'],
                        'product_id'      => (int)$r['product_id'],
                        'from_process_id' => (int)($r['from_process_id'] ?? 0),
                        'part_no'         => (string)($r['part_no'] ?? ''),
                        'part_name'       => (string)($r['part_name'] ?? ''),
                        'avail'           => (int)($r['avail'] ?? 0),
                    ];
                }, $rows)
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /* =====================================================
     * ASSIGN INCOMING WIP (FOR MODAL)  ✅ FIXED
     * ===================================================== */
    public function assignIncomingWip()
    {
        $db = db_connect();

        $date      = (string)($this->request->getPost('date') ?? '');
        $shiftId   = (int)($this->request->getPost('shift_id') ?? 0);
        $machineId = (int)($this->request->getPost('machine_id') ?? 0);
        $productId = (int)($this->request->getPost('product_id') ?? 0);
        $qtyAssign = (int)($this->request->getPost('qty') ?? 0);
        $wipId     = (int)($this->request->getPost('wip_id') ?? 0);

        if (!$date || $shiftId <= 0 || $machineId <= 0 || $productId <= 0 || $qtyAssign <= 0 || $wipId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Payload tidak lengkap']);
        }

        if (!$db->tableExists('production_wip')) {
            return $this->response->setJSON(['status' => false, 'message' => 'Tabel production_wip tidak ditemukan']);
        }

        $wipDateCol    = $this->detectWipDateColumn($db);
        $assyProcessId = $this->getProcessIdByCodeOrName($db, 'AS', 'Assy Shaft');
        if ($assyProcessId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Process "Assy Shaft" belum ada']);
        }

        $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');

        // ambil WIP sumber
        $wip = $db->table('production_wip')->where('id', $wipId)->get()->getRowArray();
        if (!$wip) return $this->response->setJSON(['status' => false, 'message' => 'WIP sumber tidak ditemukan']);

        // validasi
        if ((int)($wip['to_process_id'] ?? 0) !== (int)$assyProcessId) {
            return $this->response->setJSON(['status' => false, 'message' => 'WIP ini bukan incoming Assy Shaft']);
        }
        if ((string)($wip[$wipDateCol] ?? '') !== $date) {
            return $this->response->setJSON(['status' => false, 'message' => 'Tanggal WIP tidak sama']);
        }
        if (strtoupper((string)($wip['status'] ?? '')) !== 'WAITING') {
            return $this->response->setJSON(['status' => false, 'message' => 'WIP bukan status WAITING']);
        }

        $fromProcessId = (int)($wip['from_process_id'] ?? 0);
        if ($fromProcessId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'from_process_id kosong']);
        }

        // hitung available
        $avail = 0;
        if ($hasStock) $avail = (int)($wip['stock'] ?? 0);
        else if ($hasQtyIn) $avail = (int)($wip['qty_in'] ?? 0);
        else $avail = (int)($wip['qty'] ?? 0);

        if ($qtyAssign > $avail) {
            return $this->response->setJSON(['status' => false, 'message' => "Qty melebihi available ({$avail})"]);
        }

        // product master
        $product = $db->table('products')
            ->select('cycle_time, cavity, efficiency_rate')
            ->where('id', $productId)
            ->get()->getRowArray();
        if (!$product) return $this->response->setJSON(['status' => false, 'message' => 'Product tidak ditemukan']);

        $cycle  = (int)($product['cycle_time'] ?? 0);
        $cavity = (int)($product['cavity'] ?? 0);
        $eff    = ((float)($product['efficiency_rate'] ?? 100)) / 100;

        $totalSecond = $this->getTotalShiftSeconds($db, $shiftId);
        if ($totalSecond <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Shift time slot belum diset']);
        }

        $targetPerHour = ($cycle > 0 && $cavity > 0) ? (int)floor((3600 / $cycle) * $cavity * $eff) : 0;
        $qtyAssign     = min($qtyAssign, 1200);

        $db->transBegin();
        try {
            // 1) daily_schedules
            $scheduleId = $this->getOrCreateDailySchedule($db, $date, $shiftId, 'Assy Shaft', $assyProcessId);

            // 2) daily_schedule_items per machine (anti duplicate unique)
            $itemId = $this->upsertScheduleItemPerMachine(
                $db,
                $scheduleId,
                $shiftId,
                $machineId,
                $productId,
                [
                    'cycle_time'       => $cycle,
                    'cavity'           => $cavity,
                    'target_per_hour'  => $targetPerHour,
                    'target_per_shift' => $qtyAssign,
                    'is_selected'      => 1,
                ]
            );

            // 3) kurangi WIP sumber (pool)
            $remaining = $avail - $qtyAssign;

            $wipUpd = [
                'status' => ($remaining <= 0) ? 'DONE' : 'WAITING',
            ];
            if ($this->tableHas($db, 'production_wip', 'updated_at')) {
                $wipUpd['updated_at'] = date('Y-m-d H:i:s');
            }

            if ($hasStock) $wipUpd['stock'] = $remaining;
            else if ($hasQtyIn) $wipUpd['qty_in'] = $remaining;
            else $wipUpd['qty'] = $remaining;

            if ($hasQtyOut) $wipUpd['qty_out'] = (int)($wip['qty_out'] ?? 0) + $qtyAssign;

            if ($db->table('production_wip')->where('id', $wipId)->update($wipUpd) === false) {
                $err = $db->error();
                throw new \Exception($err['message'] ?? 'Gagal update WIP sumber');
            }

            // 4) buat WIP assigned inbound (row SCHEDULED untuk schedule item)
            $assignedBase = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $fromProcessId,
                'to_process_id'   => (int)$assyProcessId,
            ];

            $assignedData = $this->buildWipData($db, [
                'status'   => 'SCHEDULED',
                'qty_plan' => $qtyAssign,
            ]);

            $this->upsertWipSafeGeneric($db, $assignedBase, $assignedData, 'daily_schedule_items', $itemId);
            $this->upsertWipSafeGeneric($db, $assignedBase, $assignedData, 'daily_schedules', $scheduleId);

            // 5) pastikan outbound AS->NEXT minimal ada
            $pn = $this->resolvePrevNextBySequence($db, $productId, $assyProcessId);
            if (!empty($pn['next'])) {
                $baseOut = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => (int)$assyProcessId,
                    'to_process_id'   => (int)$pn['next'],
                ];

                $dataOut = $this->buildWipData($db, [
                    'status'   => 'WAITING',
                    'qty_plan' => 0,
                ]);

                $this->upsertWipSafeGeneric($db, $baseOut, $dataOut, 'daily_schedule_items', $itemId);
                $this->upsertWipSafeGeneric($db, $baseOut, $dataOut, 'daily_schedules', $scheduleId);
            }

            if ($db->transStatus() === false) {
                $err = $db->error();
                throw new \Exception($err['message'] ?? 'DB error');
            }
            $db->transCommit();

            return $this->response->setJSON([
                'status'  => true,
                'message' => 'Incoming WIP berhasil di-assign',
                'data'    => [
                    'schedule_id' => $scheduleId,
                    'item_id'     => $itemId,
                    'wip_id'      => $wipId,
                    'assigned'    => $qtyAssign,
                    'remaining'   => $remaining,
                ]
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */

    private function tableHas($db, string $table, string $field): bool
    {
        return $db->fieldExists($field, $table);
    }

    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    private function getProcessIdByCodeOrName($db, ?string $code, string $nameExactOrLike): int
    {
        if ($code && $db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_code', $code)
                ->get()->getRowArray();
            if (!empty($row['id'])) return (int)$row['id'];
        }

        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', $nameExactOrLike)
            ->get()->getRowArray();
        if (!empty($row['id'])) return (int)$row['id'];

        $row = $db->table('production_processes')
            ->select('id')
            ->like('process_name', $nameExactOrLike)
            ->get()->getRowArray();
        if (!empty($row['id'])) return (int)$row['id'];

        return 0;
    }

    private function resolvePrevNextBySequence($db, int $productId, int $currentProcessId): array
    {
        if (!$db->tableExists('product_process_flows')) return ['prev' => null, 'next' => null];

        $cur = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1,
            ])->get()->getRowArray();

        if (!$cur) return ['prev' => null, 'next' => null];

        $seq = (int)$cur['sequence'];

        $prev = $db->table('product_process_flows')
            ->select('process_id')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->where('sequence <', $seq)
            ->orderBy('sequence', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        $next = $db->table('product_process_flows')
            ->select('process_id')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->where('sequence >', $seq)
            ->orderBy('sequence', 'ASC')
            ->limit(1)
            ->get()->getRowArray();

        return [
            'prev' => $prev ? (int)$prev['process_id'] : null,
            'next' => $next ? (int)$next['process_id'] : null,
        ];
    }

    private function getTotalShiftSeconds($db, int $shiftId): int
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

    private function getOrCreateDailySchedule($db, string $date, int $shiftId, string $sectionName, int $processId): int
    {
        $schedule = $db->table('daily_schedules')
            ->where('schedule_date', $date)
            ->where('shift_id', $shiftId)
            ->like('section', $sectionName)
            ->get()->getRowArray();

        if (!$schedule) {
            $insert = [
                'schedule_date' => $date,
                'shift_id'      => $shiftId,
                'section'       => $sectionName,
                'is_completed'  => 0,
            ];

            if ($db->fieldExists('process_id', 'daily_schedules')) $insert['process_id'] = $processId;
            if ($db->fieldExists('created_at', 'daily_schedules')) $insert['created_at'] = date('Y-m-d H:i:s');

            if ($db->table('daily_schedules')->insert($insert) === false) {
                $err = $db->error();
                throw new \Exception($err['message'] ?? 'Gagal insert daily_schedules');
            }

            return (int)$db->insertID();
        }

        $scheduleId = (int)$schedule['id'];

        // canonicalize + backfill process_id
        $upd = [];
        if ((string)($schedule['section'] ?? '') !== $sectionName) $upd['section'] = $sectionName;
        if ($db->fieldExists('process_id', 'daily_schedules') && empty($schedule['process_id'])) $upd['process_id'] = $processId;

        if (!empty($upd)) {
            if ($db->fieldExists('updated_at', 'daily_schedules')) $upd['updated_at'] = date('Y-m-d H:i:s');
            $db->table('daily_schedules')->where('id', $scheduleId)->update($upd);
        }

        return $scheduleId;
    }

    /**
     * 1 mesin 1 baris, anti duplicate unique (merge/delete jika ada dup)
     */
    private function upsertScheduleItemPerMachine(
        $db,
        int $scheduleId,
        int $shiftId,
        int $machineId,
        int $productId,
        array $dataItem
    ): int {
        $exist = $db->table('daily_schedule_items')
            ->where([
                'daily_schedule_id' => $scheduleId,
                'machine_id'        => $machineId,
            ])->get()->getRowArray();

        // kalau exist tapi product beda, cek apakah sudah ada row dup untuk productId tsb
        if ($exist && (int)($exist['product_id'] ?? 0) !== $productId) {
            $dup = $db->table('daily_schedule_items')
                ->where([
                    'daily_schedule_id' => $scheduleId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                ])->get()->getRowArray();

            if ($dup) {
                // hapus row lama, pakai dup (hindari unique constraint)
                $db->table('daily_schedule_items')->where('id', (int)$exist['id'])->delete();
                $exist = $dup;
            }
        }

        if ($exist) {
            $upd = $dataItem;

            if ((int)($exist['product_id'] ?? 0) !== $productId) {
                $upd['product_id'] = $productId;
            }

            if ($db->fieldExists('updated_at', 'daily_schedule_items')) {
                $upd['updated_at'] = date('Y-m-d H:i:s');
            }

            if ($db->table('daily_schedule_items')->where('id', (int)$exist['id'])->update($upd) === false) {
                $err = $db->error();
                throw new \Exception($err['message'] ?? 'Gagal update daily_schedule_items');
            }

            return (int)$exist['id'];
        }

        $ins = $dataItem + [
            'daily_schedule_id' => $scheduleId,
            'shift_id'          => $shiftId,
            'machine_id'        => $machineId,
            'product_id'        => $productId,
        ];

        if ($db->fieldExists('created_at', 'daily_schedule_items')) {
            $ins['created_at'] = date('Y-m-d H:i:s');
        }

        if ($db->table('daily_schedule_items')->insert($ins) === false) {
            $err = $db->error();
            throw new \Exception($err['message'] ?? 'Gagal insert daily_schedule_items');
        }

        return (int)$db->insertID();
    }

    private function buildWipData($db, array $opt): array
    {
        $qtyPlan = (int)($opt['qty_plan'] ?? 0);

        $data = [
            'status' => $opt['status'] ?? 'WAITING',
            'qty'    => $qtyPlan,
        ];

        // updated_at hanya jika kolom ada
        if ($db->fieldExists('updated_at', 'production_wip')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if ($db->fieldExists('qty_in', 'production_wip'))  $data['qty_in']  = $qtyPlan;
        if ($db->fieldExists('qty_out', 'production_wip')) $data['qty_out'] = 0;
        if ($db->fieldExists('stock', 'production_wip'))   $data['stock']   = 0;

        return $data;
    }

    private function normalizeSourceTableValue($db, string $value): ?string
    {
        if (!$db->fieldExists('source_table', 'production_wip')) return null;

        try {
            $col = $db->query("SHOW COLUMNS FROM production_wip LIKE 'source_table'")->getRowArray();
            $type = strtolower((string)($col['Type'] ?? ''));

            $isEnum = (strpos($type, 'enum(') === 0);
            $isSet  = (strpos($type, 'set(') === 0);

            if (!$isEnum && !$isSet) return $value;

            $raw = (string)($col['Type'] ?? '');
            $inside = substr($raw, strpos($raw, '(') + 1);
            $inside = rtrim($inside, ')');

            preg_match_all("/'((?:\\\\'|[^'])*)'/", $inside, $m);
            $opts = $m[1] ?? [];

            $want = strtolower($value);
            $want2 = preg_replace('/[^a-z0-9]/', '', $want);

            foreach ($opts as $opt) {
                if (strtolower($opt) === $want) return $opt;
            }
            foreach ($opts as $opt) {
                $opt2 = preg_replace('/[^a-z0-9]/', '', strtolower($opt));
                if ($opt2 === $want2) return $opt;
            }

            // tidak ada dalam enum -> jangan set, biar tidak error
            return null;
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Upsert WIP safe:
     * - jika source_table/source_id ada, kita WAJIB pakai fullKey (tidak fallback overwrite baseKey)
     * - fallback baseKey hanya jika schema memang tidak punya source_table/source_id
     */
    private function upsertWipSafeGeneric($db, array $baseKey, array $data, ?string $sourceTable, ?int $sourceId): void
    {
        if (!$db->tableExists('production_wip')) return;

        $hasSourceTable = $db->fieldExists('source_table', 'production_wip');
        $hasSourceId    = $db->fieldExists('source_id', 'production_wip');
        $hasCreatedAt   = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt   = $db->fieldExists('updated_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        $fullKey = $baseKey;
        $payload = $data;

        // pastikan tidak memasukkan updated_at jika kolom tidak ada
        if (!$hasUpdatedAt && isset($payload['updated_at'])) unset($payload['updated_at']);
        if ($hasUpdatedAt) $payload['updated_at'] = $now;

        if ($hasSourceTable && $sourceTable !== null) {
            $normalized = $this->normalizeSourceTableValue($db, $sourceTable);
            if ($normalized !== null) {
                $fullKey['source_table'] = $normalized;
                $payload['source_table'] = $normalized;
            }
        }

        if ($hasSourceId && $sourceId !== null) {
            $fullKey['source_id'] = $sourceId;
            $payload['source_id'] = $sourceId;
        }

        // 1) update by fullKey
        $exist = $db->table('production_wip')->where($fullKey)->get()->getRowArray();
        if ($exist) {
            if ($db->table('production_wip')->where('id', (int)$exist['id'])->update($payload) === false) {
                $err = $db->error();
                throw new \Exception($err['message'] ?? 'Gagal update production_wip');
            }
            return;
        }

        // 2) insert fullKey
        $insert = $fullKey + $payload;
        if ($hasCreatedAt) $insert['created_at'] = $now;

        try {
            if ($db->table('production_wip')->insert($insert) === false) {
                $err = $db->error();
                throw new \Exception($err['message'] ?? 'Gagal insert production_wip');
            }
            return;
        } catch (\Throwable $e) {
            // fallback update baseKey HANYA jika schema memang tidak support source columns
            if (!($hasSourceTable && $hasSourceId)) {
                $exist2 = $db->table('production_wip')->where($baseKey)->get()->getRowArray();
                if ($exist2) {
                    if ($db->table('production_wip')->where('id', (int)$exist2['id'])->update($payload) === false) {
                        $err = $db->error();
                        throw new \Exception($err['message'] ?? $e->getMessage());
                    }
                    return;
                }
            }
            throw new \Exception($e->getMessage());
        }
    }
}
