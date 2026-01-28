<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftDailyScheduleController extends BaseController
{
    /* ============================================
     * Helper: ambil process_id by name (fallback by code jika ada)
     * ============================================ */
    private function getProcessId($db, string $processName, ?string $processCode = null): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', $processName)
            ->get()
            ->getRowArray();

        if ($row) return (int)$row['id'];

        if ($processCode && $db->fieldExists('process_code', 'production_processes')) {
            $row2 = $db->table('production_processes')
                ->select('id')
                ->where('process_code', $processCode)
                ->get()
                ->getRowArray();

            if ($row2) return (int)$row2['id'];
        }

        throw new \Exception('Process "'.$processName.'" belum ada di master production_processes');
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
     * Helper: validasi product punya flow process aktif
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
     * UPSERT WIP outgoing (Current -> next) status WAITING
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
     * ALOKASI incoming WIP dari prevProcess -> CurrentProcess:
     * - Mengubah sebagian qty WAITING menjadi SCHEDULED untuk schedule item ini
     * - Delta allocation: naik konsumsi WAITING, turun kembalikan ke WAITING
     * ===================================================== */
    private function allocateIncomingWipToSchedule(
        $db,
        string $date,
        int $productId,
        int $prevProcessId,
        int $currentProcessId,
        int $scheduleItemId,
        int $planQty
    ): void {
        if (!$db->tableExists('production_wip')) return;

        if ($prevProcessId <= 0) {
            // proses pertama
            return;
        }

        // Row alokasi untuk schedule item ini status SCHEDULED
        $allocKey = [
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => $prevProcessId,
            'to_process_id'   => $currentProcessId,
            'source_table'    => 'daily_schedule_items',
            'source_id'       => $scheduleItemId,
        ];

        $allocRow = $db->table('production_wip')->where($allocKey)->get()->getRowArray();
        $prevAllocQty = (int)($allocRow['qty'] ?? 0);

        // pool WAITING
        $waitingRows = $db->table('production_wip')
            ->select('id, qty')
            ->where([
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $currentProcessId,
                'status'          => 'WAITING',
            ])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $waitingTotal = 0;
        foreach ($waitingRows as $wr) $waitingTotal += (int)($wr['qty'] ?? 0);

        $available = $waitingTotal + $prevAllocQty;
        $newAllocQty = min($planQty, $available);

        $delta = $newAllocQty - $prevAllocQty;

        // delta > 0 consume waiting
        if ($delta > 0) {
            $need = $delta;

            foreach ($waitingRows as $wr) {
                $rowId = (int)$wr['id'];
                $q     = (int)$wr['qty'];

                if ($rowId <= 0 || $q <= 0) continue;

                if ($q <= $need) {
                    $db->table('production_wip')->where('id', $rowId)->delete();
                    $need -= $q;
                } else {
                    $db->table('production_wip')->where('id', $rowId)->update(['qty' => $q - $need]);
                    $need = 0;
                }

                if ($need <= 0) break;
            }
        }
        // delta < 0 return to waiting
        elseif ($delta < 0) {
            $back = abs($delta);
            if ($back > 0) {
                $payload = [
                    'production_date' => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevProcessId,
                    'to_process_id'   => $currentProcessId,
                    'qty'             => $back,
                    'source_table'    => 'daily_schedule_items',
                    'source_id'       => $scheduleItemId,
                    'status'          => 'WAITING',
                ];
                if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('production_wip')->insert($payload);
            }
        }

        // upsert allocation row to SCHEDULED
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
            ->get()
            ->getResultArray();

        // Mesin Machining (mengikuti pola kamu)
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        // plan existing section Assy Shaft
        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Shaft')
            ->get()
            ->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        // actual hourly (kalau tabel ada)
        $actualMap = [];
        if ($db->tableExists('machining_assy_shaft_hourly')) {
            $actuals = $db->table('machining_assy_shaft_hourly')
                ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
                ->where('production_date', $date)
                ->groupBy('shift_id, machine_id, product_id')
                ->get()
                ->getResultArray();

            foreach ($actuals as $a) {
                $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
            }
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
     * AJAX: PRODUCT + TARGET (filter harus punya flow Assy Shaft)
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = (int)($this->request->getGet('machine_id') ?? 0);
        $shiftId   = (int)($this->request->getGet('shift_id') ?? 0);

        if ($machineId <= 0 || $shiftId <= 0) {
            return $this->response->setJSON([]);
        }

        // process Assy Shaft (fallback code AS jika ada)
        $assyShaftProcessId = $this->getProcessId($db, 'Assy Shaft', 'AS');

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        if ($totalSecond <= 0) return $this->response->setJSON([]);

        // Produk by machine, tapi FILTER harus punya flow Assy Shaft aktif
        $products = $db->table('machine_products mp')
            ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
            ->join('products p', 'p.id = mp.product_id')
            ->join('product_process_flows ppf', 'ppf.product_id = p.id AND ppf.is_active = 1', 'inner')
            ->where('mp.machine_id', $machineId)
            ->where('mp.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $assyShaftProcessId)
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

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
     * - Incoming WIP dari prev -> Assy Shaft => SCHEDULED (alokasi sesuai plan)
     * - Outgoing WIP Assy Shaft -> next => WAITING (qty = plan)
     * ===================================================== */
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
            $assyShaftProcessId = $this->getProcessId($db, 'Assy Shaft', 'AS');

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                // plan manual dari input view (kalau view kamu pakai target_per_shift)
                $planInput = (int)($row['target_per_shift'] ?? ($row['plan'] ?? 0));
                if ($planInput < 0) $planInput = 0;
                if ($planInput > 1200) $planInput = 1200;
                if ($planInput <= 0) continue;

                // pastikan product punya flow Assy Shaft
                if (!$this->validateProductHasFlow($db, $productId, $assyShaftProcessId)) continue;

                // master product
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
                        'section'       => 'Assy Shaft',
                    ])
                    ->get()
                    ->getRowArray();

                if (!$schedule) {
                    $insertHeader = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Assy Shaft',
                        'is_completed'  => 0,
                        'created_at'    => date('Y-m-d H:i:s'),
                    ];

                    if ($db->fieldExists('process_id', 'daily_schedules')) {
                        $insertHeader['process_id'] = $assyShaftProcessId;
                    }

                    $db->table('daily_schedules')->insert($insertHeader);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];

                    // backfill process_id jika perlu
                    if ($db->fieldExists('process_id', 'daily_schedules') && empty($schedule['process_id'])) {
                        $db->table('daily_schedules')->where('id', $scheduleId)->update([
                            'process_id' => $assyShaftProcessId,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
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
                // 1) INCOMING: prev -> Assy Shaft => SCHEDULED (alokasi sesuai plan)
                // =====================================================
                $prevProcessId = $this->resolvePrevProcessByFlow($db, $productId, $assyShaftProcessId);
                if ($prevProcessId) {
                    $this->allocateIncomingWipToSchedule(
                        $db,
                        $date,
                        $productId,
                        $prevProcessId,
                        $assyShaftProcessId,
                        $itemId,
                        $planInput
                    );
                }

                // =====================================================
                // 2) OUTGOING: Assy Shaft -> next => WAITING qty=plan
                // =====================================================
                $nextProcessId = $this->resolveNextProcessByFlow($db, $productId, $assyShaftProcessId);
                if ($nextProcessId) {
                    $this->upsertOutgoingWipToNextProcess(
                        $db,
                        $date,
                        $productId,
                        $planInput,
                        $assyShaftProcessId,
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
            return redirect()->back()->with('success', 'Schedule Assy Shaft tersimpan + WIP updated.');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
