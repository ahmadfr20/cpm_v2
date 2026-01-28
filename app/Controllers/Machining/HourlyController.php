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

    /* =========================================================
     * 1) UPDATE STOCK MACHINING saat hourly disimpan
     *    - stock Machining diambil dari SUM(qty_fg) (qty A) per schedule item (dsi)
     *    - yang di-update adalah row WIP inbound (prev -> Machining) yang source-nya daily_schedule_items (dsi)
     * ========================================================= */
    private function syncMachiningWipStockFromHourly($db, string $date): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->tableExists('machining_hourly')) return;

        $wipDateCol = $this->detectWipDateColumn($db);
        $mcProcessId = $this->getProcessIdMachining($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        if (!$hasStock) return; // kalau tidak ada kolom stock, tidak ada yang bisa diisi

        // Ambil semua schedule item Machining pada tanggal itu
        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Machining')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

        // SUM FG aktual per (machine_id, product_id) dari semua shift MC (satu hari)
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

            // Prev process dari flow (inbound ke Machining)
            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $mcProcessId);
            if (!$prevProcessId) continue;

            // Row inbound schedule: prev -> Machining, source daily_schedule_items (dsi)
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
                // Kalau row inbound belum ada, kita buat minimal supaya stock Machining bisa muncul.
                // qty_in di sini tetap 0 kalau schedule memang belum mengisi qty_in,
                // tapi stock akan mengikuti qtyA.
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
                // Update stock = qtyA (qty actual FG)
                $upd = ['stock' => $qtyA];
                if ($db->fieldExists('updated_at', 'production_wip')) $upd['updated_at'] = $now;
                $db->table('production_wip')->where('id', (int)$exist['id'])->update($upd);
            }
        }
    }

    /* =========================
     * WIP UPSERT (NEXT PROCESS)
     * - OUTBOUND: Machining -> next
     * - qty/qty_in/stock = qtyMove (qty A)
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

        $now = date('Y-m-d H:i:s');

        $payload = $key + [
            'qty'    => $qtyMove,
            'status' => 'WAITING',
        ];

        if ($db->fieldExists('qty_in', 'production_wip'))  $payload['qty_in']  = $qtyMove;
        if ($db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = 0;
        if ($db->fieldExists('stock', 'production_wip'))   $payload['stock']   = $qtyMove;
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            if (($exist['status'] ?? '') === 'DONE') return; // lock kalau sudah DONE
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /* =========================
     * FINISH SHIFT TRANSFER (Machining)
     * - inbound prev->MC: qty_out = qty_in, stock=0, status DONE
     * - outbound MC->next: qty_in/stock = SUM(qty_fg) (qty A)
     * ========================= */
    private function finishMachiningTransferFlow($db, string $date): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        if (!$db->tableExists('machining_hourly')) return 0;

        $wipDateCol = $this->detectWipDateColumn($db);

        $mcProcessId = $this->getProcessIdMachining($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        $now = date('Y-m-d H:i:s');

        // semua schedule items Machining (satu hari)
        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Machining')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return 0;

        // actual FG per machine+product (satu hari)
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

            if ($dsiId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $qtyA = (int)($actualMap[$machineId.'_'.$productId] ?? 0);

            // resolve prev & next
            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $mcProcessId);
            $nextProcessId = $this->resolveNextProcessId($db, $productId, $mcProcessId);

            // A) inbound prev -> MC : DONE, qty_out = qty_in, stock=0
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

                if ($inbound) {
                    $qtyInVal = (int)($inbound['qty_in'] ?? $inbound['qty'] ?? 0);

                    $upd = [
                        'status' => 'DONE',
                    ];
                    if ($hasQtyOut) $upd['qty_out'] = $qtyInVal; // semua qty_in pindah ke qty_out
                    if ($hasStock)  $upd['stock']   = 0;        // stock jadi 0
                    if ($db->fieldExists('updated_at', 'production_wip')) $upd['updated_at'] = $now;

                    $db->table('production_wip')->where('id', (int)$inbound['id'])->update($upd);
                }
            }

            // B) outbound MC -> next : qty_in/stock = qtyA (qty actual) kirim ke proses selanjutnya
            if ($nextProcessId && $qtyA > 0) {
                $this->upsertWipNextProcess(
                    $db,
                    $date,
                    $productId,
                    $mcProcessId,
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

    /* =========================
     * STORE (save hourly biasa)
     * -> setelah save hourly, update stock WIP Machining = qty A (SUM FG)
     * ========================= */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        // ambil tanggal dari payload
        $date = null;
        foreach ($items as $r) {
            if (!empty($r['date'])) { $date = (string)$r['date']; break; }
        }

        $db->transBegin();
        try {
            $this->saveHourlyRows($db, $items);

            if ($date) {
                // qty A (FG) -> stock WIP Machining (inbound prev->MC)
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

    /**
     * POST /machining/hourly/finish-shift
     * - Simpan hourly
     * - Validasi shift 3 selesai
     * - Update stock Machining dari qty A (biar konsisten)
     * - Transfer:
     *   inbound (prev->MC): qty_out=qty_in, stock=0, DONE
     *   outbound (MC->next): qty_in/stock=qtyA (SUM FG) WAITING
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

            // 2) update stock Machining dari qty A (supaya akurat sebelum transfer)
            $this->syncMachiningWipStockFromHourly($db, $date);

            // 3) transfer flow sesuai rules
            $processed = $this->finishMachiningTransferFlow($db, $date);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Finish Shift sukses: qty_in inbound -> qty_out, stock inbound=0, dan qty A dikirim ke proses berikutnya. (rows: '.$processed.')'
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
