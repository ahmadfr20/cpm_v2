<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyBushingDailyProductionController extends BaseController
{
    /* =========================
     * PROCESS ID RESOLVER (robust)
     * ========================= */
    private function getProcessIdByCodeOrName($db, ?string $code, string $nameLike): int
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
            ->where('process_name', $nameLike)
            ->get()->getRowArray();
        if (!empty($row['id'])) return (int)$row['id'];

        $row = $db->table('production_processes')
            ->select('id')
            ->like('process_name', $nameLike)
            ->get()->getRowArray();
        if (!empty($row['id'])) return (int)$row['id'];

        return 0;
    }

    private function getProcessIdAssyBushing($db): int
    {
        $id = $this->getProcessIdByCodeOrName($db, 'AB', 'Assy Bushing');
        if ($id <= 0) throw new \Exception('Process "Assy Bushing" belum ada di master production_processes');
        return $id;
    }

    /* =========================
     * Detect kolom tanggal yang dipakai di production_wip
     * ========================= */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';

        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    /* =========================
     * SHIFT MC LIST
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
            $s['is_shift3'] = ($code === 3) || (stripos($name, '3') !== false);
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

    /* =========================
     * ✅ INDEX (DIBALIKIN)
     * ========================= */
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        $shifts = $this->getMachiningShifts($db);

        foreach ($shifts as &$shift) {
            // slots shift
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()
                ->getResultArray();

            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $slot['minute'] = ($end - $start) / 60;
                $totalMinute += $slot['minute'];
            }
            unset($slot);

            $shift['total_minute'] = $totalMinute;

            // schedule items (Assy Bushing) per shift
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
                ->where('ds.section', 'Assy Bushing')
                ->where('dsi.target_per_shift >', 0)
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            // hourly data (Assy Bushing)
            $hourly = $db->table('machining_assy_bushing_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()
                ->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map'][(int)$h['machine_id']][(int)$h['product_id']][(int)$h['time_slot_id']] = $h;
            }
        }
        unset($shift);

        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, $date);

        // ✅ GANTI view di bawah kalau path view kamu berbeda
        return view('machining/assy_bushing/daily_production/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'canFinish'    => $canFinish,
            'shift3EndAt'  => $shift3EndDT ? $shift3EndDT->format('Y-m-d H:i:s') : null,
            'finishError'  => $finishError,
        ]);
    }

    /* =========================
     * SAVE HOURLY (Assy Bushing)
     * ========================= */
    private function saveHourlyRows($db, array $items): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($items as $row) {
            if (
                empty($row['date']) ||
                empty($row['shift_id']) ||
                empty($row['machine_id']) ||
                empty($row['product_id']) ||
                empty($row['time_slot_id'])
            ) continue;

            $payload = [
                'production_date' => (string)$row['date'],
                'shift_id'        => (int)$row['shift_id'],
                'machine_id'      => (int)$row['machine_id'],
                'product_id'      => (int)$row['product_id'],
                'time_slot_id'    => (int)$row['time_slot_id'],
                'qty_fg'          => (int)($row['ok'] ?? $row['fg'] ?? 0),
                'qty_ng'          => (int)($row['ng'] ?? 0),
            ];

            if ($db->fieldExists('ng_category', 'machining_assy_bushing_hourly')) {
                $payload['ng_category'] = $row['ng_remark'] ?? $row['ng_category'] ?? null;
            }
            if ($db->fieldExists('downtime', 'machining_assy_bushing_hourly')) {
                $payload['downtime'] = (int)($row['downtime'] ?? 0);
            }
            if ($db->fieldExists('remark', 'machining_assy_bushing_hourly')) {
                $payload['remark'] = $row['remark'] ?? null;
            }

            if ($db->fieldExists('created_at', 'machining_assy_bushing_hourly')) {
                $payload['created_at'] = $now;
            }
            if ($db->fieldExists('updated_at', 'machining_assy_bushing_hourly')) {
                $payload['updated_at'] = $now;
            }

            $db->table('machining_assy_bushing_hourly')->replace($payload);
        }
    }

    /* =========================================================
     * ✅ SYNC STOCK WIP inbound (prev -> AB) dari hourly OK (SUM qty_fg)
     * ========================================================= */
    private function syncAssyBushingWipStockFromHourly($db, string $date): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->tableExists('machining_assy_bushing_hourly')) return;

        $wipDateCol    = $this->detectWipDateColumn($db);
        $assyProcessId = $this->getProcessIdAssyBushing($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        if (!$hasStock) return;

        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Bushing')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

        $actualRows = $db->table('machining_assy_bushing_hourly')
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

            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $assyProcessId);
            if (!$prevProcessId) continue;

            $key = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $assyProcessId,
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
        $db,
        string $date,
        int $productId,
        int $fromProcessId,
        int $toProcessId,
        int $qtyMove,
        string $sourceTable,
        int $sourceId
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
        $now   = date('Y-m-d H:i:s');

        $payload = $key + [
            'qty'    => $qtyMove,
            'status' => 'WAITING',
        ];
        if ($db->fieldExists('qty_in', 'production_wip'))  $payload['qty_in']  = $qtyMove;
        if ($db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = 0;
        if ($db->fieldExists('stock', 'production_wip'))   $payload['stock']   = $qtyMove;
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            if (($exist['status'] ?? '') === 'DONE') return;
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /* =========================
     * FINISH SHIFT TRANSFER (AB)
     * ========================= */
    private function finishAssyBushingTransferFlow($db, string $date): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        if (!$db->tableExists('machining_assy_bushing_hourly')) return 0;

        $wipDateCol    = $this->detectWipDateColumn($db);
        $assyProcessId = $this->getProcessIdAssyBushing($db);

        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        $now = date('Y-m-d H:i:s');

        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Bushing')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return 0;

        $actualRows = $db->table('machining_assy_bushing_hourly')
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

            if ($dsiId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $qtyA = (int)($actualMap[$machineId.'_'.$productId] ?? 0);

            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $assyProcessId);
            $nextProcessId = $this->resolveNextProcessId($db, $productId, $assyProcessId);

            // inbound DONE
            if ($prevProcessId) {
                $keyInbound = [
                    $wipDateCol        => $date,
                    'product_id'       => $productId,
                    'from_process_id'  => $prevProcessId,
                    'to_process_id'    => $assyProcessId,
                    'source_table'     => 'daily_schedule_items',
                    'source_id'        => $dsiId,
                ];

                $inbound = $db->table('production_wip')->where($keyInbound)->get()->getRowArray();
                if ($inbound) {
                    $qtyInVal = (int)($inbound['qty_in'] ?? $inbound['qty'] ?? 0);

                    $upd = ['status' => 'DONE'];
                    if ($hasQtyOut) $upd['qty_out'] = $qtyInVal;
                    if ($hasStock)  $upd['stock']   = 0;
                    if ($db->fieldExists('updated_at', 'production_wip')) $upd['updated_at'] = $now;

                    $db->table('production_wip')->where('id', (int)$inbound['id'])->update($upd);
                }
            }

            // outbound WAITING
            if ($nextProcessId && $qtyA > 0) {
                $this->upsertWipNextProcess(
                    $db,
                    $date,
                    $productId,
                    $assyProcessId,
                    (int)$nextProcessId,
                    $qtyA,
                    'daily_schedule_items',
                    $dsiId
                );
            }

            $processed++;
        }

        return $processed;
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
        if (!$date) return redirect()->back()->with('error', 'Tanggal tidak ditemukan dari payload');

        $db->transBegin();
        try {
            $this->saveHourlyRows($db, $items);
            $this->syncAssyBushingWipStockFromHourly($db, $date);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with('success', 'Hourly Assy Bushing tersimpan + Stock WIP Assy Bushing ter-update');
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
        if (!$date) return redirect()->back()->with('error', 'Tanggal tidak ditemukan dari payload');

        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, $date);
        if (!$canFinish) {
            $msg = $finishError ?: 'Belum bisa Finish Shift';
            if ($shift3EndDT) $msg .= ' (Shift 3 selesai: '.$shift3EndDT->format('Y-m-d H:i:s').')';
            return redirect()->back()->with('error', $msg);
        }

        $db->transBegin();
        try {
            $this->saveHourlyRows($db, $items);
            $this->syncAssyBushingWipStockFromHourly($db, $date);
            $processed = $this->finishAssyBushingTransferFlow($db, $date);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Finish Shift Assy Bushing sukses: inbound qty_in→qty_out, stock inbound=0, qty A dikirim ke proses berikutnya. (rows: '.$processed.')'
            );
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
