<?php

namespace App\Controllers\Painting;

use App\Controllers\BaseController;

class PaintingHourlyController extends BaseController
{
    /* =========================
     * ADMIN CHECK
     * ========================= */
    private function isAdminSession(): bool
    {
        $role = strtoupper((string)(session()->get('role') ?? ''));
        return $role === 'ADMIN';
    }

    /* =========================
     * PROCESS ID
     * ========================= */
    private function getProcessIdByName($db, string $processName): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', $processName)
            ->get()
            ->getRowArray();

        return (int)($row['id'] ?? 0);
    }

    private function getProcessIdMachining($db): int
    {
        $id = $this->getProcessIdByName($db, 'Painting');
        if ($id <= 0) throw new \Exception('Process "Machining" belum ada di master production_processes');
        return $id;
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

    /* =========================================================
     * ✅ NG TOTAL CALC FROM DETAILS
     * ========================================================= */
    private function calcNgTotalFromDetails($ngDetails): int
    {
        $total = 0;
        if (!is_array($ngDetails)) return 0;

        foreach ($ngDetails as $d) {
            $ngId = (int)($d['ng_category_id'] ?? 0);
            $qty  = (int)($d['qty'] ?? 0);
            if ($ngId > 0 && $qty > 0) $total += $qty;
        }
        return (int)$total;
    }

    /* =========================================================
     * ✅ SAVE HOURLY
     * ========================================================= */
    private function saveHourlyRows($db, array $items, array $shiftOperators = [], array $shiftLeaders = []): void
    {
        $now = date('Y-m-d H:i:s');
        $hasDetailTable = $db->tableExists('machining_hourly_ng_details');

        // Optimalisasi: Tarik master downtime 
        $downtimeValues = [];
        if ($db->tableExists('downtime_categories')) {
            $dtRows = $db->table('downtime_categories')->get()->getResultArray();
            foreach ($dtRows as $dt) {
                $downtimeValues[(int)$dt['id']] = (int)$dt['value'];
            }
        }

        foreach ($items as $row) {
            if (
                empty($row['date']) || empty($row['shift_id']) ||
                empty($row['machine_id']) || empty($row['product_id']) ||
                empty($row['time_slot_id'])
            ) {
                continue;
            }

            $date      = (string)$row['date'];
            $shiftId   = (int)$row['shift_id'];
            $machineId = (int)$row['machine_id'];
            $productId = (int)$row['product_id'];
            $slotId    = (int)$row['time_slot_id'];

            $fg = (int)($row['fg'] ?? 0);
            $target = isset($row['qty_target']) ? (int)$row['qty_target'] : null;

            // Total NG dari detail
            $ngDetails = $row['ng_details'] ?? [];
            $ngTotal   = $this->calcNgTotalFromDetails($ngDetails);

            // Downtime value from UI
            $dtVal = (int)($row['downtime_penalty'] ?? 0);
            $dtId = null;
            $remark = $row['remark'] ?? null;
            $ngBlank = (int)($row['ng_blank'] ?? 0);

            $exist = $db->table('machining_hourly')
                ->where([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'time_slot_id'    => $slotId,
                ])
                ->get()
                ->getRowArray();
            $opName = $shiftOperators[$shiftId][$machineId][$slotId]
                      ?? $shiftOperators[$shiftId][$machineId]['all']
                      ?? (is_string($shiftOperators[$shiftId][$machineId] ?? null) ? $shiftOperators[$shiftId][$machineId] : null);
            
            $ldName = $shiftLeaders[$shiftId] ?? null;

            if ($exist) {
                $hourlyId = (int)$exist['id'];
                $update = [
                    'qty_fg'               => $fg,
                    'qty_ng'               => $ngTotal,
                    'qty_ng_blank'         => $ngBlank,
                    'qty_target'           => $target,
                    'ng_category'          => null,     
                    'downtime_category_id' => $dtId > 0 ? $dtId : null,
                    'downtime'             => $dtVal,
                    'remark'               => $remark,
                    'operator_name'        => $opName,
                    'leader_name'          => $ldName,
                ];
                if ($db->fieldExists('updated_at', 'machining_hourly')) $update['updated_at'] = $now;

                $db->table('machining_hourly')->where('id', $hourlyId)->update($update);
            } else {
                if ($fg == 0 && $ngTotal == 0 && $dtId == 0 && $dtVal == 0 && empty($opName) && empty($ldName) && $target === null) continue; 

                $insert = [
                    'production_date'      => $date,
                    'shift_id'             => $shiftId,
                    'machine_id'           => $machineId,
                    'product_id'           => $productId,
                    'time_slot_id'         => $slotId,
                    'qty_fg'               => $fg,
                    'qty_ng'               => $ngTotal,
                    'qty_ng_blank'         => $ngBlank,
                    'qty_target'           => $target,
                    'ng_category'          => null,     
                    'downtime_category_id' => $dtId > 0 ? $dtId : null,
                    'downtime'             => $dtVal,
                    'remark'               => $remark,
                    'operator_name'        => $opName,
                    'leader_name'          => $ldName,
                ];
                if ($db->fieldExists('created_at', 'machining_hourly')) $insert['created_at'] = $now;
                if ($db->fieldExists('updated_at', 'machining_hourly')) $insert['updated_at'] = $now;

                $db->table('machining_hourly')->insert($insert);
                $hourlyId = (int)$db->insertID();
            }

            if ($hasDetailTable && $hourlyId > 0) {
                $db->table('machining_hourly_ng_details')
                    ->where('machining_hourly_id', $hourlyId)
                    ->delete();

                if (is_array($ngDetails)) {
                    foreach ($ngDetails as $d) {
                        $ngId = (int)($d['ng_category_id'] ?? 0);
                        $qty  = (int)($d['qty'] ?? 0);
                        if ($ngId <= 0 || $qty <= 0) continue;

                        $payload = [
                            'machining_hourly_id' => $hourlyId,
                            'ng_category_id'      => $ngId,
                            'qty'                 => $qty,
                            'created_at'          => $now,
                        ];
                        if ($db->fieldExists('updated_at', 'machining_hourly_ng_details')) $payload['updated_at'] = $now;

                        $db->table('machining_hourly_ng_details')->insert($payload);
                    }
                }
            }

            // Save downtime details
            $dtDetails = $row['dt_details'] ?? [];
            if ($db->tableExists('machining_hourly_downtime_details') && $hourlyId > 0) {
                $db->table('machining_hourly_downtime_details')
                    ->where('machining_hourly_id', $hourlyId)
                    ->delete();

                if (is_array($dtDetails)) {
                    foreach ($dtDetails as $d) {
                        $dtCatId = (int)($d['downtime_category_id'] ?? 0);
                        $mins = (int)($d['downtime_minute'] ?? 0);
                        if ($dtCatId <= 0 && $dtCatId !== -1) continue;

                        $payload = [
                            'machining_hourly_id'  => $hourlyId,
                            'downtime_category_id' => $dtCatId === -1 ? 0 : $dtCatId,
                            'downtime_minute'      => $mins,
                            'created_at'           => $now,
                            'updated_at'           => $now,
                        ];

                        $db->table('machining_hourly_downtime_details')->insert($payload);
                    }
                }
            }
        }
    }

    /* =========================
     * SHIFT MC LIST + SHIFT 3 FLAG
     * ========================= */
    private function getMachiningShifts($db, string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->where('day_group', $this->getDayGroup($date))
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($shifts as &$s) {
            $code = (int)($s['shift_code'] ?? 0);
            $name = (string)($s['shift_name'] ?? '');
            $s['is_shift3'] = ($code === 3) || (preg_match('/\b3\b/', $name) === 1) || (stripos($name, 'shift 3') !== false);
        }
        unset($s);

        return $shifts;
    }

    private function getShiftEndDateTime($db, int $shiftId, string $date, \DateTimeZone $tz): ?\DateTime
    {
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('ts.time_start', 'ASC')
            ->get()
            ->getResultArray();

        if (!$slots) return null;

        $last = end($slots);
        $startStr = (string)($last['time_start'] ?? '00:00:00');
        $endStr   = (string)($last['time_end'] ?? '00:00:00');

        $startDT = new \DateTime($date . ' ' . $startStr, $tz);
        $endDT   = new \DateTime($date . ' ' . $endStr, $tz);

        if ($endDT <= $startDT) $endDT->modify('+1 day');

        return $endDT;
    }

    private function canFinishShift($db, string $date): array
    {
        if ($this->isAdminSession()) {
            return [true, null, null];
        }

        $tz  = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);

        $shifts = $this->getMachiningShifts($db, $date);
        $shift3 = null;

        foreach ($shifts as $s) {
            if (!empty($s['is_shift3'])) { $shift3 = $s; break; }
        }

        if (!$shift3) return [false, null, 'Shift 3 Machining tidak ditemukan di master shifts'];

        $endDT = $this->getShiftEndDateTime($db, (int)$shift3['id'], $date, $tz);
        if (!$endDT) return [false, null, 'Time slot Shift 3 belum diset (shift_time_slots kosong)'];

        if ($now < $endDT) return [false, $endDT, 'Finish Shift hanya bisa setelah Shift 3 selesai'];

        return [true, $endDT, null];
    }

    /* =========================
     * FLOW HELPERS
     * ========================= */
    private function resolveNextProcessId($db, int $productId, int $fromProcessId): ?int
    {
        $flows = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getResultArray();

        if (!$flows) return null;

        $idx = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === (int)$fromProcessId) { $idx = $i; break; }
        }
        if ($idx === null) return null;

        return isset($flows[$idx + 1]) ? (int)$flows[$idx + 1]['process_id'] : null;
    }

    private function resolvePrevProcessId($db, int $productId, int $toProcessId): ?int
    {
        $flows = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getResultArray();

        if (!$flows) return null;

        $idx = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === (int)$toProcessId) { $idx = $i; break; }
        }
        if ($idx === null) return null;

        return isset($flows[$idx - 1]) ? (int)$flows[$idx - 1]['process_id'] : null;
    }

    /* =========================================================
     * UPDATE STOCK MACHINING saat hourly disimpan
     * ========================================================= */
    private function syncMachiningWipStockFromHourly($db, string $date): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->tableExists('machining_hourly')) return;

        $wipDateCol  = $this->detectWipDateColumn($db);
        $mcProcessId = $this->getProcessIdMachining($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        if (!$hasStock) return;

        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Painting')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

        // Build list of scheduled machine+product pairs for Painting
        $scheduledPairs = [];
        foreach ($items as $si) {
            $scheduledPairs[] = (int)$si['machine_id'] . '_' . (int)$si['product_id'];
        }

        $actualRows = $db->table('machining_hourly')
            ->select('machine_id, product_id, SUM(qty_fg) AS fg_total')
            ->where('production_date', $date)
            ->groupBy('machine_id, product_id')
            ->get()
            ->getResultArray();

        $actualMap = [];
        foreach ($actualRows as $a) {
            $mid = (int)$a['machine_id'];
            $pid = (int)$a['product_id'];
            $key = $mid . '_' . $pid;
            if (in_array($key, $scheduledPairs)) {
                $actualMap[$key] = (int)($a['fg_total'] ?? 0);
            }
        }

        $now = date('Y-m-d H:i:s');

        foreach ($items as $si) {
            $dsiId     = (int)$si['dsi_id'];
            $machineId = (int)$si['machine_id'];
            $productId = (int)$si['product_id'];

            if ($dsiId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $qtyA = (int)($actualMap[$machineId.'_'.$productId] ?? 0);

            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $mcProcessId);
            if (!$prevProcessId) continue;

            $key = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $mcProcessId,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $dsiId,
            ];

            $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

            if (!$exist) {
                $payload = $key + [
                    'qty'    => 0,
                    'status' => 'SCHEDULED',
                    'stock'  => $qtyA,
                ];
                if ($hasQtyIn)  $payload['qty_in']  = 0;
                if ($hasQtyOut) $payload['qty_out'] = 0;

                if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
                if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

                $db->table('production_wip')->insert($payload);
            } else {
                $upd = ['stock' => $qtyA];
                if ($db->fieldExists('updated_at', 'production_wip')) $upd['updated_at'] = $now;
                $db->table('production_wip')->where('id', (int)$exist['id'])->update($upd);
            }
        }
    }

    /* =========================
     * WIP UPSERT (NEXT PROCESS)
     * ========================= */
    private function upsertWipNextProcess(
        $db, string $date, int $productId, int $fromProcessId, int $toProcessId,
        int $qtyMove, string $sourceTable, int $sourceId
    ): void {
        if (!$db->tableExists('production_wip')) return;

        $wipDateCol = $this->detectWipDateColumn($db);

        $key = [
            $wipDateCol        => $date,
            'product_id'       => $productId,
            'from_process_id'  => $fromProcessId,
            'to_process_id'    => $toProcessId,
            'source_table'     => $sourceTable,
            'source_id'        => $sourceId,
        ];

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');

        $payload = $key + ['qty' => $qtyMove, 'status' => 'WAITING'];

        if ($db->fieldExists('qty_in', 'production_wip'))  $payload['qty_in']  = $qtyMove;
        if ($db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = 0;
        
        if ($db->fieldExists('stock', 'production_wip')) {
            // JANGAN hardcode stock = 0. Tarik running balance terakhir proses tujuan
            // agar flow ledger tidak ter-reset ke 0 oleh scheduler!
            $stockCol = 'stock';
            $wipRow = $db->table('production_wip')
                         ->select($stockCol)
                         ->where('to_process_id', $toProcessId)
                         ->where('product_id', $productId)
                         ->where("$wipDateCol <=", $date)
                         ->orderBy($wipDateCol, 'DESC')
                         ->orderBy('id', 'DESC')
                         ->limit(1)
                         ->get()->getRowArray();
            $payload['stock'] = $wipRow ? (int)$wipRow[$stockCol] : 0;
        }

        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            if (strtoupper((string)($exist['status'] ?? '')) === 'DONE') return;
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /* =========================
     * FINISH SHIFT TRANSFER (Machining)
     * ========================= */
    private function finishMachiningTransferFlow($db, string $date, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        if (!$db->tableExists('machining_hourly')) return 0;

        $wipDateCol  = $this->detectWipDateColumn($db);
        $mcProcessId = $this->getProcessIdMachining($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        $now = date('Y-m-d H:i:s');

        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Painting')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return 0;

        // Build scheduled pairs for Painting to filter actuals
        $scheduledPairs = [];
        foreach ($items as $si) {
            $scheduledPairs[] = (int)$si['machine_id'] . '_' . (int)$si['product_id'];
        }

        $actualRows = $db->table('machining_hourly')
            ->select('machine_id, product_id, SUM(qty_fg) AS fg_total')
            ->where('production_date', $date)
            ->groupBy('machine_id, product_id')
            ->get()
            ->getResultArray();

        $actualMap = [];
        foreach ($actualRows as $a) {
            $mid = (int)$a['machine_id'];
            $pid = (int)$a['product_id'];
            $key = $mid . '_' . $pid;
            if (in_array($key, $scheduledPairs)) {
                $actualMap[$key] = (int)($a['fg_total'] ?? 0);
            }
        }

        $processed = 0;

        foreach ($items as $si) {
            $dsiId     = (int)$si['dsi_id'];
            $machineId = (int)$si['machine_id'];
            $productId = (int)$si['product_id'];
            $qtyPlan   = (int)($si['target_per_shift'] ?? 0);

            if ($dsiId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $qtyA = (int)($actualMap[$machineId.'_'.$productId] ?? 0);

            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $mcProcessId);
            $nextProcessId = $this->resolveNextProcessId($db, $productId, $mcProcessId);

            if ($prevProcessId) {
                $keyInbound = [
                    $wipDateCol        => $date,
                    'product_id'       => $productId,
                    'from_process_id'  => $prevProcessId,
                    'to_process_id'    => $mcProcessId,
                    'source_table'     => 'daily_schedule_items',
                    'source_id'        => $dsiId,
                ];

                $inbound = $db->table('production_wip')->where($keyInbound)->get()->getRowArray();

                if (!$inbound) {
                    $ins = $keyInbound + ['qty' => $qtyPlan, 'status' => 'WAITING'];
                    if ($hasQtyIn)  $ins['qty_in']  = max(0, $qtyPlan - $qtyA);
                    if ($hasQtyOut) $ins['qty_out'] = 0;
                    if ($hasStock)  $ins['stock']   = $qtyA;

                    if ($db->fieldExists('created_at', 'production_wip')) $ins['created_at'] = $now;
                    if ($db->fieldExists('updated_at', 'production_wip')) $ins['updated_at'] = $now;

                    $db->table('production_wip')->insert($ins);
                    $inbound = $db->table('production_wip')->where($keyInbound)->get()->getRowArray();
                }

                if ($inbound && !$forceAdmin && strtoupper((string)($inbound['status'] ?? '')) === 'DONE') {
                    $processed++;
                    continue;
                }

                $stockNow    = $hasStock ? (int)($inbound['stock'] ?? 0) : 0;
                $transferQty = max($stockNow, $qtyA);

                $updA = ['status' => 'DONE'];
                if ($hasQtyOut) $updA['qty_out'] = $transferQty;
                if ($hasStock)  $updA['stock']   = 0;
                if ($hasQtyIn)  $updA['qty_in']  = max(0, $qtyPlan - $transferQty);
                if ($db->fieldExists('updated_at', 'production_wip')) $updA['updated_at'] = $now;

                $db->table('production_wip')->where('id', (int)$inbound['id'])->update($updA);

                if ($nextProcessId > 0 && $transferQty > 0) {
                    $this->upsertWipNextProcess(
                        $db, $date, $productId, $mcProcessId, (int)$nextProcessId,
                        $transferQty, 'daily_schedule_items', $dsiId
                    );
                }
            }
            $processed++;
        }
        return $processed;
    }

    /* =========================
     * INDEX (Menampilkan View)
     * ========================= */
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $isAdmin  = $this->isAdminSession();

        $operatorModel = new \App\Models\MasterOperatorModel();
        $operators = $operatorModel->where('section', 'Painting')->orderBy('operator_name', 'ASC')->findAll();

        // ✅ NG Categories master 
        $ngCategories = $db->table('ng_categories')
            ->select('id, ng_code, ng_name')
            ->where('process_name', 'Painting')
            ->where('is_active', 1)
            ->orderBy('ng_code', 'ASC')
            ->get()
            ->getResultArray();

        $mcProcessId = $this->getProcessIdMachining($db);
        
        // Master Downtime Categories
        $downtimes = [];
        if ($db->tableExists('downtime_categories')) {
            $downtimes = $db->table('downtime_categories')
                ->where('process_id', $mcProcessId)
                ->where('is_active', 1)
                ->orderBy('downtime_name', 'ASC')
                ->get()->getResultArray();
        }

        $shifts = $this->getMachiningShifts($db, $date);

        foreach ($shifts as &$shift) {

            // Dapatkan seluruh Slot Waktu — raw query untuk is_break
            $allSlots = $db->query(
                "SELECT ts.id, ts.time_start, ts.time_end, sts.is_break
                 FROM shift_time_slots sts
                 JOIN time_slots ts ON ts.id = sts.time_slot_id
                 WHERE sts.shift_id = ?
                 ORDER BY sts.id ASC",
                [(int)$shift['id']]
            )->getResultArray();

            $filteredSlots = $allSlots;

            $totalMinute = 0;
            foreach ($filteredSlots as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $mins = (int)(($end - $start) / 60);

                // Slot istirahat: tidak masuk hitungan total menit aktif
                $isBreak = (int)($slot['is_break'] ?? 0);
                $slot['minute']   = $isBreak ? 0 : max(0, $mins);
                $slot['is_break'] = $isBreak;
                if (!$isBreak) $totalMinute += max(0, $mins);
            }
            unset($slot);

            $shift['slots'] = $filteredSlots;
            $shift['total_minute'] = $totalMinute;

            // Dandori Map: machine_id => time_slot_id => { activity, product_id }
            $dandoriRecords = $db->table('machining_dandori')
                ->where('dandori_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['dandori_map'] = []; // machine_id => time_slot_id => info
            foreach ($dandoriRecords as $d) {
                $mId = (int)$d['machine_id'];
                $tId = (int)($d['time_slot_id'] ?? 0);
                if ($tId > 0) {
                    $shift['dandori_map'][$mId][$tId] = [
                        'activity'   => $d['activity'] ?? 'Dandori',
                        'product_id' => (int)$d['product_id'],
                        'dandori_minute' => (int)($d['dandori_minute'] ?? 0),
                    ];
                }
            }

            // 3. Items Schedule
            $rawItems = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.id AS dsi_id,
                    dsi.machine_id,
                    m.line_position,
                    m.machine_code,
                    dsi.product_id,
                    p.part_no,
                    p.part_name,
                    IFNULL(p.weight_machining, 0) AS weight_mc,
                    dsi.target_per_shift,
                    dsi.end_time_slot_id,
                    dsi.active_slot_ids,
                    dsi.slot_custom_times
                ')
                ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->join('machines m', 'm.id = dsi.machine_id')
                ->join('products p', 'p.id = dsi.product_id')
                ->where('ds.schedule_date', $date)
                ->where('ds.shift_id', $shift['id'])
                ->where('ds.section', 'Painting')
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            $shift['items'] = [];
            foreach ($rawItems as $ritem) {
                $mId = $ritem['machine_id'];
                $pId = $ritem['product_id'];
                $isDandori = isset($shift['dandori_map'][$mId][$pId]['is_dandori']);
                if ($ritem['target_per_shift'] > 0 || $isDandori) {
                    $shift['items'][] = $ritem;
                }
            }

            // weight_mc_map: product_id => weight_machining (gram)
            $shift['weight_mc_map'] = [];
            foreach ($shift['items'] as $it) {
                $pid = (int)$it['product_id'];
                $shift['weight_mc_map'][$pid] = (float)($it['weight_mc'] ?? 0);
            }

            // 4. Hourly Maps — ONLY for scheduled machine+product pairs
            // Build list of scheduled machine_id+product_id to prevent data mixing
            $scheduledMachineProducts = [];
            foreach ($shift['items'] as $si) {
                $scheduledMachineProducts[(int)$si['machine_id'] . '_' . (int)$si['product_id']] = true;
            }

            $hourly = $db->table('machining_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()
                ->getResultArray();

            $shift['hourly_map'] = [];
            $hourlyIdMap = [];

            foreach ($hourly as $h) {
                $mid = (int)$h['machine_id'];
                $pid = (int)$h['product_id'];
                $sid = (int)$h['time_slot_id'];

                // Only include hourly data that belongs to THIS department's schedule
                if (!isset($scheduledMachineProducts[$mid . '_' . $pid])) continue;

                $shift['hourly_map'][$mid][$pid][$sid] = $h;
                if (isset($h['id'])) $hourlyIdMap[$mid.'_'.$pid.'_'.$sid] = (int)$h['id'];
            }

            // 5. NG Detail Maps
            $shift['ng_detail_map'] = [];
            if ($db->tableExists('machining_hourly_ng_details') && $hourlyIdMap) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $hourlyIdMap))));
                if ($ids) {
                    $rows = $db->table('machining_hourly_ng_details')
                        ->whereIn('machining_hourly_id', $ids)
                        ->get()
                        ->getResultArray();

                    $byHourlyId = [];
                    foreach ($rows as $r) {
                        $hid = (int)($r['machining_hourly_id'] ?? 0);
                        if ($hid <= 0) continue;
                        $byHourlyId[$hid][] = [
                            'ng_category_id' => (int)($r['ng_category_id'] ?? 0),
                            'qty'            => (int)($r['qty'] ?? 0),
                        ];
                    }

                    foreach ($hourlyIdMap as $k => $hid) {
                        [$midStr, $pidStr, $sidStr] = explode('_', $k);
                        $mid = (int)$midStr; $pid = (int)$pidStr; $sid = (int)$sidStr;
                        $shift['ng_detail_map'][$mid][$pid][$sid] = $byHourlyId[$hid] ?? [];
                    }
                }
            }

            // 6. Downtime Detail Maps
            $shift['dt_detail_map'] = [];
            if ($db->tableExists('machining_hourly_downtime_details') && $hourlyIdMap) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $hourlyIdMap))));
                if ($ids) {
                    $rows = $db->table('machining_hourly_downtime_details d')
                        ->select('d.machining_hourly_id, d.downtime_category_id, d.downtime_minute, dc.downtime_name')
                        ->join('downtime_categories dc', 'dc.id = d.downtime_category_id', 'left')
                        ->whereIn('d.machining_hourly_id', $ids)
                        ->get()
                        ->getResultArray();

                    $byHourlyId = [];
                    foreach ($rows as $r) {
                        $hid = (int)($r['machining_hourly_id'] ?? 0);
                        if ($hid <= 0) continue;
                        
                        $catId = (int)($r['downtime_category_id'] ?? 0);
                        if ($catId === 0) $catId = -1; // -1 represents Dandori in the UI

                        $byHourlyId[$hid][] = [
                            'downtime_category_id' => $catId,
                            'downtime_minute'      => (int)($r['downtime_minute'] ?? 0),
                            'downtime_name'        => $catId === -1 ? 'Dandori' : (string)($r['downtime_name'] ?? ''),
                        ];
                    }

                    foreach ($hourlyIdMap as $k => $hid) {
                        [$midStr, $pidStr, $sidStr] = explode('_', $k);
                        $mid = (int)$midStr; $pid = (int)$pidStr; $sid = (int)$sidStr;
                        $shift['dt_detail_map'][$mid][$pid][$sid] = $byHourlyId[$hid] ?? [];
                    }
                }
            }
        }
        unset($shift);

        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, $date);

        return view('painting/hourly/index', [
            'date'         => $date,
            'operators'    => $operators,
            'shifts'       => $shifts,
            'canFinish'    => $canFinish,
            'isAdmin'      => $isAdmin,
            'shift3EndAt'  => $shift3EndDT ? $shift3EndDT->format('Y-m-d H:i:s') : null,
            'finishError'  => $finishError,
            'ngCategories' => $ngCategories,
            'downtimes'    => $downtimes
        ]);
    }

    /* =========================
     * STORE
     * ========================= */
    public function store()
    {
        $db    = db_connect();
        // Support JSON-consolidated items to bypass max_input_vars limit
        $itemsJson = $this->request->getPost('items_json');
        $items = $itemsJson ? json_decode($itemsJson, true) : ($this->request->getPost('items') ?? []);
        $shiftOperators = $this->request->getPost('operators') ?? [];
        $shiftLeaders   = $this->request->getPost('leaders') ?? [];

        $date = null;
        foreach ($items as $r) {
            if (!empty($r['date'])) { $date = (string)$r['date']; break; }
        }

        $db->transBegin();
        try {
            $this->saveHourlyRows($db, $items, $shiftOperators, $shiftLeaders);

            if ($date) {
                $this->syncMachiningWipStockFromHourly($db, $date);

                $rowTargets = $this->request->getPost('row_targets') ?? [];
                if (!empty($rowTargets)) {
                    $this->syncRowTargets($db, $date, $rowTargets);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with('success', 'Hourly Machining tersimpan + Stock WIP Machining ter-update');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * FINISH SHIFT
     * ========================= */
    public function finishShift()
    {
        $db    = db_connect();
        // Support JSON-consolidated items to bypass max_input_vars limit
        $itemsJson = $this->request->getPost('items_json');
        $items = $itemsJson ? json_decode($itemsJson, true) : $this->request->getPost('items');
        $date  = $this->request->getPost('global_date'); 
        $shiftOperators = $this->request->getPost('operators') ?? [];
        $shiftLeaders   = $this->request->getPost('leaders') ?? [];

        if (!$items || !is_array($items) || empty($date)) {
            return redirect()->back()->with('error', 'Data gagal disimpan. Coba lagi.');
        }

        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, $date);
        if (!$canFinish) {
            $msg = $finishError ?: 'Belum bisa Finish Shift';
            if ($shift3EndDT) $msg .= ' (Shift 3 selesai: '.$shift3EndDT->format('Y-m-d H:i:s').')';
            return redirect()->back()->with('error', $msg);
        }

        $forceAdmin = $this->isAdminSession();

        $db->transBegin();
        try {
            // 1) simpan hourly (dengan NG detail)
            $this->saveHourlyRows($db, $items, $shiftOperators, $shiftLeaders);

            // 1.5) update row target sum to schedule
            $rowTargets = $this->request->getPost('row_targets') ?? [];
            if (!empty($rowTargets)) {
                $this->syncRowTargets($db, $date, $rowTargets);
            }

            // 2) update stock dari qty A
            $this->syncMachiningWipStockFromHourly($db, $date);

            // 3) transfer flow 
            $processed = $this->finishMachiningTransferFlow($db, $date, $forceAdmin);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Finish Shift sukses. Transfer ke proses berikutnya selesai. (rows: '.$processed.')'
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function endDandori()
    {
        $db = db_connect();
        $date       = $this->request->getPost('date');
        $shiftId    = (int)$this->request->getPost('shift_id');
        $machineId  = (int)$this->request->getPost('machine_id');
        $timeSlotId = (int)$this->request->getPost('time_slot_id');

        if (!$date || !$shiftId || !$machineId || !$timeSlotId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Invalid parameters']);
        }

        $now = time();

        $dandoriRecord = $db->table('machining_dandori')
            ->where('dandori_date', $date)
            ->where('shift_id', $shiftId)
            ->where('machine_id', $machineId)
            ->where('time_slot_id', $timeSlotId)
            ->get()->getRowArray();

        if (!$dandoriRecord) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Dandori record not found']);
        }

        $slot = $db->table('time_slots')->where('id', $timeSlotId)->get()->getRowArray();
        if (!$slot) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Time slot not found']);
        }

        $prodDateISO = $date;
        $startStr = substr((string)($slot['time_start'] ?? ''), 0, 5);
        $startDate = date('Y-m-d H:i:s', strtotime("{$prodDateISO} {$startStr}:00"));
        
        $startHour = (int)date('H', strtotime($startDate));
        if ($startHour >= 0 && $startHour < 7) {
            $startDate = date('Y-m-d H:i:s', strtotime($startDate . ' +1 day'));
        }

        $startTs = strtotime($startDate);
        if ($now <= $startTs) {
            $diffMins = 0;
        } else {
            $diffMins = (int)floor(($now - $startTs) / 60);
        }

        // Limit the actual dandori minutes to the original scheduled minutes
        $originalMins = (int)($dandoriRecord['dandori_minute'] ?? 0);
        if ($diffMins > $originalMins) $diffMins = $originalMins;

        $db->table('machining_dandori')
            ->where('id', $dandoriRecord['id'])
            ->update(['dandori_minute' => $diffMins]);

        return $this->response->setJSON(['ok' => true, 'dandori_minute' => $diffMins, 'msg' => 'Dandori ended']);
    }

    private function syncRowTargets($db, string $date, array $rowTargets): void
    {
        foreach ($rowTargets as $key => $sumTarget) {
            $parts = explode('_', $key);
            if (count($parts) === 3) {
                $sId = (int)$parts[0];
                $mId = (int)$parts[1];
                $pId = (int)$parts[2];
                
                $dsRows = $db->table('daily_schedules')
                    ->select('id')
                    ->where('schedule_date', $date)
                    ->where('shift_id', $sId)
                    ->get()->getResultArray();
                    
                $dsIds = array_column($dsRows, 'id');
                if (!empty($dsIds)) {
                    $db->table('daily_schedule_items')
                        ->whereIn('daily_schedule_id', $dsIds)
                        ->where('machine_id', $mId)
                        ->where('product_id', $pId)
                        ->update(['target_per_shift' => (int)$sumTarget]);
                }
            }
        }
    }
}
