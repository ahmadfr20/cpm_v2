<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class LeakTestDailyProductionController extends BaseController
{
    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        foreach ($shifts as &$shift) {
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start', 'ASC')
                ->get()->getResultArray();

            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;

                $slot['minute'] = (int)(($end - $start) / 60);
                $totalMinute   += $slot['minute'];
            }
            unset($slot);
            $shift['total_minute'] = $totalMinute;

            $shift['items'] = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.id as schedule_item_id,
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
                ->where('ds.shift_id', $shift['id'])
                ->groupStart()
                    ->where('ds.section', 'Leak Test')
                    ->orWhere('ds.section', 'LEAK TEST')
                ->groupEnd()
                ->orderBy('m.line_position', 'ASC')
                ->get()->getResultArray();

            $hourly = $db->table('machining_leak_test_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map']
                    [(int)$h['machine_id']]
                    [(int)$h['product_id']]
                    [(int)$h['time_slot_id']] = $h;
            }
        }
        unset($shift);

        return view('machining/leak_test/daily_production/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts,
            'isAdmin'  => $this->isAdmin()
        ]);
    }

    /* =====================================================
     * STORE HOURLY
     * - simpan hourly (replace/upsert)
     * - sync realtime untuk shift yang terkena
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data kosong / terpotong');
        }

        if (!$db->tableExists('machining_leak_test_hourly')) {
            return redirect()->back()->with('error', 'Tabel machining_leak_test_hourly tidak ditemukan');
        }

        if (!$db->fieldExists('qty_ok', 'machining_leak_test_hourly')) {
            return redirect()->back()->with('error', 'Kolom machining_leak_test_hourly.qty_ok tidak ditemukan');
        }

        $hourlyHasQtyNg   = $db->fieldExists('qty_ng', 'machining_leak_test_hourly');
        $hourlyHasNgCat   = $db->fieldExists('ng_category', 'machining_leak_test_hourly');
        $hourlyHasCreated = $db->fieldExists('created_at', 'machining_leak_test_hourly');
        $hourlyHasUpdated = $db->fieldExists('updated_at', 'machining_leak_test_hourly');

        $isAdmin = $this->isAdmin();
        $now     = date('Y-m-d H:i:s');

        $db->transBegin();

        try {
            $shiftTouched = []; // shift_id => true
            $dateTouched  = null;

            foreach ($items as $row) {
                $date      = (string)($row['date'] ?? '');
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);
                $slotId    = (int)($row['time_slot_id'] ?? 0);

                if ($date === '' || $shiftId <= 0 || $machineId <= 0 || $productId <= 0 || $slotId <= 0) {
                    continue;
                }

                $dateTouched = $date;
                $shiftTouched[$shiftId] = true;

                // REPLACE membutuhkan unique key, jika tidak ada, CI4 akan tetap INSERT baru.
                // Pastikan ada UNIQUE(production_date,shift_id,machine_id,product_id,time_slot_id)
                $payload = [
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'time_slot_id'    => $slotId,
                    'qty_ok'          => (int)($row['ok'] ?? 0),
                ];

                if ($hourlyHasQtyNg) $payload['qty_ng'] = (int)($row['ng'] ?? 0);
                if ($hourlyHasNgCat) $payload['ng_category'] = $row['ng_category'] ?? null;

                if ($hourlyHasCreated) $payload['created_at'] = $now;
                if ($hourlyHasUpdated) $payload['updated_at'] = $now;

                $ok = $db->table('machining_leak_test_hourly')->replace($payload);
                if ($ok === false) $this->dbFail($db, 'Replace machining_leak_test_hourly');
            }

            if (!$dateTouched || empty($shiftTouched)) {
                $db->transCommit();
                return redirect()->back()->with('success', 'Tidak ada data valid untuk disimpan.');
            }

            // Sync realtime untuk shift yang berubah (seperti Die Casting)
            foreach (array_keys($shiftTouched) as $sid) {
                $this->syncLeakTestRealtime($db, $dateTouched, (int)$sid, $isAdmin);
            }

            if ($db->transStatus() === false) {
                $this->dbFail($db, 'Store transaction failed');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Leak Test hourly tersimpan & realtime WIP tersync.');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * FINISH SHIFT (Ala Die Casting) + ADMIN OVERRIDE
     * - 1) sync realtime semua shift MC tanggal tsb
     * - 2) transfer flow LT -> next
     * - qty_in pindah ke qty_out di NEXT sesuai jumlah input (request kamu)
     * ===================================================== */
    public function finishShift()
    {
        $db      = db_connect();
        $date    = (string)($this->request->getPost('date') ?? date('Y-m-d'));
        $shiftId = (int)($this->request->getPost('shift_id') ?? 0);

        if ($date === '' || $shiftId <= 0) {
            return redirect()->back()->with('error', 'Data tidak lengkap (date/shift_id).');
        }

        $isAdmin = $this->isAdmin();

        if (!$this->isMcShift($db, $shiftId)) {
            return redirect()->back()->with('error', 'Finish Shift hanya boleh untuk shift MC.');
        }

        // non-admin: hanya shift terakhir + sudah berakhir
        if (!$isAdmin) {
            $lastShift = $this->getLastMachiningShift($db);
            if (!$lastShift) {
                return redirect()->back()->with('error', 'Tidak ditemukan shift MC aktif.');
            }
            if ((int)$lastShift['id'] !== $shiftId) {
                return redirect()->back()->with('error', 'Finish Shift hanya boleh pada shift terakhir MC (admin bisa override).');
            }
            if (!$this->isShiftEnded($db, $shiftId, $date)) {
                return redirect()->back()->with('error', 'Shift belum berakhir (admin bisa override).');
            }
        }

        $ltProcessId = $this->getProcessIdByCode($db, 'LT');
        if (!$ltProcessId) {
            return redirect()->back()->with('error', 'Process LT (Leak Test) tidak ditemukan.');
        }

        $mcShiftIds = $this->getMcShiftIds($db);
        if (empty($mcShiftIds)) {
            return redirect()->back()->with('error', 'Tidak ada shift MC aktif.');
        }

        $db->transBegin();

        try {
            // 1) Sync realtime semua shift MC (seperti DC)
            foreach ($mcShiftIds as $sid) {
                $this->syncLeakTestRealtime($db, $date, (int)$sid, $isAdmin);
            }

            // 2) Transfer flow untuk semua inbound LT
            $count = $this->finishShiftTransferFlowAllLt($db, $date, (int)$ltProcessId, $isAdmin);

            if ($db->transStatus() === false) {
                $this->dbFail($db, 'FinishShift transaction failed');
            }

            $db->transCommit();

            $msg = $isAdmin
                ? "Finish Shift Leak Test OK (ADMIN override). Transferred rows: {$count}"
                : "Finish Shift Leak Test OK. Transferred rows: {$count}";

            return redirect()->back()->with('success', $msg);

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * SYNC REALTIME (Hourly -> Schedule Actual + WIP inbound prev->LT)
     * ===================================================== */
    private function syncLeakTestRealtime($db, string $date, int $shiftId, bool $forceAdmin = false): void
    {
        if (!$db->tableExists('machining_leak_test_hourly')) return;
        if (!$db->fieldExists('qty_ok', 'machining_leak_test_hourly')) return;

        $rows = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id, ds.process_id AS lt_process_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.shift_id', $shiftId)
            ->groupStart()
                ->where('ds.section', 'Leak Test')
                ->orWhere('ds.section', 'LEAK TEST')
            ->groupEnd()
            ->get()->getResultArray();

        if (!$rows) return;

        $now = date('Y-m-d H:i:s');

        $dsiHasQtyOk = $db->fieldExists('qty_ok', 'daily_schedule_items');
        $dsiHasQtyA  = $db->fieldExists('qty_a', 'daily_schedule_items');

        $w = $this->wipCols($db);

        foreach ($rows as $r) {
            $dsiId     = (int)$r['dsi_id'];
            $machineId = (int)$r['machine_id'];
            $productId = (int)$r['product_id'];
            if ($dsiId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $sumRow = $db->table('machining_leak_test_hourly')
                ->select('SUM(qty_ok) AS ok_total')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->where('machine_id', $machineId)
                ->where('product_id', $productId)
                ->get()->getRowArray();

            $okTotal = (int)($sumRow['ok_total'] ?? 0);

            // update actual di schedule item
            if ($dsiHasQtyOk) {
                $ok = $db->table('daily_schedule_items')->where('id', $dsiId)->update(['qty_ok' => $okTotal]);
                if ($ok === false) $this->dbFail($db, 'Update daily_schedule_items.qty_ok');
            } elseif ($dsiHasQtyA) {
                $ok = $db->table('daily_schedule_items')->where('id', $dsiId)->update(['qty_a' => $okTotal]);
                if ($ok === false) $this->dbFail($db, 'Update daily_schedule_items.qty_a');
            }

            // lt process id (prefer ds.process_id)
            $ltProcessId = (int)($r['lt_process_id'] ?? 0);
            if ($ltProcessId <= 0) $ltProcessId = (int)($this->getProcessIdByCode($db, 'LT') ?? 0);
            if ($ltProcessId <= 0) continue;

            // upsert WIP inbound prev->LT
            if (!$db->tableExists('production_wip')) continue;

            $prevProcessId = $this->resolvePrevProcessIdActive($db, $productId, $ltProcessId);

            // kunci inbound konsisten (seperti store kamu)
            $where = [
                'production_date' => $date,
                'product_id'      => $productId,
                'to_process_id'   => $ltProcessId,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $dsiId,
            ];

            $exist = $db->table('production_wip')->where($where)->get()->getRowArray();

            // sebelum finish: stock = hasil OK, qty_out = hasil OK (biar report mudah)
            $payload = [
                'from_process_id' => $prevProcessId,
                'status'          => 'SCHEDULED',
            ];
            if ($w['qty_out'])   $payload['qty_out'] = $okTotal;
            if ($w['stock'])     $payload['stock']   = $okTotal;
            if ($w['updated_at']) $payload['updated_at'] = $now;

            if ($exist) {
                // non-admin lock DONE (jangan timpa DONE)
                if (!$forceAdmin && strtoupper((string)($exist['status'] ?? '')) === 'DONE') {
                    continue;
                }
                $ok = $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
                if ($ok === false) $this->dbFail($db, 'Update production_wip inbound realtime');
            } else {
                $ins = $where + $payload;

                if ($w['qty'])    $ins['qty'] = 0;
                if ($w['qty_in']) $ins['qty_in'] = 0;

                if ($w['created_at']) $ins['created_at'] = $now;
                if ($w['updated_at']) $ins['updated_at'] = $now;

                $ok = $db->table('production_wip')->insert($ins);
                if (!$ok) $this->dbFail($db, 'Insert production_wip inbound realtime');
            }
        }
    }

    /* =====================================================
     * TRANSFER FLOW ALL LT
     * - inbound prev->LT : DONE, qty_out=transferQty, stock=0
     * - outbound LT->NEXT: WAITING, qty_in += transferQty,
     *   ✅ qty_out juga += transferQty (sesuai request)
     * ===================================================== */
    private function finishShiftTransferFlowAllLt($db, string $date, int $ltProcessId, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;

        $w = $this->wipCols($db);
        $now = date('Y-m-d H:i:s');

        // ambil semua inbound LT yg belum DONE
        $inRows = $db->table('production_wip')
            ->where('production_date', $date)
            ->where('to_process_id', $ltProcessId)
            ->groupStart()
                ->whereIn('status', ['SCHEDULED','WAITING','IN_PROGRESS'])
                ->orWhere('status IS NULL', null, false)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        if (!$inRows) return 0;

        $processed = 0;

        // agregasi transfer per product
        $byProduct = [];

        foreach ($inRows as $r) {
            $wipId     = (int)($r['id'] ?? 0);
            $productId = (int)($r['product_id'] ?? 0);
            if ($wipId <= 0 || $productId <= 0) continue;

            if (!$forceAdmin && strtoupper((string)($r['status'] ?? '')) === 'DONE') {
                continue;
            }

            // transferQty: pakai stock dulu, fallback qty_out, fallback qty_in
            $stockNow = $w['stock'] ? (int)($r['stock'] ?? 0) : 0;
            $outNow   = $w['qty_out'] ? (int)($r['qty_out'] ?? 0) : 0;
            $inNow    = $w['qty_in'] ? (int)($r['qty_in'] ?? 0) : 0;

            $transferQty = max($stockNow, $outNow, $inNow);

            // mark inbound DONE
            $updA = [
                'status' => 'DONE',
            ];
            if ($w['qty_out']) $updA['qty_out'] = $transferQty;
            if ($w['qty_in'])  $updA['qty_in']  = 0;           // qty_in dianggap sudah dipindah keluar
            if ($w['stock'])   $updA['stock']   = 0;
            if ($w['updated_at']) $updA['updated_at'] = $now;

            $ok = $db->table('production_wip')->where('id', $wipId)->update($updA);
            if ($ok === false) $this->dbFail($db, 'Update production_wip inbound DONE');

            $processed++;

            if ($transferQty <= 0) continue;

            if (!isset($byProduct[$productId])) {
                $nextProcessId = $this->resolveNextProcessByFlow($db, $productId, $ltProcessId) ?? 0;
                $byProduct[$productId] = [
                    'qty' => 0,
                    'next_process_id' => (int)$nextProcessId,
                    'source_wip_id' => $wipId,
                ];
            }
            $byProduct[$productId]['qty'] += $transferQty;
        }

        // upsert outbound LT->NEXT
        foreach ($byProduct as $productId => $agg) {
            $qty = (int)$agg['qty'];
            $nextProcessId = (int)$agg['next_process_id'];

            if ($qty <= 0 || $nextProcessId <= 0) continue;

            $whereB = [
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => $ltProcessId,
                'to_process_id'   => $nextProcessId,
            ];

            $existB = $db->table('production_wip')->where($whereB)->get()->getRowArray();

            if ($existB) {
                // tambah qty_in & qty_out
                $updB = [
                    'status' => 'WAITING',
                ];
                if ($w['updated_at']) $updB['updated_at'] = $now;

                if ($w['qty_in']) {
                    $oldIn = (int)($existB['qty_in'] ?? 0);
                    $updB['qty_in'] = $oldIn + $qty;
                }

                // ✅ sesuai request: qty_in yang pindah juga dicatat ke qty_out
                if ($w['qty_out']) {
                    $oldOut = (int)($existB['qty_out'] ?? 0);
                    $updB['qty_out'] = $oldOut + $qty;
                }

                // optional: kalau kamu ingin stock next = qty_in, ubah ini:
                if ($w['stock']) {
                    $oldStock = (int)($existB['stock'] ?? 0);
                    $updB['stock'] = $oldStock + $qty;
                }

                $ok = $db->table('production_wip')->where('id', (int)$existB['id'])->update($updB);
                if ($ok === false) $this->dbFail($db, 'Update production_wip outbound WAITING');
            } else {
                $insB = $whereB + [
                    'status' => 'WAITING',
                ];
                if ($w['qty']) $insB['qty'] = 0;

                if ($w['qty_in'])  $insB['qty_in']  = $qty;
                if ($w['qty_out']) $insB['qty_out'] = $qty;  // ✅ pindah qty_in -> qty_out sesuai qty input

                if ($w['stock']) $insB['stock'] = $qty;

                if ($w['source_table']) $insB['source_table'] = 'production_wip';
                if ($w['source_id'])    $insB['source_id'] = (int)($agg['source_wip_id'] ?? null);

                if ($w['created_at']) $insB['created_at'] = $now;
                if ($w['updated_at']) $insB['updated_at'] = $now;

                $ok = $db->table('production_wip')->insert($insB);
                if (!$ok) $this->dbFail($db, 'Insert production_wip outbound WAITING');
            }
        }

        return $processed;
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */

    private function isAdmin(): bool
    {
        $role = session()->get('role')
            ?? session()->get('role_name')
            ?? session()->get('user_role')
            ?? session()->get('level')
            ?? '';

        $role = strtolower((string)$role);
        return in_array($role, ['admin','administrator','superadmin','super admin'], true);
    }

    private function getProcessIdByCode($db, string $code): ?int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_code', $code)
            ->get()->getRowArray();

        return $row ? (int)$row['id'] : null;
    }

    private function getMcShiftIds($db): array
    {
        $rows = $db->table('shifts')
            ->select('id')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        return array_values(array_map(fn($r) => (int)$r['id'], $rows));
    }

    private function isMcShift($db, int $shiftId): bool
    {
        $shift = $db->table('shifts')
            ->select('shift_name, is_active')
            ->where('id', $shiftId)
            ->get()->getRowArray();

        if (!$shift) return false;
        if ((int)($shift['is_active'] ?? 0) !== 1) return false;
        return (stripos((string)($shift['shift_name'] ?? ''), 'MC') !== false);
    }

    private function getLastMachiningShift($db): ?array
    {
        $row = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'DESC')
            ->get()->getRowArray();

        return $row ?: null;
    }

    private function isShiftEnded($db, int $shiftId, string $date): bool
    {
        date_default_timezone_set('Asia/Jakarta');

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('ts.time_start', 'ASC')
            ->get()->getResultArray();

        if (!$slots) return false;

        $firstStart = $slots[0]['time_start'];
        $lastEnd    = $slots[count($slots) - 1]['time_end'];

        $startDT = strtotime($date . ' ' . $firstStart);
        $endDT   = strtotime($date . ' ' . $lastEnd);
        if ($endDT <= $startDT) $endDT += 86400;

        return time() >= $endDT;
    }

    private function resolvePrevProcessIdActive($db, int $productId, int $currentProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;

        $cur = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1,
            ])
            ->get()->getRowArray();

        if (!$cur) return null;

        $seq = (int)$cur['sequence'];
        if ($seq <= 1) return null;

        $prev = $db->table('product_process_flows')
            ->select('process_id')
            ->where([
                'product_id' => $productId,
                'sequence'   => $seq - 1,
                'is_active'  => 1,
            ])
            ->get()->getRowArray();

        return $prev ? (int)$prev['process_id'] : null;
    }

    private function resolveNextProcessByFlow($db, int $productId, int $fromProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;

        $flows = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();

        if (!$flows) return null;

        $idx = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === (int)$fromProcessId) {
                $idx = $i;
                break;
            }
        }

        if ($idx === null) return isset($flows[1]) ? (int)$flows[1]['process_id'] : null;
        return isset($flows[$idx + 1]) ? (int)$flows[$idx + 1]['process_id'] : null;
    }

    private function wipCols($db): array
    {
        if (!$db->tableExists('production_wip')) {
            return [
                'qty' => false, 'qty_in' => false, 'qty_out' => false, 'stock' => false,
                'source_table' => false, 'source_id' => false, 'created_at' => false, 'updated_at' => false,
            ];
        }

        return [
            'qty'         => $db->fieldExists('qty', 'production_wip'),
            'qty_in'      => $db->fieldExists('qty_in', 'production_wip'),
            'qty_out'     => $db->fieldExists('qty_out', 'production_wip'),
            'stock'       => $db->fieldExists('stock', 'production_wip'),
            'source_table'=> $db->fieldExists('source_table', 'production_wip'),
            'source_id'   => $db->fieldExists('source_id', 'production_wip'),
            'created_at'  => $db->fieldExists('created_at', 'production_wip'),
            'updated_at'  => $db->fieldExists('updated_at', 'production_wip'),
        ];
    }

    private function dbFail($db, string $context): void
    {
        $err  = $db->error();
        $msg  = $err['message'] ?? 'Unknown DB error';
        $code = $err['code'] ?? 0;
        throw new \RuntimeException("{$context}: [{$code}] {$msg}");
    }
}
