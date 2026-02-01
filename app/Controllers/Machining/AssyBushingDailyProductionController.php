<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyBushingDailyProductionController extends BaseController
{
    private function isAdminSession(): bool
    {
        $role = strtoupper((string)(session()->get('role') ?? ''));
        return $role === 'ADMIN';
    }

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

    private function resolveNextProcessId($db, int $productId, int $fromProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;

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
        if (!$db->tableExists('product_process_flows')) return null;

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

            if ($db->fieldExists('created_at', 'machining_assy_bushing_hourly')) {
                $payload['created_at'] = $now;
            }
            if ($db->fieldExists('updated_at', 'machining_assy_bushing_hourly')) {
                $payload['updated_at'] = $now;
            }

            $db->table('machining_assy_bushing_hourly')->replace($payload);
        }
    }

    /**
     * ✅ helper upsert production_wip
     * key wajib mengandung: dateCol, product_id, from_process_id, to_process_id, source_table, source_id
     */
    private function upsertWip($db, array $key, array $data): void
    {
        if (!$db->tableExists('production_wip')) return;

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();
        $now   = date('Y-m-d H:i:s');

        $payload = $key + $data;

        if ($exist) {
            // jangan timpa DONE kecuali memang diminta di data
            if (isset($data['status']) && strtoupper((string)($exist['status'] ?? '')) === 'DONE' && strtoupper((string)$data['status']) !== 'DONE') {
                return;
            }
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($db->fieldExists('created_at', 'production_wip') && !isset($payload['created_at'])) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /**
     * ✅ SYNC STOCK AB (realtime):
     * - update 2 row: source_table daily_schedule_items + daily_schedules
     * - qty_in berkurang sesuai produksi (plan - stock)
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

        if (!$hasStock) return;

        // ✅ ambil schedule items + ds_id
        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, ds.id AS ds_id, dsi.machine_id, dsi.product_id, dsi.target_per_shift, ds.shift_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Bushing')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

        // SUM qty_fg
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

            $qtyA = (int)($actualMap[$shiftId.'_'.$machineId.'_'.$productId] ?? 0);
            $remaining = max(0, $qtyPlan - $qtyA);

            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $abProcessId);
            if (!$prevProcessId) continue;

            // ✅ row ITEM
            $keyItem = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $abProcessId,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $dsiId,
            ];

            $data = [
                'qty'    => $qtyPlan,
                'status' => 'WAITING',
                'stock'  => $qtyA,
            ];
            if ($hasQtyIn)  $data['qty_in']  = $remaining;
            if ($hasQtyOut) $data['qty_out'] = 0;

            $this->upsertWip($db, $keyItem, $data);

            // ✅ row HEADER (yang sering dipakai modul downstream)
            $keyHdr = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $abProcessId,
                'source_table'    => 'daily_schedules',
                'source_id'       => $dsId,
            ];
            $this->upsertWip($db, $keyHdr, $data);
        }
    }

    /**
     * ✅ UPSERT NEXT WIP:
     * - qty_in berisi qtyMove
     * - qty_out juga berisi qtyMove (permintaan kamu)
     * - dibuat 2 row: item + header
     */
    private function upsertWipNextBoth($db, string $date, int $productId, int $fromProcessId, int $toProcessId, int $qtyMove, int $dsiId, int $dsId): void
    {
        $wipDateCol = $this->detectWipDateColumn($db);

        $data = [
            'qty'    => $qtyMove,
            'status' => 'WAITING',
        ];
        if ($db->fieldExists('qty_in', 'production_wip'))  $data['qty_in']  = $qtyMove;
        if ($db->fieldExists('qty_out', 'production_wip')) $data['qty_out'] = $qtyMove;
        if ($db->fieldExists('stock', 'production_wip'))   $data['stock']   = 0;

        $keyItem = [
            $wipDateCol       => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromProcessId,
            'to_process_id'   => $toProcessId,
            'source_table'    => 'daily_schedule_items',
            'source_id'       => $dsiId,
        ];
        $this->upsertWip($db, $keyItem, $data);

        $keyHdr = [
            $wipDateCol       => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromProcessId,
            'to_process_id'   => $toProcessId,
            'source_table'    => 'daily_schedules',
            'source_id'       => $dsId,
        ];
        $this->upsertWip($db, $keyHdr, $data);
    }

    /**
     * ✅ FINISH SHIFT:
     * - inbound prev->AB: qty_in berkurang sesuai stock hasil input, qty_out=transferQty, stock=0, DONE
     * - next AB->NEXT: qty_in=qty_out=transferQty, WAITING
     * - semua dibuat 2 row (items + schedules) supaya flow downstream kebaca
     */
    private function finishAssyBushingTransferFlow($db, string $date, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        if (!$db->tableExists('machining_assy_bushing_hourly')) return 0;

        $wipDateCol  = $this->detectWipDateColumn($db);
        $abProcessId = $this->getProcessIdAssyBushing($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        // ✅ ambil items + ds_id
        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, ds.id AS ds_id, dsi.machine_id, dsi.product_id, dsi.target_per_shift, ds.shift_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Bushing')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) throw new \Exception('Daily schedule Assy Bushing kosong untuk tanggal '.$date);

        // SUM qty_fg
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

        $processed = 0;

        foreach ($items as $si) {
            $dsiId     = (int)$si['dsi_id'];
            $dsId      = (int)$si['ds_id'];
            $shiftId   = (int)$si['shift_id'];
            $machineId = (int)$si['machine_id'];
            $productId = (int)$si['product_id'];
            $qtyPlan   = (int)($si['target_per_shift'] ?? 0);

            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $abProcessId);
            if (!$prevProcessId) throw new \Exception('Prev process tidak ditemukan untuk product_id='.$productId.' -> Assy Bushing');

            $nextProcessId = $this->resolveNextProcessId($db, $productId, $abProcessId);
            if (!$nextProcessId) throw new \Exception('Next process tidak ditemukan untuk product_id='.$productId.' setelah Assy Bushing');

            // transferQty = stock inbound (hasil sync) fallback ke SUM hourly
            $qtyA = (int)($actualMap[$shiftId.'_'.$machineId.'_'.$productId] ?? 0);

            // ambil inbound item utk baca stock
            $keyInboundItem = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $abProcessId,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $dsiId,
            ];
            $inItem = $db->table('production_wip')->where($keyInboundItem)->get()->getRowArray();
            $stockNow = ($hasStock && $inItem) ? (int)($inItem['stock'] ?? 0) : 0;

            $transferQty = max($stockNow, $qtyA);

            // ✅ qty_in berkurang sesuai stock/hasil input
            $qtyInNew = max(0, $qtyPlan - $transferQty);

            // inbound DONE untuk ITEM + HEADER
            $doneData = [
                'qty'    => $qtyPlan,
                'status' => 'DONE',
            ];
            if ($hasQtyOut) $doneData['qty_out'] = $transferQty;
            if ($hasQtyIn)  $doneData['qty_in']  = $qtyInNew;
            if ($hasStock)  $doneData['stock']   = 0;

            $this->upsertWip($db, $keyInboundItem, $doneData);

            $keyInboundHdr = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $abProcessId,
                'source_table'    => 'daily_schedules',
                'source_id'       => $dsId,
            ];
            $this->upsertWip($db, $keyInboundHdr, $doneData);

            // ✅ kirim ke next (qty_in & qty_out terisi)
            if ($transferQty > 0) {
                $this->upsertWipNextBoth(
                    $db,
                    $date,
                    $productId,
                    $abProcessId,
                    (int)$nextProcessId,
                    $transferQty,
                    $dsiId,
                    $dsId
                );
            }

            $processed++;
        }

        return $processed;
    }

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

    public function finishShift()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        // ✅ fallback date dari GET/POST kalau items kosong (view bisa saja tidak punya item)
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

        $forceAdmin = $this->isAdminSession();

        $db->transBegin();
        try {
            // simpan hourly bila ada
            if (!empty($items)) $this->saveHourlyRows($db, $items);

            // sync stock (buat 2 row)
            $this->syncAssyBushingWipStockFromHourly($db, (string)$date);

            // transfer (update inbound item+header DONE, create next item+header)
            $processed = $this->finishAssyBushingTransferFlow($db, (string)$date, $forceAdmin);

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
