<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftShiftProductionController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // process Assy shaft
        $assyProcess = $this->getProcessAssyShaft($db);
        $assyProcessId = $assyProcess['id'] ?? null;

        // shifts MC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // ng categories (master) - kalau table ada
        $ngCategories = [];
        if ($db->tableExists('ng_categories')) {
            $ngCategories = $db->table('ng_categories')
                ->select('id, ng_code, ng_name')
                ->orderBy('ng_code', 'ASC')
                ->get()->getResultArray();
        }

        // Map time slots per shift (untuk edit window)
        $shiftSlots = [];
        foreach ($shifts as $s) {
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $s['id'])
                ->orderBy('ts.time_start', 'ASC')
                ->get()->getResultArray();

            $shiftSlots[$s['id']] = $slots;
        }

        // Schedule Assy shaft (target per shift)
        $scheduleRows = $db->table('daily_schedule_items dsi')
            ->select('
                ds.shift_id,
                dsi.id as dsi_id,
                dsi.machine_id,
                dsi.product_id,
                dsi.target_per_shift,
                m.machine_code,
                m.line_position,
                p.part_no,
                p.part_name
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->join('products p', 'p.id = dsi.product_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Shaft')
            ->where('dsi.target_per_shift >', 0)
            ->orderBy('ds.shift_id', 'ASC')
            ->orderBy('m.line_position', 'ASC')
            ->get()->getResultArray();

        // Hourly totals (fg/ng) per shift+machine+product
        $hourlyTotals = $db->table('machining_assy_shaft_hourly')
            ->select('
                shift_id, machine_id, product_id,
                SUM(qty_fg) fg,
                SUM(qty_ng) ng
            ')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()->getResultArray();

        $hourlyMap = [];
        foreach ($hourlyTotals as $h) {
            $key = $h['shift_id'].'_'.$h['machine_id'].'_'.$h['product_id'];
            $hourlyMap[$key] = $h;
        }

        // WIP inbound untuk Assy shaft: (prev -> AB)
        $wipMap = [];
        if ($assyProcessId && $db->tableExists('production_wip')) {
            $selectCols = ['pw.product_id','pw.from_process_id','pw.to_process_id','pw.status','pw.qty'];
            if ($db->fieldExists('qty_in', 'production_wip'))  $selectCols[] = 'pw.qty_in';
            if ($db->fieldExists('qty_out', 'production_wip')) $selectCols[] = 'pw.qty_out';
            if ($db->fieldExists('stock', 'production_wip'))   $selectCols[] = 'pw.stock';

            // pakai detect date col biar aman
            $wipDateCol = $this->detectWipDateColumn($db);

            $wips = $db->table('production_wip pw')
                ->select(implode(',', $selectCols))
                ->where("pw.$wipDateCol", $date)
                ->where('pw.to_process_id', $assyProcessId)
                ->get()->getResultArray();

            foreach ($wips as $w) {
                $wipMap[(int)$w['product_id']] = $w;
            }
        }

        // Process name map (untuk next process)
        $processNameMap = [];
        $processes = $db->table('production_processes')->select('id, process_name')->get()->getResultArray();
        foreach ($processes as $pp) $processNameMap[$pp['id']] = $pp['process_name'];

        // Build shifts output
        $viewShifts = [];
        $dailyTarget = 0; $dailyFG = 0; $dailyNG = 0; $dailyDT = 0;

        foreach ($shifts as $s) {
            $shiftId = (int)$s['id'];

            $slots = $shiftSlots[$shiftId] ?? [];
            $startTime = $slots[0]['time_start'] ?? null;
            $endTime   = $slots ? end($slots)['time_end'] : null;

            $editDeadline = $this->calcEditDeadline($date, $startTime, $endTime, 60);
            $canEdit = $editDeadline ? (time() <= strtotime($editDeadline)) : false;

            $items = array_values(array_filter($scheduleRows, fn($r) => (int)$r['shift_id'] === $shiftId));

            $mappedItems = [];
            $no = 1;

            $totalTarget = 0; $totalFG = 0; $totalNG = 0; $totalDT = 0;

            foreach ($items as $r) {
                $k = $shiftId.'_'.$r['machine_id'].'_'.$r['product_id'];
                $h = $hourlyMap[$k] ?? ['fg'=>0,'ng'=>0];

                $totalTarget += (int)$r['target_per_shift'];
                $totalFG     += (int)$h['fg'];
                $totalNG     += (int)$h['ng'];
                $totalDT     += 0;

                $dailyTarget += (int)$r['target_per_shift'];
                $dailyFG     += (int)$h['fg'];
                $dailyNG     += (int)$h['ng'];
                $dailyDT     += 0;

                $nextProcessId = $this->getNextProcessId($db, (int)$r['product_id'], $assyProcessId);
                $nextProcessName = $nextProcessId ? ($processNameMap[$nextProcessId] ?? '-') : '-';

                $wip = $wipMap[(int)$r['product_id']] ?? null;
                $wipStatus = $wip['status'] ?? 'WAITING';

                $wipQty = 0;
                if ($wip) {
                    if (array_key_exists('qty_in', $wip)) $wipQty = (int)($wip['qty_in'] ?? 0);
                    else $wipQty = (int)($wip['qty'] ?? 0);
                }

                $mappedItems[] = [
                    'no' => $no++,
                    'shift_id' => $shiftId,
                    'machine_id' => (int)$r['machine_id'],
                    'product_id' => (int)$r['product_id'],
                    'line_position' => $r['line_position'],
                    'machine_code' => $r['machine_code'],
                    'part_no' => $r['part_no'],
                    'part_name' => $r['part_name'],
                    'target' => (int)$r['target_per_shift'],

                    'fg_display' => (int)$h['fg'],
                    'ng_display' => (int)$h['ng'],
                    'downtime' => 0,
                    'ng_category' => '',

                    'next_process_name' => $nextProcessName,
                    'wip_qty' => $wipQty,
                    'wip_status' => $wipStatus,
                ];
            }

            $viewShifts[] = [
                'id' => $shiftId,
                'shift_name' => $s['shift_name'],
                'isEditable' => $canEdit,
                'editDeadline' => $editDeadline,

                'items' => $mappedItems,
                'totalTarget' => $totalTarget,
                'totalFG' => $totalFG,
                'totalNG' => $totalNG,
                'totalDT' => $totalDT,
            ];
        }

        $dailyEfficiency = $dailyTarget > 0 ? round(($dailyFG / $dailyTarget) * 100, 1) : 0;

        return view('machining/assy_shaft/shift_production/index', [
            'date' => $date,
            'shifts' => $viewShifts,
            'ngCategories' => $ngCategories,

            'dailyTarget' => $dailyTarget,
            'dailyFG' => $dailyFG,
            'dailyNG' => $dailyNG,
            'dailyDT' => $dailyDT,
            'dailyEfficiency' => $dailyEfficiency,
        ]);
    }

    /**
     * Simpan Koreksi:
     * - Adjust delta ke slot terakhir shift supaya SUM sesuai input
     * - ✅ Setelah koreksi, update stock WIP inbound (prev -> AB) = SUM(qty_fg) harian (qty OK)
     */
    public function store()
    {
        $db = db_connect();
        $items = $this->request->getPost('items') ?? [];

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $db->transBegin();
        try {
            $affectedDates = [];

            foreach ($items as $row) {
                $date = $row['date'] ?? null;
                $shiftId = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if (!$date || !$shiftId || !$machineId || !$productId) continue;

                $affectedDates[$date] = true;

                // cek edit window (max 1 jam setelah shift end)
                $slots = $db->table('shift_time_slots sts')
                    ->select('ts.id, ts.time_start, ts.time_end')
                    ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                    ->where('sts.shift_id', $shiftId)
                    ->orderBy('ts.time_start', 'ASC')
                    ->get()->getResultArray();

                $startTime = $slots[0]['time_start'] ?? null;
                $endTime   = $slots ? end($slots)['time_end'] : null;

                $deadline = $this->calcEditDeadline($date, $startTime, $endTime, 60);
                if (!$deadline || time() > strtotime($deadline)) {
                    continue;
                }

                $lastSlotId = $slots ? (int)end($slots)['id'] : 0;
                if (!$lastSlotId) continue;

                $newFG = (int)($row['fg'] ?? 0);
                $newNG = (int)($row['ng'] ?? 0);

                // current totals
                $cur = $db->table('machining_assy_shaft_hourly')
                    ->select('SUM(qty_fg) fg, SUM(qty_ng) ng')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->where('machine_id', $machineId)
                    ->where('product_id', $productId)
                    ->get()->getRowArray();

                $curFG = (int)($cur['fg'] ?? 0);
                $curNG = (int)($cur['ng'] ?? 0);

                $deltaFG = $newFG - $curFG;
                $deltaNG = $newNG - $curNG;

                // row slot terakhir
                $lastRow = $db->table('machining_assy_shaft_hourly')
                    ->where([
                        'production_date' => $date,
                        'shift_id'        => $shiftId,
                        'time_slot_id'    => $lastSlotId,
                        'machine_id'      => $machineId,
                        'product_id'      => $productId
                    ])->get()->getRowArray();

                if ($lastRow) {
                    $updatedFG = max(0, (int)$lastRow['qty_fg'] + $deltaFG);
                    $updatedNG = max(0, (int)$lastRow['qty_ng'] + $deltaNG);

                    $payload = [
                        'qty_fg' => $updatedFG,
                        'qty_ng' => $updatedNG,
                    ];
                    if ($db->fieldExists('updated_at', 'machining_assy_shaft_hourly')) {
                        $payload['updated_at'] = date('Y-m-d H:i:s');
                    }
                    $db->table('machining_assy_shaft_hourly')->where('id', $lastRow['id'])->update($payload);
                } else {
                    $payload = [
                        'production_date' => $date,
                        'shift_id'        => $shiftId,
                        'time_slot_id'    => $lastSlotId,
                        'machine_id'      => $machineId,
                        'product_id'      => $productId,
                        'qty_fg'          => max(0, $deltaFG),
                        'qty_ng'          => max(0, $deltaNG),
                    ];
                    if ($db->fieldExists('created_at', 'machining_assy_shaft_hourly')) {
                        $payload['created_at'] = date('Y-m-d H:i:s');
                    }
                    if ($db->fieldExists('updated_at', 'machining_assy_shaft_hourly')) {
                        $payload['updated_at'] = date('Y-m-d H:i:s');
                    }
                    $db->table('machining_assy_shaft_hourly')->insert($payload);
                }
            }

            // ✅ setelah koreksi, sync stock WIP inbound (prev -> AB) berdasarkan SUM OK harian
            foreach (array_keys($affectedDates) as $d) {
                $this->syncAssyshaftWipStockFromHourly($db, (string)$d);
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Koreksi Assy shaft berhasil disimpan + Stock WIP ter-update');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ================== HELPERS ==================

    private function getProcessAssyshaft($db): ?array
    {
        // by code AB if exists
        if ($db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')
                ->select('id, process_code, process_name')
                ->where('process_code', 'AS')
                ->get()->getRowArray();
            if ($row) return $row;
        }

        $row = $db->table('production_processes')
            ->select('id, process_code, process_name')
            ->where('process_name', 'Assy shaft')
            ->get()->getRowArray();

        if ($row) return $row;

        // LIKE fallback
        $row = $db->table('production_processes')
            ->select('id, process_code, process_name')
            ->like('process_name', 'Assy shaft')
            ->get()->getRowArray();

        return $row ?: null;
    }

    private function calcEditDeadline(?string $date, ?string $start, ?string $end, int $minutesAfterEnd): ?string
    {
        if (!$date || !$start || !$end) return null;

        $startTs = strtotime($date.' '.$start);
        $endTs   = strtotime($date.' '.$end);
        if ($endTs <= $startTs) $endTs += 86400;

        $deadlineTs = $endTs + ($minutesAfterEnd * 60);
        return date('Y-m-d H:i:s', $deadlineTs);
    }

    private function getNextProcessId($db, int $productId, ?int $currentProcessId): ?int
    {
        if (!$currentProcessId) return null;

        $cur = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1
            ])->get()->getRowArray();

        if (!$cur) return null;

        $seq = (int)$cur['sequence'];

        $next = $db->table('product_process_flows')
            ->select('process_id')
            ->where([
                'product_id' => $productId,
                'sequence'   => $seq + 1,
                'is_active'  => 1
            ])->get()->getRowArray();

        return $next ? (int)$next['process_id'] : null;
    }

    /* =========================
     * ✅ Tambahan: detect date col production_wip
     * ========================= */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        return 'production_date';
    }

    /* =========================
     * ✅ Tambahan: prev process dari flow list
     * ========================= */
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
     * ✅ Tambahan: SYNC STOCK WIP inbound (prev -> AB) dari hourly OK (SUM qty_fg)
     * - SAMA POLA dengan Machining Hourly
     * ========================================================= */
    private function syncAssyshaftWipStockFromHourly($db, string $date): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->tableExists('machining_assy_shaft_hourly')) return;
        if (!$db->tableExists('daily_schedule_items') || !$db->tableExists('daily_schedules')) return;

        $assyProcess = $this->getProcessAssyshaft($db);
        $assyProcessId = (int)($assyProcess['id'] ?? 0);
        if ($assyProcessId <= 0) return;

        $wipDateCol = $this->detectWipDateColumn($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        if (!$hasStock) return;

        // semua schedule item AB pada tanggal tsb (SEMUA SHIFT)
        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy shaft')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

        // total OK harian per machine+product
        $actualRows = $db->table('machining_assy_shaft_hourly')
            ->select('machine_id, product_id, SUM(qty_fg) AS fg_total')
            ->where('production_date', $date)
            ->groupBy('machine_id, product_id')
            ->get()
            ->getResultArray();

        $actualMap = [];
        foreach ($actualRows as $a) {
            $actualMap[(int)$a['machine_id'].'_'.(int)$a['product_id']] = (int)($a['fg_total'] ?? 0);
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

            // key inbound: prev -> AB (source daily_schedule_items)
            $key = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $assyProcessId,
            ];

            // gunakan source_table/source_id kalau kolomnya ada (lebih presisi)
            if ($db->fieldExists('source_table', 'production_wip')) $key['source_table'] = 'daily_schedule_items';
            if ($db->fieldExists('source_id', 'production_wip'))    $key['source_id']    = $dsiId;

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
}
