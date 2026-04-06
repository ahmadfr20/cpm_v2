<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class HourlyController extends BaseController
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
        $id = $this->getProcessIdByName($db, 'Machining');
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
    private function saveHourlyRows($db, array $items): void
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

            // Total NG dari detail
            $ngDetails = $row['ng_details'] ?? [];
            $ngTotal   = $this->calcNgTotalFromDetails($ngDetails);

            // Downtime value
            $dtId   = (int)($row['downtime_category_id'] ?? 0);
            $dtVal  = $dtId > 0 ? ($downtimeValues[$dtId] ?? 0) : 0;
            $remark = $row['remark'] ?? null;

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

            if ($exist) {
                $update = [
                    'qty_fg'               => $fg,
                    'qty_ng'               => $ngTotal,
                    'ng_category'          => null,     
                    'downtime_category_id' => $dtId > 0 ? $dtId : null,
                    'downtime'             => $dtVal,
                    'remark'               => $remark,
                ];
                if ($db->fieldExists('updated_at', 'machining_hourly')) $update['updated_at'] = $now;

                $db->table('machining_hourly')->where('id', (int)$exist['id'])->update($update);
                $hourlyId = (int)$exist['id'];
            } else {
                if ($fg == 0 && $ngTotal == 0 && $dtId == 0) continue; 

                $insert = [
                    'production_date'      => $date,
                    'shift_id'             => $shiftId,
                    'machine_id'           => $machineId,
                    'product_id'           => $productId,
                    'time_slot_id'         => $slotId,
                    'qty_fg'               => $fg,
                    'qty_ng'               => $ngTotal,
                    'ng_category'          => null,
                    'downtime_category_id' => $dtId > 0 ? $dtId : null,
                    'downtime'             => $dtVal,
                    'remark'               => $remark,
                    'created_at'           => $now,
                ];
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
        }
    }

    /* =========================
     * SHIFT MC LIST + SHIFT 3 FLAG
     * ========================= */
    private function getMachiningShifts($db): array
    {
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
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

        $shifts = $this->getMachiningShifts($db);
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
            ->where('ds.section', 'Machining')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

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
            $actualMap[$mid.'_'.$pid] = (int)($a['fg_total'] ?? 0);
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
        if ($db->fieldExists('stock', 'production_wip'))   $payload['stock']   = 0;
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
            ->where('ds.section', 'Machining')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return 0;

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
            $actualMap[$mid.'_'.$pid] = (int)($a['fg_total'] ?? 0);
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
        $operator = session()->get('fullname') ?? '-';

        // ✅ NG Categories master 
        $ngCategories = $db->table('ng_categories')
            ->select('id, ng_code, ng_name')
            ->where('process_name', 'Machining')
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

        $shifts = $this->getMachiningShifts($db);

        foreach ($shifts as &$shift) {

            // 1. Dapatkan End Slot ID (Jika di schedule di set limit slot)
            $scheduleRow = $db->table('daily_schedules')
                ->select('end_time_slot_id')
                ->where('schedule_date', $date)
                ->where('shift_id', $shift['id'])
                ->where('section', 'Machining')
                ->get()->getRowArray();
            $endSlotId = $scheduleRow ? (int)$scheduleRow['end_time_slot_id'] : null;

            // 2. Tarik & Filter Slot Waktu
            $allSlots = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start', 'ASC')
                ->get()
                ->getResultArray();

            $filteredSlots = [];
            foreach($allSlots as $slot) {
                $filteredSlots[] = $slot;
                if($endSlotId > 0 && (int)$slot['id'] === $endSlotId) {
                    break;
                }
            }

            $totalMinute = 0;
            foreach ($filteredSlots as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $slot['minute'] = (int)(($end - $start) / 60);
                $totalMinute += (int)$slot['minute'];
            }
            unset($slot);

            $shift['slots'] = $filteredSlots;
            $shift['total_minute'] = $totalMinute;

            // 3. Items Schedule
            $shift['items'] = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.id AS dsi_id,
                    dsi.machine_id,
                    m.line_position,
                    m.machine_code,
                    dsi.product_id,
                    p.part_no,
                    p.part_name,
                    dsi.target_per_shift
                ')
                ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->join('machines m', 'm.id = dsi.machine_id')
                ->join('products p', 'p.id = dsi.product_id')
                ->where('ds.schedule_date', $date)
                ->where('ds.shift_id', $shift['id'])
                ->where('ds.section', 'Machining')
                ->where('dsi.target_per_shift >', 0)
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            // 4. Hourly Maps
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
        }
        unset($shift);

        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, $date);
        $isAdmin = $this->isAdminSession();

        return view('machining/hourly/index', [
            'date'         => $date,
            'operator'     => $operator,
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
        $items = $this->request->getPost('items') ?? [];

        $date = null;
        foreach ($items as $r) {
            if (!empty($r['date'])) { $date = (string)$r['date']; break; }
        }

        $db->transBegin();
        try {
            $this->saveHourlyRows($db, $items);

            if ($date) {
                $this->syncMachiningWipStockFromHourly($db, $date);
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
        $items = $this->request->getPost('items') ?? [];

        $date = null;
        foreach ($items as $r) {
            if (!empty($r['date'])) { $date = (string)$r['date']; break; }
        }
        if (!$date) {
            return redirect()->back()->with('error', 'Tanggal tidak ditemukan dari payload');
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
            $this->saveHourlyRows($db, $items);

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
}