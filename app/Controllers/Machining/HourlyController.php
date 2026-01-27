<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class HourlyController extends BaseController
{
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

    /* =========================
     * SAVE HOURLY
     * ========================= */
    private function saveHourlyRows($db, array $items): void
    {
        foreach ($items as $row) {
            if (
                empty($row['date']) ||
                empty($row['shift_id']) ||
                empty($row['machine_id']) ||
                empty($row['product_id']) ||
                empty($row['time_slot_id'])
            ) {
                continue;
            }

            $db->table('machining_hourly')->replace([
                'production_date' => (string)$row['date'],
                'shift_id'        => (int)$row['shift_id'],
                'machine_id'      => (int)$row['machine_id'],
                'product_id'      => (int)$row['product_id'],
                'time_slot_id'    => (int)$row['time_slot_id'],
                'qty_fg'          => (int)($row['fg'] ?? 0),
                'qty_ng'          => (int)($row['ng'] ?? 0),
                'ng_category'     => $row['ng_remark'] ?? null,
                'downtime'        => (int)($row['downtime'] ?? 0),
                'remark'          => $row['remark'] ?? null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
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
     * WIP UPSERT (NEXT PROCESS)
     * - status default WAITING
     * - qty = FG aktual saat finish
     * - key: production_date + product + from + to + source_table + source_id
     * ========================= */
    private function upsertWipNextProcess(
        $db,
        string $date,
        int $productId,
        int $fromProcessId,
        int $toProcessId,
        int $qty,
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
        $payload = $key + [
            'qty'    => $qty,
            'status' => 'WAITING',
        ];

        // opsional: field tambahan
        if ($db->fieldExists('qty_in', 'production_wip'))  $payload['qty_in']  = $qty;
        if ($db->fieldExists('stock', 'production_wip'))   $payload['stock']   = $qty;
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            // kalau DONE (opsional, kalau kamu mau lock), bisa skip.
            if (($exist['status'] ?? '') === 'DONE') return;

            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /* =========================
     * MARK INBOUND WIP -> DONE
     * inbound = (prev_process -> Machining)
     * status SCHEDULED / WAITING => DONE saat finish shift
     * ========================= */
    private function markInboundToMachiningDone(
        $db,
        string $date,
        int $productId,
        int $prevProcessId,
        int $mcProcessId,
        int $qtyOut
    ): void {
        if (!$db->tableExists('production_wip')) return;

        // Bisa ada beberapa row inbound (source beda), jadi kita update semua yg relevan
        $builder = $db->table('production_wip')
            ->where('production_date', $date)
            ->where('product_id', $productId)
            ->where('from_process_id', $prevProcessId)
            ->where('to_process_id', $mcProcessId)
            ->groupStart()
                ->where('status', 'SCHEDULED')
                ->orWhere('status', 'WAITING')
            ->groupEnd();

        $rows = $builder->get()->getResultArray();
        if (!$rows) return;

        $now = date('Y-m-d H:i:s');

        foreach ($rows as $r) {
            $update = ['status' => 'DONE'];

            // opsional kolom tambahan
            if ($db->fieldExists('qty_out', 'production_wip')) $update['qty_out'] = $qtyOut;
            if ($db->fieldExists('stock', 'production_wip'))   $update['stock']   = 0;
            if ($db->fieldExists('updated_at', 'production_wip')) $update['updated_at'] = $now;

            $db->table('production_wip')->where('id', (int)$r['id'])->update($update);
        }
    }

    /* =========================
     * INDEX
     * ========================= */
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        $shifts = $this->getMachiningShifts($db);

        foreach ($shifts as &$shift) {
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

            $hourly = $db->table('machining_hourly')
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

        return view('machining/hourly/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'canFinish'    => $canFinish,
            'shift3EndAt'  => $shift3EndDT ? $shift3EndDT->format('Y-m-d H:i:s') : null,
            'finishError'  => $finishError,
        ]);
    }

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        $db->transBegin();
        try {
            $this->saveHourlyRows($db, $items);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();
            return redirect()->back()->with('success', 'Hourly Machining tersimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST /machining/hourly/finish-shift
     * - Simpan hourly dulu
     * - Validasi shift 3 selesai
     * - SUM FG per (machine_id, product_id) dari SEMUA shift MC di tanggal tsb
     * - Update WIP:
     *   (A) inbound (prev -> Machining) jadi DONE
     *   (B) outbound (Machining -> next) upsert qty=FG aktual status WAITING
     *
     * NOTE:
     * - outbound key pakai source_table=daily_schedule_items dan source_id=dsi.id
     */
    public function finishShift()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        // ambil date dari payload
        $date = null;
        foreach ($items as $r) {
            if (!empty($r['date'])) { $date = (string)$r['date']; break; }
        }
        if (!$date) {
            return redirect()->back()->with('error', 'Tanggal tidak ditemukan dari payload');
        }

        // validasi shift 3 selesai
        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, $date);
        if (!$canFinish) {
            $msg = $finishError ?: 'Belum bisa Finish Shift';
            if ($shift3EndDT) $msg .= ' (Shift 3 selesai: '.$shift3EndDT->format('Y-m-d H:i:s').')';
            return redirect()->back()->with('error', $msg);
        }

        $db->transBegin();
        try {
            // 1) simpan hourly dulu
            $this->saveHourlyRows($db, $items);

            $mcProcessId = $this->getProcessIdMachining($db);

            // 2) ambil semua shift id Machining
            $mcShifts = $this->getMachiningShifts($db);
            $mcShiftIds = array_values(array_filter(array_map(fn($s) => (int)$s['id'], $mcShifts)));
            if (!$mcShiftIds) throw new \Exception('Shift Machining (MC) tidak ditemukan');

            // 3) ambil schedule items machining (semua shift di tanggal tsb)
            $scheduleItems = $db->table('daily_schedule_items dsi')
                ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id, ds.shift_id')
                ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->where('ds.schedule_date', $date)
                ->where('ds.section', 'Machining')
                ->where('dsi.target_per_shift >', 0)
                ->get()
                ->getResultArray();

            if (!$scheduleItems) {
                throw new \Exception('Daily schedule Machining belum dibuat untuk tanggal ini.');
            }

            // 4) hitung FG aktual per (machine_id, product_id) dari semua shift MC
            $actualRows = $db->table('machining_hourly')
                ->select('machine_id, product_id, SUM(qty_fg) AS fg_total')
                ->where('production_date', $date)
                ->whereIn('shift_id', $mcShiftIds)
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

            foreach ($scheduleItems as $si) {
                $dsiId     = (int)$si['dsi_id'];
                $machineId = (int)$si['machine_id'];
                $productId = (int)$si['product_id'];

                if ($dsiId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                $fgActual = (int)($actualMap[$machineId.'_'.$productId] ?? 0);
                if ($fgActual <= 0) continue;

                // (A) inbound prev -> machining => DONE
                $prevProcessId = $this->resolvePrevProcessId($db, $productId, $mcProcessId);
                if ($prevProcessId) {
                    $this->markInboundToMachiningDone($db, $date, $productId, $prevProcessId, $mcProcessId, $fgActual);
                }

                // (B) outbound machining -> next => upsert WAITING qty=FG aktual
                $nextProcessId = $this->resolveNextProcessId($db, $productId, $mcProcessId);
                if ($nextProcessId) {
                    $this->upsertWipNextProcess(
                        $db,
                        $date,
                        $productId,
                        $mcProcessId,
                        $nextProcessId,
                        $fgActual,
                        'daily_schedule_items',
                        $dsiId
                    );
                }

                $processed++;
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Finish Shift sukses: inbound Machining ditandai DONE & FG aktual dikirim ke proses berikutnya. (rows: '.$processed.')'
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
