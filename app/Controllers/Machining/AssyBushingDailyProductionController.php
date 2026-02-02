<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyBushingDailyProductionController extends BaseController
{
    /* =====================================================
     * HELPERS
     * ===================================================== */

    private function isAdminSession(): bool
    {
        $role = strtoupper((string)(session()->get('role') ?? ''));
        return $role === 'ADMIN';
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

    private function getProcessIdAssyBushing($db): int
    {
        $id = $this->getProcessIdByCodeOrName($db, 'AB', 'Assy Bushing');
        if ($id <= 0) $id = $this->getProcessIdByCodeOrName($db, null, 'BUSHING');
        if ($id <= 0) $id = $this->getProcessIdByCodeOrName($db, null, 'Bushing');
        if ($id <= 0) throw new \Exception('Process "Assy Bushing" belum ada di master production_processes');
        return $id;
    }

    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

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
        if ($this->isAdminSession()) return [true, null, null];

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

    /**
     * Prev/Next process berdasarkan product_process_flows.sequence
     */
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

            if ($db->fieldExists('created_at', 'machining_assy_bushing_hourly')) $payload['created_at'] = $now;
            if ($db->fieldExists('updated_at', 'machining_assy_bushing_hourly')) $payload['updated_at'] = $now;

            $db->table('machining_assy_bushing_hourly')->replace($payload);
        }
    }

    /**
     * Upsert aman untuk production_wip.
     * Bisa handle kasus:
     * - ada kolom source_table/source_id (bisa insert item+header)
     * - tidak ada kolom source_table/source_id (fallback update row baseKey)
     */
    private function upsertWipSafe($db, array $baseKey, array $data, ?string $sourceTable = null, ?int $sourceId = null): void
    {
        if (!$db->tableExists('production_wip')) return;

        $hasSourceTable = $db->fieldExists('source_table', 'production_wip');
        $hasSourceId    = $db->fieldExists('source_id', 'production_wip');
        $hasCreatedAt   = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt   = $db->fieldExists('updated_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        $fullKey = $baseKey;
        if ($hasSourceTable && $sourceTable !== null) $fullKey['source_table'] = $sourceTable;
        if ($hasSourceId && $sourceId !== null)       $fullKey['source_id']    = $sourceId;

        $payload = $data;
        if ($hasUpdatedAt) $payload['updated_at'] = $now;
        if ($hasSourceTable && $sourceTable !== null) $payload['source_table'] = $sourceTable;
        if ($hasSourceId && $sourceId !== null)       $payload['source_id']    = $sourceId;

        // 1) UPDATE by fullKey
        $exist = $db->table('production_wip')->where($fullKey)->get()->getRowArray();
        if ($exist) {
            // guard DONE
            if (
                isset($payload['status']) &&
                strtoupper((string)($exist['status'] ?? '')) === 'DONE' &&
                strtoupper((string)$payload['status']) !== 'DONE'
            ) return;

            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
            return;
        }

        // 2) INSERT fullKey
        $insert = $fullKey + $payload;
        if ($hasCreatedAt) $insert['created_at'] = $now;

        try {
            $db->table('production_wip')->insert($insert);
            return;
        } catch (\Throwable $e) {
            // 3) fallback: update row baseKey (kalau unique key cuma baseKey)
            $exist2 = $db->table('production_wip')->where($baseKey)->get()->getRowArray();
            if (!$exist2) return;

            if (
                isset($payload['status']) &&
                strtoupper((string)($exist2['status'] ?? '')) === 'DONE' &&
                strtoupper((string)$payload['status']) !== 'DONE'
            ) return;

            $db->table('production_wip')->where('id', (int)$exist2['id'])->update($payload);
        }
    }

    /**
     * Ambil WIP row: prefer item (source_table/items), fallback baseKey.
     */
    private function getWipRowPreferItem($db, array $baseKey, ?int $dsiId = null): ?array
    {
        if (!$db->tableExists('production_wip')) return null;

        $hasSourceTable = $db->fieldExists('source_table', 'production_wip');
        $hasSourceId    = $db->fieldExists('source_id', 'production_wip');

        if ($dsiId && $hasSourceTable && $hasSourceId) {
            $itemKey = $baseKey + [
                'source_table' => 'daily_schedule_items',
                'source_id'    => $dsiId
            ];
            $r = $db->table('production_wip')->where($itemKey)->get()->getRowArray();
            if ($r) return $r;
        }

        // fallback baseKey (bisa return row header atau single row jika unique tanpa source)
        return $db->table('production_wip')->where($baseKey)->get()->getRowArray();
    }

    /* =====================================================
     * CORE LOGIC
     * ===================================================== */

    /**
     * SYNC inbound WIP (prev->AB) berdasarkan hourly:
     * - produced_total = SUM hourly FG (cap <= plan)
     * - qty_out existing dipakai untuk hitung stock (yang belum terkirim)
     * - stock = produced_total - qty_out_existing
     * - qty_in = plan - qty_out_existing
     * - status tidak diubah (kalau existing SCHEDULED ya tetap)
     */
    private function syncAssyBushingWipStockFromHourly($db, string $date): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->tableExists('machining_assy_bushing_hourly')) return;

        $wipDateCol  = $this->detectWipDateColumn($db);
        $abProcessId = $this->getProcessIdAssyBushing($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        // schedule items
        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, ds.id AS ds_id, dsi.machine_id, dsi.product_id, dsi.target_per_shift, ds.shift_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Bushing')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

        // SUM qty_fg per (shift,machine,product)
        $actualRows = $db->table('machining_assy_bushing_hourly')
            ->select('shift_id, machine_id, product_id, SUM(qty_fg) AS fg_total')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()
            ->getResultArray();

        $actualMap = [];
        foreach ($actualRows as $a) {
            $actualMap[(int)$a['shift_id'].'_'.(int)$a['machine_id'].'_'.(int)$a['product_id']] = (int)($a['fg_total'] ?? 0);
        }

        foreach ($items as $si) {
            $dsiId     = (int)$si['dsi_id'];
            $dsId      = (int)$si['ds_id'];
            $shiftId   = (int)$si['shift_id'];
            $machineId = (int)$si['machine_id'];
            $productId = (int)$si['product_id'];
            $qtyPlan   = (int)($si['target_per_shift'] ?? 0);

            $produced = (int)($actualMap[$shiftId.'_'.$machineId.'_'.$productId] ?? 0);
            if ($produced > $qtyPlan) $produced = $qtyPlan;

            // prev process pakai sequence
            $pn = $this->resolvePrevNextBySequence($db, $productId, $abProcessId);
            $prevProcessId = $pn['prev'];
            if (!$prevProcessId) continue;

            $baseInbound = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => (int)$prevProcessId,
                'to_process_id'   => $abProcessId,
            ];

            $exist = $this->getWipRowPreferItem($db, $baseInbound, $dsiId);

            $oldQtyOut = ($hasQtyOut && $exist) ? (int)($exist['qty_out'] ?? 0) : 0;
            $oldQtyOut = max(0, min($oldQtyOut, $qtyPlan));

            $stockRemain = $hasStock ? max(0, $produced - $oldQtyOut) : 0;
            $qtyInRemain = $hasQtyIn ? max(0, $qtyPlan - $oldQtyOut) : 0;

            $keepStatus = $exist ? (string)($exist['status'] ?? 'SCHEDULED') : 'SCHEDULED';

            $data = [
                'qty'    => $qtyPlan,
                'status' => $keepStatus,
            ];
            if ($hasStock)  $data['stock']   = $stockRemain;
            if ($hasQtyIn)  $data['qty_in']  = $qtyInRemain;
            if ($hasQtyOut) $data['qty_out'] = $oldQtyOut;

            // item + header
            $this->upsertWipSafe($db, $baseInbound, $data, 'daily_schedule_items', $dsiId);
            $this->upsertWipSafe($db, $baseInbound, $data, 'daily_schedules', $dsId);
        }
    }

    /**
     * FINISH SHIFT:
     * - transfer = stock inbound (prev->AB) yang belum terkirim
     * - inbound prev->AB:
     *    qty_out += transfer
     *    qty_in  = plan - qty_out
     *    stock   = 0
     *    status DONE jika qty_in==0 else WAITING
     * - next AB->NEXT:
     *    qty_in  += transfer
     *    qty_out += transfer
     *    stock   += transfer   (agar proses next "melihat" kiriman)
     */
    private function finishAssyBushingTransferFlow($db, string $date): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        if (!$db->tableExists('machining_assy_bushing_hourly')) return 0;

        $wipDateCol  = $this->detectWipDateColumn($db);
        $abProcessId = $this->getProcessIdAssyBushing($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, ds.id AS ds_id, dsi.machine_id, dsi.product_id, dsi.target_per_shift, ds.shift_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Bushing')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) throw new \Exception('Daily schedule Assy Bushing kosong untuk tanggal '.$date);

        $processed = 0;

        foreach ($items as $si) {
            $dsiId     = (int)$si['dsi_id'];
            $dsId      = (int)$si['ds_id'];
            $productId = (int)$si['product_id'];
            $qtyPlan   = (int)($si['target_per_shift'] ?? 0);

            $pn = $this->resolvePrevNextBySequence($db, $productId, $abProcessId);
            $prevProcessId = $pn['prev'];
            $nextProcessId = $pn['next'];

            if (!$prevProcessId) throw new \Exception("Prev process tidak ditemukan (product_id={$productId})");
            if (!$nextProcessId) throw new \Exception("Next process tidak ditemukan (product_id={$productId})");

            // =========================
            // A) INBOUND prev -> AB
            // =========================
            $baseInbound = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => (int)$prevProcessId,
                'to_process_id'   => $abProcessId,
            ];

            $inRow = $this->getWipRowPreferItem($db, $baseInbound, $dsiId);

            $oldQtyOut = ($hasQtyOut && $inRow) ? (int)($inRow['qty_out'] ?? 0) : 0;
            $oldQtyOut = max(0, min($oldQtyOut, $qtyPlan));

            $oldStock = ($hasStock && $inRow) ? (int)($inRow['stock'] ?? 0) : 0;
            $oldStock = max(0, $oldStock);

            // transfer = stock yang belum terkirim
            $transfer = min($oldStock, max(0, $qtyPlan - $oldQtyOut));
            if ($transfer <= 0) {
                $processed++;
                continue;
            }

            $newQtyOut = $oldQtyOut + $transfer;
            $newQtyOut = min($newQtyOut, $qtyPlan);

            $newQtyIn = $hasQtyIn ? max(0, $qtyPlan - $newQtyOut) : 0;

            $inStatus = ($hasQtyIn && $newQtyIn > 0) ? 'WAITING' : 'DONE';

            $inData = [
                'qty'    => $qtyPlan,
                'status' => $inStatus,
            ];
            if ($hasQtyOut) $inData['qty_out'] = $newQtyOut;
            if ($hasQtyIn)  $inData['qty_in']  = $newQtyIn;
            if ($hasStock)  $inData['stock']   = 0;

            // update inbound item + header
            $this->upsertWipSafe($db, $baseInbound, $inData, 'daily_schedule_items', $dsiId);
            $this->upsertWipSafe($db, $baseInbound, $inData, 'daily_schedules', $dsId);

            // =========================
            // B) NEXT AB -> NEXT
            // =========================
            $baseNext = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $abProcessId,
                'to_process_id'   => (int)$nextProcessId,
            ];

            $nxRow = $this->getWipRowPreferItem($db, $baseNext, $dsiId);

            $nxQty    = (int)($nxRow['qty'] ?? 0) + $transfer;

            $nxQtyIn  = $hasQtyIn  ? (int)($nxRow['qty_in'] ?? 0) + $transfer : 0;
            $nxQtyOut = $hasQtyOut ? (int)($nxRow['qty_out'] ?? 0) + $transfer : 0;
            $nxStock  = $hasStock  ? (int)($nxRow['stock'] ?? 0) + $transfer : 0;

            $nxData = [
                'qty'    => $nxQty,
                'status' => 'WAITING',
            ];
            if ($hasQtyIn)  $nxData['qty_in']  = $nxQtyIn;
            if ($hasQtyOut) $nxData['qty_out'] = $nxQtyOut;
            if ($hasStock)  $nxData['stock']   = $nxStock; // ✅ next bisa lihat kiriman

            $this->upsertWipSafe($db, $baseNext, $nxData, 'daily_schedule_items', $dsiId);
            $this->upsertWipSafe($db, $baseNext, $nxData, 'daily_schedules', $dsId);

            $processed++;
        }

        return $processed;
    }

    /* =====================================================
     * CONTROLLER ACTIONS
     * ===================================================== */

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
                ->where('ds.section', 'Assy Bushing')
                ->where('dsi.target_per_shift >', 0)
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

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

        return view('machining/assy_bushing/daily_production/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'canFinish'    => $canFinish,
            'isAdmin'      => $this->isAdminSession(),
            'shift3EndAt'  => $shift3EndDT ? $shift3EndDT->format('Y-m-d H:i:s') : null,
            'finishError'  => $finishError,
        ]);
    }

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        $date = $this->request->getPost('date') ?? null;
        if (!$date) {
            foreach ($items as $r) {
                if (!empty($r['date'])) { $date = (string)$r['date']; break; }
            }
        }
        if (!$date) return redirect()->back()->with('error', 'Tanggal tidak ditemukan dari payload');

        $db->transBegin();
        try {
            $this->saveHourlyRows($db, $items);
            $this->syncAssyBushingWipStockFromHourly($db, (string)$date);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with('success', 'Hourly Assy Bushing tersimpan + Stock WIP Assy Bushing ter-update');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function finishShift()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        $date = $this->request->getPost('date') ?? $this->request->getGet('date') ?? null;
        if (!$date) {
            foreach ($items as $r) {
                if (!empty($r['date'])) { $date = (string)$r['date']; break; }
            }
        }
        if (!$date) return redirect()->back()->with('error', 'Tanggal tidak ditemukan dari payload');

        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, (string)$date);
        if (!$canFinish) {
            $msg = $finishError ?: 'Belum bisa Finish Shift';
            if ($shift3EndDT) $msg .= ' (Shift 3 selesai: '.$shift3EndDT->format('Y-m-d H:i:s').')';
            return redirect()->back()->with('error', $msg);
        }

        $db->transBegin();
        try {
            // kalau ada input shift3 belum disimpan, simpan dulu
            if (!empty($items)) $this->saveHourlyRows($db, $items);

            // sync dulu: bentuk stock inbound yang benar (produced - qty_out)
            $this->syncAssyBushingWipStockFromHourly($db, (string)$date);

            // finish + transfer ke next flow
            $processed = $this->finishAssyBushingTransferFlow($db, (string)$date);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Finish Shift Assy Bushing sukses. Transfer ke proses berikutnya selesai. (rows: '.$processed.')'
            );
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
