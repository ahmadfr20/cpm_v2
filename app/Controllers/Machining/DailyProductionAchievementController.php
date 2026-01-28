<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyProductionAchievementController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // process machining
        $machiningProcess = $db->table('production_processes')
            ->select('id, process_code, process_name')
            ->where('process_code', 'MC')
            ->get()->getRowArray();

        $machiningProcessId = $machiningProcess['id'] ?? null;

        // shifts MC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // ng categories (master)
        $ngCategories = $db->table('ng_categories')
            ->select('id, ng_code, ng_name')
            ->orderBy('ng_code', 'ASC')
            ->get()->getResultArray();

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

        // Schedule Machining (target per shift)
        $scheduleRows = $db->table('daily_schedule_items dsi')
            ->select('
                ds.shift_id,
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
            ->where('ds.section', 'Machining')
            ->where('dsi.target_per_shift >', 0)
            ->orderBy('ds.shift_id', 'ASC')
            ->orderBy('m.line_position', 'ASC')
            ->get()->getResultArray();

        // Hourly totals (fg/ng/dt) per shift+machine+product
        $hourlyTotals = $db->table('machining_hourly')
            ->select('
                shift_id, machine_id, product_id,
                SUM(qty_fg) fg,
                SUM(qty_ng) ng,
                SUM(downtime) downtime,
                MAX(ng_category) ng_category
            ')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()->getResultArray();

        $hourlyMap = [];
        foreach ($hourlyTotals as $h) {
            $key = $h['shift_id'].'_'.$h['machine_id'].'_'.$h['product_id'];
            $hourlyMap[$key] = $h;
        }

        // WIP for Machining inbound: (prev -> MC)
        // NOTE: kalau kamu ingin tampil wip outbound (MC -> next), tinggal ganti to_process_id.
        $wipMap = [];
        if ($machiningProcessId) {
            $wips = $db->table('production_wip pw')
                ->select('pw.product_id, pw.from_process_id, pw.to_process_id, pw.status, pw.qty_in, pw.qty_out, pw.stock')
                ->where('pw.production_date', $date)
                ->where('pw.to_process_id', $machiningProcessId)
                ->get()->getResultArray();

            foreach ($wips as $w) {
                // key by product only (umumnya inbound per product)
                $wipMap[$w['product_id']] = $w;
            }
        }

        // Process name map (untuk next process)
        $processNameMap = [];
        $processes = $db->table('production_processes')->select('id, process_name')->get()->getResultArray();
        foreach ($processes as $pp) $processNameMap[$pp['id']] = $pp['process_name'];

        // Build shifts output like Die Casting view structure
        $viewShifts = [];
        $dailyTarget = 0; $dailyFG = 0; $dailyNG = 0; $dailyDT = 0;

        foreach ($shifts as $s) {
            $shiftId = (int)$s['id'];

            $slots = $shiftSlots[$shiftId] ?? [];
            $startTime = $slots[0]['time_start'] ?? null;
            $endTime   = $slots ? end($slots)['time_end'] : null;

            // deadline = 1 jam setelah shift end (handle cross midnight)
            $editDeadline = $this->calcEditDeadline($date, $startTime, $endTime, 60);
            $canEdit = $editDeadline ? (time() <= strtotime($editDeadline)) : false;

            // items by shift
            $items = array_values(array_filter($scheduleRows, fn($r) => (int)$r['shift_id'] === $shiftId));

            $mappedItems = [];
            $no = 1;

            $totalTarget = 0; $totalFG = 0; $totalNG = 0; $totalDT = 0;

            foreach ($items as $r) {
                $k = $shiftId.'_'.$r['machine_id'].'_'.$r['product_id'];
                $h = $hourlyMap[$k] ?? ['fg'=>0,'ng'=>0,'downtime'=>0,'ng_category'=>null];

                $totalTarget += (int)$r['target_per_shift'];
                $totalFG     += (int)$h['fg'];
                $totalNG     += (int)$h['ng'];
                $totalDT     += (int)$h['downtime'];

                $dailyTarget += (int)$r['target_per_shift'];
                $dailyFG     += (int)$h['fg'];
                $dailyNG     += (int)$h['ng'];
                $dailyDT     += (int)$h['downtime'];

                // next process from flow (sequence + 1)
                $nextProcessId = $this->getNextProcessId($db, (int)$r['product_id'], $machiningProcessId);
                $nextProcessName = $nextProcessId ? ($processNameMap[$nextProcessId] ?? '-') : '-';

                // WIP inbound display
                $wip = $wipMap[(int)$r['product_id']] ?? null;
                $wipStatus = $wip['status'] ?? 'WAITING';
                $wipQty = (int)($wip['qty_in'] ?? 0);

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
                    'downtime' => (int)$h['downtime'],
                    'ng_category' => (string)($h['ng_category'] ?? ''),

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

        return view('machining/daily_production_achievement/index', [
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
     * - Tidak overwrite semua hourly
     * - Adjust delta ke slot terakhir shift supaya SUM sesuai input
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
            foreach ($items as $row) {
                $date = $row['date'] ?? null;
                $shiftId = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if (!$date || !$shiftId || !$machineId || !$productId) continue;

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
                    continue; // terkunci
                }

                $lastSlotId = $slots ? (int)end($slots)['id'] : 0;
                if (!$lastSlotId) continue;

                $newFG = (int)($row['fg'] ?? 0);
                $newNG = (int)($row['ng'] ?? 0);
                $newDT = (int)($row['downtime'] ?? 0);
                $newNGCat = (string)($row['ng_category'] ?? '');

                // current totals
                $cur = $db->table('machining_hourly')
                    ->select('SUM(qty_fg) fg, SUM(qty_ng) ng, SUM(downtime) downtime')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->where('machine_id', $machineId)
                    ->where('product_id', $productId)
                    ->get()->getRowArray();

                $curFG = (int)($cur['fg'] ?? 0);
                $curNG = (int)($cur['ng'] ?? 0);

                $deltaFG = $newFG - $curFG;
                $deltaNG = $newNG - $curNG;

                // ambil row slot terakhir
                $lastRow = $db->table('machining_hourly')
                    ->where([
                        'production_date' => $date,
                        'shift_id' => $shiftId,
                        'time_slot_id' => $lastSlotId,
                        'machine_id' => $machineId,
                        'product_id' => $productId
                    ])->get()->getRowArray();

                if ($lastRow) {
                    $updatedFG = max(0, (int)$lastRow['qty_fg'] + $deltaFG);
                    $updatedNG = max(0, (int)$lastRow['qty_ng'] + $deltaNG);

                    $db->table('machining_hourly')->where('id', $lastRow['id'])->update([
                        'qty_fg' => $updatedFG,
                        'qty_ng' => $updatedNG,
                        'downtime' => $newDT,
                        'ng_category' => $newNGCat ?: null,
                        'remark' => 'CORRECTION',
                    ]);
                } else {
                    // kalau slot terakhir belum ada, buat row baru sebagai "closing correction"
                    $db->table('machining_hourly')->insert([
                        'production_date' => $date,
                        'shift_id' => $shiftId,
                        'time_slot_id' => $lastSlotId,
                        'machine_id' => $machineId,
                        'product_id' => $productId,
                        'qty_fg' => max(0, $deltaFG), // karena curFG=0 kalau tidak ada data sama sekali
                        'qty_ng' => max(0, $deltaNG),
                        'downtime' => $newDT,
                        'ng_category' => $newNGCat ?: null,
                        'remark' => 'CORRECTION',
                    ]);
                }
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Koreksi machining berhasil disimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ================== HELPERS ==================

    private function calcEditDeadline(?string $date, ?string $start, ?string $end, int $minutesAfterEnd): ?string
    {
        if (!$date || !$start || !$end) return null;

        $startTs = strtotime($date.' '.$start);
        $endTs   = strtotime($date.' '.$end);
        if ($endTs <= $startTs) $endTs += 86400; // cross midnight

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
}
