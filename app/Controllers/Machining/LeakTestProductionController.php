<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class LeakTestProductionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        // Leak Test process (sesuaikan kalau bukan LT)
        $leakProcess = $db->table('production_processes')
            ->select('id, process_code, process_name')
            ->where('process_code', 'LT')
            ->get()->getRowArray();
        $leakProcessId = $leakProcess['id'] ?? null;

        // SHIFT MC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // NG Categories
        $ngCategories = $db->table('ng_categories')
            ->select('id, ng_code, ng_name')
            ->orderBy('ng_code', 'ASC')
            ->get()->getResultArray();

        // Slots per shift -> deadline koreksi
        $slotsByShift = [];
        foreach ($shifts as $s) {
            $slotsByShift[$s['id']] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $s['id'])
                ->orderBy('ts.time_start', 'ASC')
                ->get()->getResultArray();
        }

        // schedule leak test
        $scheduleRows = $db->table('daily_schedule_items dsi')
            ->select('
                ds.shift_id,
                dsi.machine_id,
                m.machine_code,
                m.line_position,
                dsi.product_id,
                p.part_no,
                p.part_name,
                dsi.target_per_shift
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->join('products p', 'p.id = dsi.product_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Leak Test')
            ->where('dsi.target_per_shift >', 0)
            ->orderBy('ds.shift_id', 'ASC')
            ->orderBy('m.line_position', 'ASC')
            ->get()->getResultArray();

        // hourly totals leak test (✅ tanpa downtime)
        $hourlyTotals = $db->table('machining_leak_test_hourly')
            ->select('
                shift_id, machine_id, product_id,
                SUM(qty_ok) ok,
                SUM(qty_ng) ng,
                MAX(ng_category_id) ng_category_id
            ')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()->getResultArray();

        $hourlyMap = [];
        foreach ($hourlyTotals as $h) {
            $k = $h['shift_id'].'_'.$h['machine_id'].'_'.$h['product_id'];
            $hourlyMap[$k] = $h;
        }

        // process name map
        $processNameMap = [];
        $pps = $db->table('production_processes')
            ->select('id, process_name')
            ->get()->getResultArray();
        foreach ($pps as $pp) $processNameMap[$pp['id']] = $pp['process_name'];

        // WIP outbound LT -> next
        $wipOutMap = [];
        if ($leakProcessId) {
            $wips = $db->table('production_wip pw')
                ->select('pw.product_id, pw.from_process_id, pw.to_process_id, pw.status, pw.qty_in, pw.qty_out, pw.stock, pw.qty')
                ->where('pw.production_date', $date)
                ->where('pw.from_process_id', $leakProcessId)
                ->get()->getResultArray();

            foreach ($wips as $w) {
                $wipOutMap[$w['product_id'].'_'.$w['to_process_id']] = $w;
            }
        }

        $dailyTarget = 0;
        $dailyFG = 0; // OK
        $dailyNG = 0;
        $dailyDT = 0; // ✅ tidak ada downtime, set 0 saja

        $viewShifts = [];
        foreach ($shifts as $s) {
            $shiftId = (int)$s['id'];

            $slots = $slotsByShift[$shiftId] ?? [];
            $startTime = $slots[0]['time_start'] ?? null;
            $endTime   = $slots ? end($slots)['time_end'] : null;

            $editDeadline = $this->calcEditDeadline($date, $startTime, $endTime, 60);
            $isEditable = $editDeadline ? (time() <= strtotime($editDeadline)) : false;

            $items = array_values(array_filter($scheduleRows, fn($r) => (int)$r['shift_id'] === $shiftId));

            $mappedItems = [];
            foreach ($items as $r) {
                $key = $shiftId.'_'.$r['machine_id'].'_'.$r['product_id'];
                $h = $hourlyMap[$key] ?? ['ok'=>0,'ng'=>0,'ng_category_id'=>null];

                $target = (int)$r['target_per_shift'];
                $ok = (int)$h['ok'];
                $ng = (int)$h['ng'];
                $dt = 0;

                $dailyTarget += $target;
                $dailyFG     += $ok;
                $dailyNG     += $ng;

                $nextProcessId = $this->getNextProcessId($db, (int)$r['product_id'], $leakProcessId);
                $nextProcessName = $nextProcessId ? ($processNameMap[$nextProcessId] ?? '-') : '-';

                $wip = ($nextProcessId)
                    ? ($wipOutMap[(int)$r['product_id'].'_'.$nextProcessId] ?? null)
                    : null;

                $wipStatus = $wip['status'] ?? 'WAITING';

                $wipQty = 0;
                if ($wip) {
                    if (isset($wip['stock'])) $wipQty = (int)$wip['stock'];
                    elseif (isset($wip['qty_out'])) $wipQty = (int)$wip['qty_out'];
                    elseif (isset($wip['qty'])) $wipQty = (int)$wip['qty'];
                }

                $mappedItems[] = [
                    'machine_id' => (int)$r['machine_id'],
                    'product_id' => (int)$r['product_id'],
                    'line_position' => $r['line_position'],
                    'machine_code' => $r['machine_code'],
                    'part_no' => $r['part_no'],
                    'part_name' => $r['part_name'],

                    'target' => $target,
                    'fg_display' => $ok,
                    'ng_display' => $ng,
                    'downtime' => $dt,
                    'ng_category_id' => (string)($h['ng_category_id'] ?? ''),

                    'next_process_name' => $nextProcessName,
                    'wip_qty' => $wipQty,
                    'wip_status' => $wipStatus,
                ];
            }

            $viewShifts[] = [
                'id' => $shiftId,
                'shift_name' => $s['shift_name'],
                'isEditable' => $isEditable,
                'editDeadline' => $editDeadline,
                'items' => $mappedItems,
            ];
        }

        $dailyEfficiency = $dailyTarget > 0 ? round(($dailyFG / $dailyTarget) * 100, 1) : 0;

        return view('machining/leak_test/production_shift/index', [
            'date' => $date,
            'operator' => $operator,
            'shifts' => $viewShifts,
            'ngCategories' => $ngCategories,

            'dailyTarget' => $dailyTarget,
            'dailyFG' => $dailyFG,
            'dailyNG' => $dailyNG,
            'dailyDT' => $dailyDT,
            'dailyEfficiency' => $dailyEfficiency,
        ]);
    }

    private function calcEditDeadline(?string $date, ?string $start, ?string $end, int $minutesAfterEnd): ?string
    {
        if (!$date || !$start || !$end) return null;

        $startTs = strtotime($date.' '.$start);
        $endTs   = strtotime($date.' '.$end);
        if ($endTs <= $startTs) $endTs += 86400;

        return date('Y-m-d H:i:s', $endTs + ($minutesAfterEnd * 60));
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
