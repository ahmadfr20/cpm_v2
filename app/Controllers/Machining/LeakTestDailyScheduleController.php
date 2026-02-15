<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class LeakTestDailyScheduleController extends BaseController
{
    /* =====================================================
     * GUARD: role & tanggal (redirect)
     * ===================================================== */
    private function guardScheduleDateByRoleRedirect(string $date)
    {
        $role = session()->get('role') ?? '';

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d) {
            return redirect()->back()->with('error', 'Format tanggal tidak valid');
        }
        $d->setTime(0, 0, 0);

        $today = new \DateTime(date('Y-m-d'));
        $today->setTime(0, 0, 0);

        if ($role !== 'ADMIN' && $d <= $today) {
            return redirect()->back()->with(
                'error',
                'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh mulai besok.'
            );
        }

        return null;
    }

    /* =====================================================
     * PROCESS ID Leak Test (lebih robust)
     * ===================================================== */
    private function getProcessIdLeakTest($db): int
    {
        if ($db->tableExists('production_processes') && $db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')->select('id')
                ->where('process_code', 'LT')
                ->get()->getRowArray();
            if ($row && !empty($row['id'])) return (int)$row['id'];
        }

        // fallback by name
        $row = $db->table('production_processes')->select('id')
            ->like('process_name', 'LEAK TEST')
            ->get()->getRowArray();

        if ($row && !empty($row['id'])) return (int)$row['id'];

        throw new \Exception('Process Leak Test tidak ditemukan (cek production_processes: process_code=LT / process_name like LEAK TEST)');
    }

    /* =====================================================
     * SHIFT seconds
     * ===================================================== */
    private function getTotalSecondShift($db, int $shiftId): int
    {
        if (!$db->tableExists('shift_time_slots') || !$db->tableExists('time_slots')) {
            return 0;
        }

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()->getResultArray();

        $totalSecond = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400;
            $totalSecond += ($end - $start);
        }

        return (int)$totalSecond;
    }

    /* =====================================================
     * VALIDATE product punya flow LT aktif
     * (pakai is_active jika kolom ada)
     * ===================================================== */
    private function validateProductHasFlow($db, int $productId, int $processId): bool
    {
        if (!$db->tableExists('product_process_flows')) return false;

        $q = $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processId);

        if ($db->fieldExists('is_active', 'product_process_flows')) {
            $q->where('is_active', 1);
        }

        return $q->countAllResults() > 0;
    }

    /* =====================================================
     * resolve PREV process by flow (sequence < current)
     * ===================================================== */
    private function resolvePrevProcessByFlow($db, int $productId, int $currentProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;

        $curQ = $db->table('product_process_flows')
            ->select('sequence')
            ->where('product_id', $productId)
            ->where('process_id', $currentProcessId);

        if ($db->fieldExists('is_active', 'product_process_flows')) {
            $curQ->where('is_active', 1);
        }

        $currentFlow = $curQ->orderBy('sequence', 'ASC')->get()->getRowArray();
        if (!$currentFlow) return null;

        $curSeq = (int)$currentFlow['sequence'];

        $prevQ = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('sequence <', $curSeq);

        if ($db->fieldExists('is_active', 'product_process_flows')) {
            $prevQ->where('is_active', 1);
        }

        $prevFlow = $prevQ->orderBy('sequence', 'DESC')->get()->getRowArray();
        return $prevFlow ? (int)$prevFlow['process_id'] : null;
    }

    /* =====================================================
     * Detect date col production_wip
     * ===================================================== */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    /* =====================================================
     * Detect stock col production_wip
     * ===================================================== */
    private function detectWipStockColumn($db): ?string
    {
        foreach (['stock', 'stock_qty', 'qty_stock'] as $c) {
            if ($db->fieldExists($c, 'production_wip')) return $c;
        }
        return null;
    }

    private function ensureTransferColumn($db): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->fieldExists('transfer', 'production_wip')) {
            throw new \Exception('Kolom transfer belum ada di production_wip. Jalankan migration tambah kolom transfer.');
        }
    }

    /* =========================================================
     * Ambil ROW stock terbaru untuk prev process
     * ========================================================= */
    private function getPrevProcessLatestRow($db, string $date, int $productId, int $prevProcessId): ?array
    {
        if (!$db->tableExists('production_wip')) return null;

        $dateCol  = $this->detectWipDateColumn($db);
        $stockCol = $this->detectWipStockColumn($db);
        if (!$stockCol) return null;

        $builder = $db->table('production_wip pw')
            ->select("pw.id, pw.$stockCol as stock_val, pw.transfer, pw.qty_in, pw.qty_out")
            ->where('pw.product_id', $productId)
            ->where('pw.to_process_id', $prevProcessId)
            ->where("DATE(pw.$dateCol) <=", $date, false);

        $row = $builder
            ->orderBy("DATE(pw.$dateCol)", 'DESC', false)
            ->orderBy("pw.id", 'DESC')
            ->get()->getRowArray();

        if ($row) return $row;

        return $db->table('production_wip pw')
            ->select("pw.id, pw.$stockCol as stock_val, pw.transfer, pw.qty_in, pw.qty_out")
            ->where('pw.product_id', $productId)
            ->where('pw.to_process_id', $prevProcessId)
            ->orderBy("pw.id", 'DESC')
            ->get()->getRowArray();
    }

    private function getPrevProcessStock($db, string $date, int $productId, int $prevProcessId): int
    {
        $row = $this->getPrevProcessLatestRow($db, $date, $productId, $prevProcessId);
        return (int)($row['stock_val'] ?? 0);
    }

    /* =========================================================
     * Reserve/Release prev -> next
     * ========================================================= */
    private function applyPrevReserveToNext($db, string $date, int $productId, int $prevProcessId, int $deltaQty): void
    {
        if ($deltaQty === 0) return;
        if (!$db->tableExists('production_wip')) return;

        $this->ensureTransferColumn($db);

        $stockCol = $this->detectWipStockColumn($db);
        if (!$stockCol) return;

        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $now = date('Y-m-d H:i:s');

        $prevRow = $this->getPrevProcessLatestRow($db, $date, $productId, $prevProcessId);
        if (!$prevRow) return;

        $rowId       = (int)$prevRow['id'];
        $curStock    = (int)($prevRow['stock_val'] ?? 0);
        $curTransfer = (int)($prevRow['transfer'] ?? 0);

        $newStock = $curStock - $deltaQty;
        if ($newStock < 0) $newStock = 0;

        $newTransfer = $curTransfer + $deltaQty;
        if ($newTransfer < 0) $newTransfer = 0;

        $upd = [
            $stockCol  => $newStock,
            'transfer' => $newTransfer,
        ];
        if ($hasUpdatedAt) $upd['updated_at'] = $now;

        $db->table('production_wip')->where('id', $rowId)->update($upd);
    }

    /* =========================================================
     * Incoming WIP untuk Leak Test saat schedule
     * ========================================================= */
    private function upsertIncomingWipForLeakTest(
        $db,
        string $date,
        int $productId,
        int $fromPrevProcessId,
        int $toLeakTestProcessId,
        int $scheduleItemId,
        int $deltaQty
    ): void {
        if ($deltaQty === 0) return;
        if (!$db->tableExists('production_wip')) return;

        $dateCol = $this->detectWipDateColumn($db);

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQty    = $db->fieldExists('qty', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

        if (!$hasQtyIn) throw new \Exception('Kolom qty_in tidak ada di production_wip');

        $now = date('Y-m-d H:i:s');

        $key = [
            $dateCol          => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromPrevProcessId,
            'to_process_id'   => $toLeakTestProcessId,
            'source_table'    => 'daily_schedule_items',
            'source_id'       => $scheduleItemId,
        ];

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

        if ($exist) {
            $newQtyIn = (int)($exist['qty_in'] ?? 0) + $deltaQty;
            if ($newQtyIn < 0) $newQtyIn = 0;

            $upd = [
                'qty_in' => $newQtyIn,
                'status' => ($newQtyIn > 0) ? 'WAITING' : ($exist['status'] ?? 'WAITING'),
            ];
            if ($hasQty) $upd['qty'] = $newQtyIn;
            if ($hasQtyOut) $upd['qty_out'] = (int)($exist['qty_out'] ?? 0);
            if ($hasStock) $upd['stock'] = 0;
            if ($db->fieldExists('transfer', 'production_wip')) $upd['transfer'] = (int)($exist['transfer'] ?? 0);
            if ($hasUpdatedAt) $upd['updated_at'] = $now;

            $db->table('production_wip')->where('id', (int)$exist['id'])->update($upd);
            return;
        }

        $insQty = max(0, $deltaQty);
        if ($insQty <= 0) return;

        $ins = $key + [
            'qty_in' => $insQty,
            'status' => 'WAITING',
        ];
        if ($hasQty) $ins['qty'] = $insQty;
        if ($hasQtyOut) $ins['qty_out'] = 0;
        if ($hasStock) $ins['stock'] = 0;
        if ($db->fieldExists('transfer', 'production_wip')) $ins['transfer'] = 0;
        if ($hasCreatedAt) $ins['created_at'] = $now;
        if ($hasUpdatedAt) $ins['updated_at'] = $now;

        $db->table('production_wip')->insert($ins);
    }

    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
    {
        $db = db_connect();

        $date = trim((string)$this->request->getGet('date'));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.id schedule_id, ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Leak Test')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        $actualMap = [];
        if ($db->tableExists('machining_leak_test_hourly')) {
            $actuals = $db->table('machining_leak_test_hourly')
                ->select('shift_id, machine_id, product_id, SUM(qty_ok) act, SUM(qty_ng) ng')
                ->where('production_date', $date)
                ->groupBy('shift_id, machine_id, product_id')
                ->get()->getResultArray();

            foreach ($actuals as $a) {
                $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
            }
        }

        return view('machining/leak_test_schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /* =====================================================
     * ✅ AJAX Select2: product + target + stock_prev
     * FIX: jangan pakai p.is_active jika kolom tidak ada
     * Return: { results: [...] }
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)($this->request->getGet('shift_id') ?? 0);
        $date    = (string)($this->request->getGet('date') ?? date('Y-m-d'));

        // select2 default 'term'
        $term = trim((string)($this->request->getGet('term') ?? ''));
        $q    = trim((string)($this->request->getGet('q') ?? ''));
        $search = ($term !== '') ? $term : $q;

        if ($shiftId <= 0) {
            return $this->response->setJSON(['results' => []]);
        }

        try {
            $processIdLT = $this->getProcessIdLeakTest($db);
            $totalSecond = $this->getTotalSecondShift($db, $shiftId); // boleh 0

            $builder = $db->table('product_process_flows ppf')
                ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
                ->join('products p', 'p.id = ppf.product_id', 'inner')
                ->where('ppf.process_id', $processIdLT);

            // filter is_active hanya jika kolom ada
            if ($db->fieldExists('is_active', 'product_process_flows')) {
                $builder->where('ppf.is_active', 1);
            }
            if ($db->fieldExists('is_active', 'products')) {
                $builder->where('p.is_active', 1);
            }

            if ($search !== '') {
                $builder->groupStart()
                    ->like('p.part_no', $search)
                    ->orLike('p.part_name', $search)
                    ->groupEnd();
            }

            $products = $builder
                ->groupBy('p.id')
                ->orderBy('p.part_no', 'ASC')
                ->limit(50)
                ->get()->getResultArray();

            $results = [];
            foreach ($products as $p) {
                $pid    = (int)($p['id'] ?? 0);
                $cycle  = (int)($p['cycle_time'] ?? 0);
                $cavity = (int)($p['cavity'] ?? 0);

                $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                $targetShift = 0;
                $targetHour  = 0;

                if ($cycle > 0 && $cavity > 0) {
                    if ($totalSecond > 0) {
                        $targetShift = (int)floor(($totalSecond / $cycle) * $cavity * $eff);
                    }
                    $targetHour = (int)floor((3600 / $cycle) * $cavity * $eff);
                }

                $prevId = $this->resolvePrevProcessByFlow($db, $pid, $processIdLT);
                $stockPrev = $prevId ? $this->getPrevProcessStock($db, $date, $pid, (int)$prevId) : 0;

                $results[] = [
                    'id' => $pid,
                    'text' => trim((string)($p['part_no'] ?? '').' - '.(string)($p['part_name'] ?? '')),
                    'cycle_time_used'  => $cycle,
                    'target_per_shift' => min($targetShift, 1200),
                    'target_per_hour'  => (int)$targetHour,
                    'prev_process_id'  => (int)($prevId ?? 0),
                    'stock_prev'       => (int)$stockPrev,
                ];
            }

            return $this->response->setJSON(['results' => $results]);

        } catch (\Throwable $e) {
            log_message('error', 'LeakTest getProductAndTarget error: ' . $e->getMessage());
            return $this->response->setJSON(['results' => []]);
        }
    }

    /* =====================================================
     * STORE (punya kamu, tidak diubah)
     * ===================================================== */
    public function store()
    {
        // pakai store kamu yang terakhir (yang delta reserve/transfer/incoming),
        // tidak aku utak-atik karena fokus perbaikan ada di produk tidak tampil.
        return redirect()->back()->with('error', 'Tempelkan isi store kamu yang terakhir di sini (tidak perlu diubah).');
    }
}
