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
     * Helper: hitung total detik shift
     * ============================================ */
    private function getTotalSecondShift($db, int $shiftId): int
    {
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
            if ($end <= $start) $end += 86400; // lewat tengah malam
            $totalSecond += ($end - $start);
        }

        return (int)$totalSecond;
    }

    /* ============================================
     * Helper: validasi product punya flow Machining aktif
     * ============================================ */
    private function validateProductHasFlow($db, int $productId, int $processId): bool
    {
        return $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processId)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /* =====================================================
     * Helper: resolve PREV process by flow (sequence < current)
     * ===================================================== */
    private function resolvePrevProcessByFlow($db, int $productId, int $currentProcessId): ?int
    {
        $currentFlow = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1,
            ])
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getRowArray();

        if (!$currentFlow) return null;

        $curSeq = (int)$currentFlow['sequence'];

        $prevFlow = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->where('sequence <', $curSeq)
            ->orderBy('sequence', 'DESC')
            ->get()
            ->getRowArray();

        if (!$prevFlow) return null;

        return (int)$prevFlow['process_id'];
    }

    /* =====================================================
     * Helper: resolve NEXT process by flow (sequence > current)
     * ===================================================== */
    private function resolveNextProcessByFlow($db, int $productId, int $currentProcessId): ?int
    {
        $currentFlow = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1,
            ])
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getRowArray();

        if (!$currentFlow) return null;

        $curSeq = (int)$currentFlow['sequence'];

        $nextFlow = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->where('sequence >', $curSeq)
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getRowArray();

        if (!$nextFlow) return null;

        return (int)$nextFlow['process_id'];
    }

    /* =====================================================
     * UPSERT WIP outgoing (Machining -> next process) status WAITING
     * key: production_date + product_id + from_process + to_process + source_table + source_id
     * ===================================================== */
    private function upsertOutgoingWipToNextProcess(
        $db,
        string $date,
        int $productId,
        int $qty,
        int $fromProcessId,
        int $toProcessId,
        string $sourceTable,
        int $sourceId
    ): void {
        if (!$db->tableExists('production_wip')) return;

        $key = [
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromProcessId,
            'to_process_id'   => $toProcessId,
            'source_table'    => $sourceTable,
            'source_id'       => $sourceId,
        ];

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

        $now = date('Y-m-d H:i:s');

        if ($exist) {
            // kalau sudah DONE jangan diubah
            if (($exist['status'] ?? '') === 'DONE') return;

            $payload = [
                'qty'    => $qty,
                'status' => 'WAITING',
            ];
            if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
            return;
        }

        $payload = $key + [
            'qty'    => $qty,
            'status' => 'WAITING',
        ];
        if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        $db->table('production_wip')->insert($payload);
    }

    /* =====================================================
     * ALOKASI incoming WIP dari prevProcess -> Machining:
     * - Mengubah sebagian qty WAITING menjadi SCHEDULED untuk jadwal ini
     * - Disimpan sebagai row "alokasi" dengan source_table = daily_schedule_items, source_id = itemId
     *
     * Mekanisme delta:
     * - Kalau alokasi naik: konsumsi dari row WAITING (reduce/delete)
     * - Kalau alokasi turun: kembalikan ke WAITING (insert row WAITING)
     * ===================================================== */
    private function allocateIncomingWipToMachiningSchedule(
        $db,
        string $date,
        int $productId,
        int $prevProcessId,
        int $machiningProcessId,
        int $scheduleItemId,
        int $planQty
    ): void {
        if (!$db->tableExists('production_wip')) return;

        if ($prevProcessId <= 0) {
            // Machining adalah proses pertama (tidak ada incoming)
            return;
        }

        // Row alokasi untuk schedule item ini (prev -> machining) status SCHEDULED
        $allocKey = [
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => $prevProcessId,
            'to_process_id'   => $machiningProcessId,
            'source_table'    => 'daily_schedule_items',
            'source_id'       => $scheduleItemId,
        ];

        $allocRow = $db->table('production_wip')->where($allocKey)->get()->getRowArray();
        $prevAllocQty = (int)($allocRow['qty'] ?? 0);

        // Total WAITING available (prev -> machining) selain row alokasi ini
        $waitingRows = $db->table('production_wip')
            ->select('id, qty')
            ->where([
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $machiningProcessId,
                'status'          => 'WAITING',
            ])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $waitingTotal = 0;
        foreach ($waitingRows as $wr) $waitingTotal += (int)($wr['qty'] ?? 0);

        // Available total = waiting + previous allocation
        $available = $waitingTotal + $prevAllocQty;

        // Alokasi tidak boleh melebihi available
        $newAllocQty = min($planQty, $available);

        $delta = $newAllocQty - $prevAllocQty;

        // Jika delta > 0: perlu konsumsi tambahan dari WAITING pool
        if ($delta > 0) {
            $need = $delta;

            foreach ($waitingRows as $wr) {
                $rowId = (int)$wr['id'];
                $q     = (int)$wr['qty'];

                if ($rowId <= 0 || $q <= 0) continue;

                if ($q <= $need) {
                    // habiskan row ini
                    $db->table('production_wip')->where('id', $rowId)->delete();
                    $need -= $q;
                } else {
                    // kurangi sebagian
                    $db->table('production_wip')->where('id', $rowId)->update(['qty' => $q - $need]);
                    $need = 0;
                }

                if ($need <= 0) break;
            }
        }
        // Jika delta < 0: kembalikan ke WAITING pool
        elseif ($delta < 0) {
            $back = abs($delta);
            if ($back > 0) {
                $payload = [
                    'production_date' => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevProcessId,
                    'to_process_id'   => $machiningProcessId,
                    'qty'             => $back,
                    'source_table'    => 'daily_schedule_items',
                    'source_id'       => $scheduleItemId,
                    'status'          => 'WAITING',
                ];
                if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('production_wip')->insert($payload);
            }
        }

        // Upsert row alokasi SCHEDULED (prev -> machining)
        $now = date('Y-m-d H:i:s');

        if ($allocRow) {
            if (($allocRow['status'] ?? '') !== 'DONE') {
                $payload = [
                    'qty'    => $newAllocQty,
                    'status' => 'SCHEDULED',
                ];
                if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

                $db->table('production_wip')->where('id', (int)$allocRow['id'])->update($payload);
            }
        } else {
            $payload = $allocKey + [
                'qty'    => $newAllocQty,
                'status' => 'SCHEDULED',
            ];
            if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
            if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

            $db->table('production_wip')->insert($payload);
        }
    }

    /* ============================================
     * INDEX
     * ============================================ */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Machining')
            ->get()
            ->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        $actuals = $db->table('machining_hourly')
            ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()
            ->getResultArray();

        $actualMap = [];
        foreach ($actuals as $a) {
            $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
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
     * AJAX: PRODUCT + TARGET (FLOW saja)
     * ============================================ */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdMC = $this->getProcessIdMachining($db);

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        if ($totalSecond <= 0) return $this->response->setJSON([]);

        $products = $db->table('product_process_flows ppf')
            ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdMC)
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($products as &$p) {
            $cycle  = (int)($p['cycle_time'] ?? 0);
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
     * STORE
     * - Simpan schedule
     * - Incoming WIP dari proses sebelumnya -> jadi SCHEDULED (alokasi sesuai plan)
     * - Outgoing WIP ke proses berikutnya -> WAITING (qty = plan)
     * ============================================ */
    public function store()
    {
        $db    = db_connect();
        $date  = (string)$this->request->getPost('date');
        $items = $this->request->getPost('items');

        if (!$date || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $db->transBegin();

        try {
            $processIdMC = $this->getProcessIdMachining($db);

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                $planInput = (int)($row['plan'] ?? 0);
                if ($planInput < 0) $planInput = 0;
                if ($planInput > 1200) $planInput = 1200;
                if ($planInput <= 0) continue; // plan wajib

                if (!$this->validateProductHasFlow($db, $productId, $processIdMC)) continue;

                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();
                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                if ($cycle <= 0 || $cavity <= 0) continue;

                $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                $targetPerHour = (int)floor((3600 / $cycle) * $cavity * $eff);

                // ===== DAILY SCHEDULE HEADER =====
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
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

                    if ($db->fieldExists('process_id', 'daily_schedules')) {
                        $insertHeader['process_id'] = $processIdMC;
                    }

                    $db->table('daily_schedules')->insert($insertHeader);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];
                }

                // ===== DAILY SCHEDULE ITEM UPSERT (1 row per machine) =====
                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'machine_id'        => $machineId
                    ])
                    ->get()
                    ->getRowArray();

                $dataItem = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $planInput,
                    'is_selected'       => 1
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')
                        ->where('id', (int)$existItem['id'])
                        ->update($dataItem);
                    $itemId = (int)$existItem['id'];
                } else {
                    $db->table('daily_schedule_items')->insert($dataItem);
                    $itemId = (int)$db->insertID();
                }

                // =====================================================
                // 1) INCOMING: dari proses sebelumnya -> Machining jadi SCHEDULED (alokasi sesuai plan)
                // =====================================================
                $prevProcessId = $this->resolvePrevProcessByFlow($db, $productId, $processIdMC);
                if ($prevProcessId) {
                    $this->allocateIncomingWipToMachiningSchedule(
                        $db,
                        $date,
                        $productId,
                        $prevProcessId,
                        $processIdMC,
                        $itemId,
                        $planInput
                    );
                }

                // =====================================================
                // 2) OUTGOING: Machining -> next process = WAITING (qty=plan)
                // =====================================================
                $nextProcessId = $this->resolveNextProcessByFlow($db, $productId, $processIdMC);
                if ($nextProcessId) {
                    $this->upsertOutgoingWipToNextProcess(
                        $db,
                        $date,
                        $productId,
                        $planInput,
                        $processIdMC,
                        $nextProcessId,
                        'daily_schedule_items',
                        $itemId
                    );
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Schedule Machining tersimpan + WIP updated.');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
