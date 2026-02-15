<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftDailyProductionController extends BaseController
{
    /* =====================================================
     * HELPERS
     * ===================================================== */

    private function isAdminSession(): bool
    {
        $role = strtoupper((string)(session()->get('role') ?? ''));
        return $role === 'ADMIN';
    }

    private function throwDbError($db, string $context = 'DB error'): void
    {
        $err = $db->error();
        $msg = $context;
        if (!empty($err['message'])) $msg .= ' | ' . $err['message'];
        throw new \Exception($msg);
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

    private function getProcessIdAssyShaft($db): int
    {
        $id = $this->getProcessIdByCodeOrName($db, 'AS', 'ASSY SHAFT');
        if ($id <= 0) $id = $this->getProcessIdByCodeOrName($db, 'AS', 'Assy Shaft');
        if ($id <= 0) $id = $this->getProcessIdByCodeOrName($db, null, 'SHAFT');
        if ($id <= 0) throw new \Exception('Process "ASSY SHAFT" belum ada di master production_processes');
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

        $startDT = new \DateTime($date . ' ' . $startStr, new \DateTimeZone('Asia/Jakarta'));
        $endDT   = new \DateTime($date . ' ' . $endStr, new \DateTimeZone('Asia/Jakarta'));

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

    /* =====================================================
     * WIP HELPERS (punyamu, dipertahankan)
     * ===================================================== */

    private function normalizeSourceTableValue($db, string $value): string
    {
        if (!$db->fieldExists('source_table', 'production_wip')) return $value;

        try {
            $col  = $db->query("SHOW COLUMNS FROM production_wip LIKE 'source_table'")->getRowArray();
            $type = (string)($col['Type'] ?? '');
            $t    = strtolower($type);

            $isEnum = (strpos($t, 'enum(') === 0);
            $isSet  = (strpos($t, 'set(') === 0);

            if ($isEnum || $isSet) {
                $inside = substr($type, strpos($type, '(') + 1);
                $inside = rtrim($inside, ')');

                preg_match_all("/'((?:\\\\'|[^'])*)'/", $inside, $m);
                $opts = $m[1] ?? [];

                $want = strtolower($value);
                foreach ($opts as $opt) {
                    if (strtolower($opt) === $want) return $opt;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $value;
    }

    private function upsertWipByFullKey($db, array $baseKey, array $data, ?string $sourceTable = null, ?int $sourceId = null): void
    {
        if (!$db->tableExists('production_wip')) return;

        $hasSourceTable = $db->fieldExists('source_table', 'production_wip');
        $hasSourceId    = $db->fieldExists('source_id', 'production_wip');
        $hasCreatedAt   = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt   = $db->fieldExists('updated_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        $key = $baseKey;
        $payload = $data;

        if ($hasUpdatedAt) $payload['updated_at'] = $now;

        if ($hasSourceTable && $sourceTable !== null) {
            $src = $this->normalizeSourceTableValue($db, $sourceTable);
            $key['source_table'] = $src;
            $payload['source_table'] = $src;
        }
        if ($hasSourceId && $sourceId !== null) {
            $key['source_id'] = $sourceId;
            $payload['source_id'] = $sourceId;
        }

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();
        if ($exist) {
            $ok = $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
            if ($ok === false) $this->throwDbError($db, 'Update production_wip gagal');
            return;
        }

        $insert = $key + $payload;
        if ($hasCreatedAt) $insert['created_at'] = $now;

        $ok = $db->table('production_wip')->insert($insert);
        if ($ok === false) $this->throwDbError($db, 'Insert production_wip gagal');
    }

    private function getWipRow($db, array $baseKey, ?string $sourceTable = null, ?int $sourceId = null): ?array
    {
        if (!$db->tableExists('production_wip')) return null;

        $key = $baseKey;
        if ($sourceTable !== null && $db->fieldExists('source_table', 'production_wip')) {
            $key['source_table'] = $this->normalizeSourceTableValue($db, $sourceTable);
        }
        if ($sourceId !== null && $db->fieldExists('source_id', 'production_wip')) {
            $key['source_id'] = $sourceId;
        }

        return $db->table('production_wip')->where($key)->get()->getRowArray();
    }

    private function resolveDsiAndDsId($db, array $row, string $date): array
    {
        $dsiId = (int)($row['dsi_id'] ?? 0);

        if ($dsiId > 0 && $db->tableExists('daily_schedule_items')) {
            $dsi = $db->table('daily_schedule_items')
                ->select('id, daily_schedule_id')
                ->where('id', $dsiId)
                ->get()->getRowArray();
            if ($dsi) return [(int)$dsi['id'], (int)$dsi['daily_schedule_id']];
        }

        if ($db->tableExists('daily_schedule_items') && $db->tableExists('daily_schedules')) {
            $shiftId   = (int)($row['shift_id'] ?? 0);
            $machineId = (int)($row['machine_id'] ?? 0);
            $productId = (int)($row['product_id'] ?? 0);

            if ($shiftId > 0 && $machineId > 0 && $productId > 0) {
                $dsi = $db->table('daily_schedule_items dsi')
                    ->select('dsi.id, dsi.daily_schedule_id')
                    ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                    ->where('ds.schedule_date', $date)
                    ->where('ds.shift_id', $shiftId)
                    ->like('ds.section', 'Assy Shaft')
                    ->where('dsi.machine_id', $machineId)
                    ->where('dsi.product_id', $productId)
                    ->limit(1)
                    ->get()->getRowArray();

                if ($dsi) return [(int)$dsi['id'], (int)$dsi['daily_schedule_id']];
            }
        }

        return [0, 0];
    }

    private function updateInboundWipQtyInAndStock($db, string $date, int $productId, int $prevId, int $deltaFg, ?int $dsiId, ?int $dsId): void
    {
        if ($deltaFg === 0) return;
        if (!$db->tableExists('production_wip')) return;

        $wipDateCol  = $this->detectWipDateColumn($db);
        $asProcessId = $this->getProcessIdAssyShaft($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        if (!$hasQtyIn && !$hasStock) return;

        $hasSource = $db->fieldExists('source_table', 'production_wip') && $db->fieldExists('source_id', 'production_wip');

        $base = [
            $wipDateCol       => $date,
            'product_id'      => $productId,
            'from_process_id' => $prevId,
            'to_process_id'   => $asProcessId,
        ];

        $apply = function(?string $srcTable, ?int $srcId) use ($db, $base, $hasQtyIn, $hasStock, $deltaFg) {
            $row = $this->getWipRow($db, $base, $srcTable, $srcId);

            $qtyIn = $hasQtyIn ? (int)($row['qty_in'] ?? 0) : 0;
            $stock = $hasStock ? (int)($row['stock'] ?? 0) : 0;

            if ($hasQtyIn) $qtyIn = max(0, $qtyIn - $deltaFg);
            if ($hasStock) $stock = max(0, $stock + $deltaFg);

            $data = ['status' => 'SCHEDULED'];
            if ($hasQtyIn) $data['qty_in'] = $qtyIn;
            if ($hasStock) $data['stock']  = $stock;

            $this->upsertWipByFullKey($db, $base, $data, $srcTable, $srcId);
        };

        if ($hasSource && !empty($dsiId)) $apply('daily_schedule_items', (int)$dsiId);
        if ($hasSource && !empty($dsId))  $apply('daily_schedules', (int)$dsId);
        $apply(null, null);
    }

    private function applyHourlyDeltaToWip($db, array $row, string $date, int $deltaFg): void
    {
        if ($deltaFg === 0) return;
        if (!$db->tableExists('production_wip')) return;

        $asProcessId = $this->getProcessIdAssyShaft($db);

        $productId = (int)($row['product_id'] ?? 0);
        if ($productId <= 0) return;

        $pn = $this->resolvePrevNextBySequence($db, $productId, $asProcessId);
        $prevId = (int)($pn['prev'] ?? 0);
        if ($prevId <= 0) return;

        [$dsiId, $dsId] = $this->resolveDsiAndDsId($db, $row, $date);

        $this->updateInboundWipQtyInAndStock($db, $date, $productId, $prevId, $deltaFg, $dsiId ?: null, $dsId ?: null);
    }

    /* =====================================================
     * NG DETAIL HELPERS (BARU)
     * ===================================================== */

    /**
     * Simpan detail NG (hapus dulu per slot-key, insert ulang)
     * lalu update qty_ng di hourly = SUM detail
     */
    private function saveNgDetailsAndUpdateHourlyNg($db, array $items): void
    {
        if (!$db->tableExists('machining_assy_shaft_hourly_ng_details')) return;

        $now = date('Y-m-d H:i:s');

        foreach ($items as $row) {
            if (
                empty($row['date']) ||
                empty($row['shift_id']) ||
                empty($row['machine_id']) ||
                empty($row['product_id']) ||
                empty($row['time_slot_id'])
            ) continue;

            $date      = (string)$row['date'];
            $shiftId   = (int)$row['shift_id'];
            $machineId = (int)$row['machine_id'];
            $productId = (int)$row['product_id'];
            $slotId    = (int)$row['time_slot_id'];

            $details = $row['ng_details'] ?? [];
            if (!is_array($details)) $details = [];

            // delete old details
            $ok = $db->table('machining_assy_shaft_hourly_ng_details')
                ->where([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'time_slot_id'    => $slotId,
                ])->delete();
            if ($ok === false) $this->throwDbError($db, 'Delete NG details gagal');

            // insert new details
            $totalNg = 0;
            foreach ($details as $d) {
                $ngCatId = (int)($d['ng_category_id'] ?? 0);
                $qty     = (int)($d['qty'] ?? 0);
                if ($ngCatId <= 0 || $qty <= 0) continue;

                $totalNg += $qty;

                $ins = [
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'time_slot_id'    => $slotId,
                    'ng_category_id'  => $ngCatId,
                    'qty'             => $qty,
                ];
                if ($db->fieldExists('created_at', 'machining_assy_shaft_hourly_ng_details')) $ins['created_at'] = $now;
                if ($db->fieldExists('updated_at', 'machining_assy_shaft_hourly_ng_details')) $ins['updated_at'] = $now;

                $ok = $db->table('machining_assy_shaft_hourly_ng_details')->insert($ins);
                if ($ok === false) $this->throwDbError($db, 'Insert NG details gagal');
            }

            // update hourly qty_ng = totalNg (auto)
            $upd = ['qty_ng' => $totalNg];
            if ($db->fieldExists('updated_at', 'machining_assy_shaft_hourly')) $upd['updated_at'] = $now;

            $ok = $db->table('machining_assy_shaft_hourly')
                ->where([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'time_slot_id'    => $slotId,
                ])->update($upd);

            if ($ok === false) $this->throwDbError($db, 'Update qty_ng hourly gagal');
        }
    }

    /* =====================================================
     * HOURLY SAVE (diubah sedikit: qty_ng diabaikan, pakai detail)
     * ===================================================== */

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

            $date       = (string)$row['date'];
            $shiftId    = (int)$row['shift_id'];
            $machineId  = (int)$row['machine_id'];
            $productId  = (int)$row['product_id'];
            $timeSlotId = (int)$row['time_slot_id'];

            $newFg = (int)($row['ok'] ?? $row['fg'] ?? 0);

            // NG jangan dipakai dari input manual (akan diupdate oleh detail)
            $oldHourly = $db->table('machining_assy_shaft_hourly')
                ->where([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'time_slot_id'    => $timeSlotId,
                ])->get()->getRowArray();

            $oldFg = (int)($oldHourly['qty_fg'] ?? 0);
            $deltaFg = $newFg - $oldFg;

            $payload = [
                'production_date' => $date,
                'shift_id'        => $shiftId,
                'machine_id'      => $machineId,
                'product_id'      => $productId,
                'time_slot_id'    => $timeSlotId,
                'qty_fg'          => $newFg,
                // qty_ng biarkan nilai lama; nanti saveNgDetailsAndUpdateHourlyNg() yang set
                'qty_ng'          => (int)($oldHourly['qty_ng'] ?? 0),
            ];

            if ($db->fieldExists('created_at', 'machining_assy_shaft_hourly') && empty($oldHourly)) $payload['created_at'] = $now;
            if ($db->fieldExists('updated_at', 'machining_assy_shaft_hourly')) $payload['updated_at'] = $now;

            $ok = $db->table('machining_assy_shaft_hourly')->replace($payload);
            if ($ok === false) $this->throwDbError($db, 'Replace machining_assy_shaft_hourly gagal');

            // WIP delta update (tetap)
            $this->applyHourlyDeltaToWip($db, $row, $date, $deltaFg);
        }
    }

    /* =====================================================
     * FINISH SHIFT (punyamu, dipertahankan)
     * ===================================================== */

    private function finishAssyShaftTransferFromWip($db, string $date): array
    {
        if (!$db->tableExists('production_wip')) return ['rows' => 0, 'sent' => 0];

        $wipDateCol  = $this->detectWipDateColumn($db);
        $asProcessId = $this->getProcessIdAssyShaft($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasSource = $db->fieldExists('source_table', 'production_wip') && $db->fieldExists('source_id', 'production_wip');

        if (!$hasQtyOut) throw new \Exception('Kolom qty_out tidak ditemukan di production_wip.');
        if (!$hasStock)  throw new \Exception('Kolom stock tidak ditemukan di production_wip.');

        $inRows = $db->table('production_wip')
            ->where($wipDateCol, $date)
            ->where('to_process_id', $asProcessId)
            ->where('stock >', 0)
            ->get()->getResultArray();

        if (!$inRows) return ['rows' => 0, 'sent' => 0];

        $rows = 0;
        $sentTotal = 0;

        foreach ($inRows as $r) {
            $id        = (int)($r['id'] ?? 0);
            $productId = (int)($r['product_id'] ?? 0);
            $prevId    = (int)($r['from_process_id'] ?? 0);
            $stock     = (int)($r['stock'] ?? 0);

            if ($id <= 0 || $productId <= 0 || $prevId <= 0 || $stock <= 0) continue;

            $pn = $this->resolvePrevNextBySequence($db, $productId, $asProcessId);
            $nextId = (int)($pn['next'] ?? 0);
            if ($nextId <= 0) continue;

            $send = $stock;

            $db->table('production_wip')
                ->where('id', $id)
                ->set('qty_out', "qty_out + {$send}", false)
                ->set('stock', 0)
                ->set('status', 'DONE')
                ->update();
            if ($db->affectedRows() === -1) $this->throwDbError($db, 'Update inbound WIP gagal');

            if ($hasQtyIn) {
                $db->table('production_wip')
                    ->where('id', $id)
                    ->set('qty_in', "GREATEST(qty_in - {$send}, 0)", false)
                    ->update();
            }

            $baseInbound = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevId,
                'to_process_id'   => $asProcessId,
            ];

            $db->table('production_wip')
                ->where($baseInbound)
                ->where('id !=', $id)
                ->set('qty_out', "qty_out + {$send}", false)
                ->set('stock', 0)
                ->set('status', 'DONE')
                ->update();

            if ($hasQtyIn) {
                $db->table('production_wip')
                    ->where($baseInbound)
                    ->where('id !=', $id)
                    ->set('qty_in', "GREATEST(qty_in - {$send}, 0)", false)
                    ->update();
            }

            $baseOut = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $asProcessId,
                'to_process_id'   => $nextId,
            ];

            $srcTable = $hasSource ? (string)($r['source_table'] ?? '') : null;
            $srcId    = $hasSource ? (int)($r['source_id'] ?? 0) : null;
            if ($srcTable !== null && $srcTable !== '') $srcTable = $this->normalizeSourceTableValue($db, $srcTable);

            $outRow = $this->getWipRow($db, $baseOut, ($srcTable ?: null), ($srcId ?: null));
            $outQty    = (int)($outRow['qty'] ?? 0);
            $outQtyIn  = $hasQtyIn ? (int)($outRow['qty_in'] ?? 0) : 0;
            $outQtyOut = (int)($outRow['qty_out'] ?? 0);

            $outData = [
                'status'  => 'WAITING',
                'qty'     => $outQty + $send,
                'stock'   => 0,
                'qty_out' => $outQtyOut + $send,
            ];
            if ($hasQtyIn) $outData['qty_in'] = $outQtyIn + $send;

            $this->upsertWipByFullKey($db, $baseOut, $outData, ($srcTable ?: null), ($srcId ?: null));

            $outRow2 = $this->getWipRow($db, $baseOut, null, null);
            $outQty2    = (int)($outRow2['qty'] ?? 0);
            $outQtyIn2  = $hasQtyIn ? (int)($outRow2['qty_in'] ?? 0) : 0;
            $outQtyOut2 = (int)($outRow2['qty_out'] ?? 0);

            $outData2 = [
                'status'  => 'WAITING',
                'qty'     => $outQty2 + $send,
                'stock'   => 0,
                'qty_out' => $outQtyOut2 + $send,
            ];
            if ($hasQtyIn) $outData2['qty_in'] = $outQtyIn2 + $send;

            $this->upsertWipByFullKey($db, $baseOut, $outData2, null, null);

            $rows++;
            $sentTotal += $send;
        }

        return ['rows' => $rows, 'sent' => $sentTotal];
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

        // ✅ NG Categories
        $ngCategories = [];
        if ($db->tableExists('ng_categories')) {
            $ngCategories = $db->table('ng_categories')
                ->select('id, ng_code, ng_name')
                ->where('is_active', 1)
                ->orderBy('ng_code', 'ASC')
                ->get()->getResultArray();
        }

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
                ->like('ds.section', 'Assy Shaft')
                ->where('dsi.target_per_shift >', 0)
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            $hourly = $db->table('machining_assy_shaft_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()
                ->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map'][(int)$h['machine_id']][(int)$h['product_id']][(int)$h['time_slot_id']] = $h;
            }

            // ✅ NG detail map
            $shift['ng_detail_map'] = [];
            if ($db->tableExists('machining_assy_shaft_hourly_ng_details')) {
                $details = $db->table('machining_assy_shaft_hourly_ng_details')
                    ->where('production_date', $date)
                    ->where('shift_id', (int)$shift['id'])
                    ->get()->getResultArray();

                foreach ($details as $d) {
                    $shift['ng_detail_map'][(int)$d['machine_id']][(int)$d['product_id']][(int)$d['time_slot_id']][] = $d;
                }
            }
        }
        unset($shift);

        [$canFinish, $shift3EndDT, $finishError] = $this->canFinishShift($db, $date);

        return view('machining/assy_shaft/daily_production/index', [
            'date'          => $date,
            'operator'      => $operator,
            'shifts'        => $shifts,
            'ngCategories'  => $ngCategories,
            'canFinish'     => $canFinish,
            'isAdmin'       => $this->isAdminSession(),
            'shift3EndAt'   => $shift3EndDT ? $shift3EndDT->format('Y-m-d H:i:s') : null,
            'finishError'   => $finishError,
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
            $this->saveNgDetailsAndUpdateHourlyNg($db, $items);

            if ($db->transStatus() === false) $this->throwDbError($db, 'Transaksi store hourly gagal');
            $db->transCommit();

            return redirect()->back()->with('success', 'Hourly Assy Shaft tersimpan + NG detail tersimpan (qty_ng auto).');
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
            // simpan dulu jika ada input belum tersimpan
            if (!empty($items)) {
                $this->saveHourlyRows($db, $items);
                $this->saveNgDetailsAndUpdateHourlyNg($db, $items);
            }

            $res = $this->finishAssyShaftTransferFromWip($db, (string)$date);

            if ($db->transStatus() === false) $this->throwDbError($db, 'Transaksi finish shift gagal');
            $db->transCommit();

            return redirect()->back()->with(
                'success',
                'Finish Shift Assy Shaft sukses. Rows terkirim: '.$res['rows'].' | Total qty terkirim: '.$res['sent']
            );
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
