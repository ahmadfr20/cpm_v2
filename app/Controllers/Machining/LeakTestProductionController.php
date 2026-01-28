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

        // Leak Test process (LT)
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
            ->groupStart()
                ->where('ds.section', 'Leak Test')
                ->orWhere('ds.section', 'LEAK TEST')
            ->groupEnd()
            ->where('dsi.target_per_shift >', 0)
            ->orderBy('ds.shift_id', 'ASC')
            ->orderBy('m.line_position', 'ASC')
            ->get()->getResultArray();

        // hourly totals leak test
        $hasNgCatId = $db->fieldExists('ng_category_id', 'machining_leak_test_hourly');

        $hourlyTotals = $db->table('machining_leak_test_hourly')
            ->select(
                $hasNgCatId
                    ? 'shift_id, machine_id, product_id, SUM(qty_ok) ok, SUM(qty_ng) ng, MAX(ng_category_id) ng_category_id'
                    : 'shift_id, machine_id, product_id, SUM(qty_ok) ok, SUM(qty_ng) ng, MAX(ng_category) ng_category'
            )
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
        $pps = $db->table('production_processes')->select('id, process_name')->get()->getResultArray();
        foreach ($pps as $pp) $processNameMap[$pp['id']] = $pp['process_name'];

        // WIP outbound LT -> next (untuk ditampilkan)
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
        $dailyFG = 0;
        $dailyNG = 0;
        $dailyDT = 0;

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
                $h   = $hourlyMap[$key] ?? ['ok'=>0,'ng'=>0];

                $target = (int)$r['target_per_shift'];
                $ok = (int)($h['ok'] ?? 0);
                $ng = (int)($h['ng'] ?? 0);

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
                    'shift_id' => $shiftId,
                    'machine_id' => (int)$r['machine_id'],
                    'product_id' => (int)$r['product_id'],

                    'line_position' => $r['line_position'],
                    'machine_code' => $r['machine_code'],
                    'part_no' => $r['part_no'],
                    'part_name' => $r['part_name'],

                    'target' => $target,
                    'fg_display' => $ok,
                    'ng_display' => $ng,

                    // simpan category yang ada
                    'ng_category_id' => (string)($h['ng_category_id'] ?? ''),
                    'ng_category'    => (string)($h['ng_category'] ?? ''),

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

    /**
     * =====================================================
     * STORE: input OK/NG per shift -> update hourly + update WIP stock
     * =====================================================
     * Expect POST:
     *  - date (YYYY-MM-DD)
     *  - items[]: [shift_id, machine_id, product_id, qty_ok, qty_ng, ng_category_id|ng_category]
     */
    public function store()
    {
        $db    = db_connect();
        $date  = (string)($this->request->getPost('date') ?? '');
        $items = $this->request->getPost('items');

        if ($date === '' || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        $db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');

            $lt = $db->table('production_processes')->select('id')->where('process_code', 'LT')->get()->getRowArray();
            $ltProcessId = (int)($lt['id'] ?? 0);
            if ($ltProcessId <= 0) {
                throw new \RuntimeException('Process LT tidak ditemukan di production_processes');
            }

            $hasNgCatId = $db->fieldExists('ng_category_id', 'machining_leak_test_hourly');
            $hasNgCat   = $db->fieldExists('ng_category', 'machining_leak_test_hourly');

            // untuk rekap shift mana saja yang tersentuh
            $affectedShifts = []; // [shiftId=>true]

            // 1) upsert data "per shift" ke machining_leak_test_hourly
            // NOTE: karena tabel hourly butuh time_slot_id di beberapa schema,
            // kita buat time_slot_id = 0 (atau 1) jika kolom ada & NOT NULL.
            // Kalau kolom time_slot_id memang wajib dan tidak boleh 0, ganti ke ID slot khusus (mis. slot terakhir).
            $hasTimeSlotId = $db->fieldExists('time_slot_id', 'machining_leak_test_hourly');

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                $qtyOk = (int)($row['qty_ok'] ?? $row['ok'] ?? 0);
                $qtyNg = (int)($row['qty_ng'] ?? $row['ng'] ?? 0);

                if ($qtyOk < 0) $qtyOk = 0;
                if ($qtyNg < 0) $qtyNg = 0;

                $affectedShifts[$shiftId] = true;

                $where = [
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                ];

                if ($hasTimeSlotId) {
                    // "per shift" marker
                    $where['time_slot_id'] = 0;
                }

                $data = [
                    'qty_ok'     => $qtyOk,
                    'qty_ng'     => $qtyNg,
                    'updated_at' => $now,
                ];

                if ($hasNgCatId) {
                    $data['ng_category_id'] = ($row['ng_category_id'] ?? '') !== '' ? (int)$row['ng_category_id'] : null;
                } elseif ($hasNgCat) {
                    $data['ng_category'] = ($row['ng_category'] ?? null);
                }

                $exist = $db->table('machining_leak_test_hourly')->where($where)->get()->getRowArray();

                if ($exist) {
                    $db->table('machining_leak_test_hourly')->where('id', (int)$exist['id'])->update($data);
                } else {
                    $db->table('machining_leak_test_hourly')->insert($where + $data + ['created_at' => $now]);
                }
            }

            // 2) update WIP stock berdasarkan total OK per product (per shift)
            if (!$db->tableExists('production_wip')) {
                $db->transCommit();
                return redirect()->back()->with('success', 'Data tersimpan (WIP table tidak ada)');
            }

            $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
            $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
            $hasStock  = $db->fieldExists('stock', 'production_wip');
            $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
            $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');

            foreach (array_keys($affectedShifts) as $shiftId) {
                // total fg per product pada shift tsb
                $fgRows = $db->table('machining_leak_test_hourly')
                    ->select('product_id, SUM(qty_ok) AS fg')
                    ->where('production_date', $date)
                    ->where('shift_id', (int)$shiftId)
                    ->groupBy('product_id')
                    ->get()->getResultArray();

                foreach ($fgRows as $fgRow) {
                    $productId = (int)($fgRow['product_id'] ?? 0);
                    $fg        = (int)($fgRow['fg'] ?? 0);
                    if ($productId <= 0) continue;

                    // prev & next process dari flow aktif
                    $prevProcessId = $this->getPrevProcessId($db, $productId, $ltProcessId); // bisa null kalau LT proses pertama
                    $nextProcessId = $this->getNextProcessId($db, $productId, $ltProcessId); // bisa null kalau LT proses terakhir

                    // (A) inbound prev -> LT: qty_out & stock = fg
                    if ($prevProcessId !== null) {
                        $inKey = [
                            'production_date' => $date,
                            'product_id'      => $productId,
                            'from_process_id' => (int)$prevProcessId,
                            'to_process_id'   => $ltProcessId,
                        ];

                        $inExist = $db->table('production_wip')->where($inKey)->get()->getRowArray();

                        $inPayload = [];
                        if ($hasQtyOut) $inPayload['qty_out'] = $fg;
                        if ($hasStock)  $inPayload['stock']   = $fg;
                        if ($hasUpdatedAt) $inPayload['updated_at'] = $now;

                        if ($inExist) {
                            $db->table('production_wip')->where('id', (int)$inExist['id'])->update($inPayload);
                        } else {
                            $insert = $inKey + [
                                'status'       => 'SCHEDULED',
                                'qty'          => 0,
                                'source_table' => 'machining_leak_test_hourly',
                                'source_id'    => null,
                            ];
                            if ($hasQtyIn)  $insert['qty_in']  = 0;
                            if ($hasQtyOut) $insert['qty_out'] = $fg;
                            if ($hasStock)  $insert['stock']   = $fg;
                            if ($hasCreatedAt) $insert['created_at'] = $now;
                            if ($hasUpdatedAt) $insert['updated_at'] = $now;

                            $db->table('production_wip')->insert($insert);
                        }
                    }

                    // (B) outbound LT -> next: qty_in & stock = fg (agar next process lihat WIP)
                    if (!empty($nextProcessId)) {
                        $outKey = [
                            'production_date' => $date,
                            'product_id'      => $productId,
                            'from_process_id' => $ltProcessId,
                            'to_process_id'   => (int)$nextProcessId,
                        ];

                        $outExist = $db->table('production_wip')->where($outKey)->get()->getRowArray();

                        $outPayload = [
                            'status' => 'WAITING',
                        ];
                        if ($hasQtyIn)  $outPayload['qty_in']  = $fg;  // set, bukan tambah (biar tidak double)
                        if ($hasStock)  $outPayload['stock']   = $fg;
                        if ($hasUpdatedAt) $outPayload['updated_at'] = $now;

                        if ($outExist) {
                            $db->table('production_wip')->where('id', (int)$outExist['id'])->update($outPayload);
                        } else {
                            $insert = $outKey + [
                                'status'       => 'WAITING',
                                'qty'          => 0,
                                'source_table' => 'machining_leak_test_hourly',
                                'source_id'    => null,
                            ];
                            if ($hasQtyIn)  $insert['qty_in']  = $fg;
                            if ($hasQtyOut) $insert['qty_out'] = 0;
                            if ($hasStock)  $insert['stock']   = $fg;
                            if ($hasCreatedAt) $insert['created_at'] = $now;
                            if ($hasUpdatedAt) $insert['updated_at'] = $now;

                            $db->table('production_wip')->insert($insert);
                        }
                    }
                }
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Leak Test tersimpan. Stock WIP Leak Test ikut ter-update.');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function calcEditDeadline(?string $date, ?string $start, ?string $end, int $minutesAfterEnd): ?string
    {
        if (!$date || !$start || !$end) return null;

        $startTs = strtotime($date.' '.$start);
        $endTs   = strtotime($date.' '.$end);
        if ($endTs <= $startTs) $endTs += 86400;

        return date('Y-m-d H:i:s', $endTs + ($minutesAfterEnd * 60));
    }

    private function getPrevProcessId($db, int $productId, int $currentProcessId): ?int
    {
        $cur = $db->table('product_process_flows')
            ->select('sequence')
            ->where(['product_id' => $productId, 'process_id' => $currentProcessId, 'is_active' => 1])
            ->get()->getRowArray();

        if (!$cur) return null;

        $seq = (int)$cur['sequence'];
        if ($seq <= 1) return null;

        $prev = $db->table('product_process_flows')
            ->select('process_id')
            ->where(['product_id' => $productId, 'sequence' => $seq - 1, 'is_active' => 1])
            ->get()->getRowArray();

        return $prev ? (int)$prev['process_id'] : null;
    }

    private function getNextProcessId($db, int $productId, ?int $currentProcessId): ?int
    {
        if (!$currentProcessId) return null;

        $cur = $db->table('product_process_flows')
            ->select('sequence')
            ->where(['product_id' => $productId, 'process_id' => $currentProcessId, 'is_active' => 1])
            ->get()->getRowArray();

        if (!$cur) return null;

        $seq = (int)$cur['sequence'];

        $next = $db->table('product_process_flows')
            ->select('process_id')
            ->where(['product_id' => $productId, 'sequence' => $seq + 1, 'is_active' => 1])
            ->get()->getRowArray();

        return $next ? (int)$next['process_id'] : null;
    }
}
