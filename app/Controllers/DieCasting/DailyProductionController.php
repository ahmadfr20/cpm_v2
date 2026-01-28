<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionController extends BaseController
{
    /* =========================
     * INDEX
     * ========================= */
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        // SHIFT DC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // NG category
        $ngCategories = $db->table('ng_categories')
            ->where('process_name', 'Die Casting')
            ->orderBy('ng_code')
            ->get()->getResultArray();

        $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');

        // optional columns production_wip
        $wipHasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $wipHasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $wipHasStock  = $db->fieldExists('stock', 'production_wip');

        foreach ($shifts as &$shift) {
            // slots
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()->getResultArray();

            // total minute
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $slot['minute'] = ($end - $start) / 60;
                $totalMinute += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            // items (plan)
            $shift['items'] = $db->table('die_casting_production dcp')
                ->select('
                    dcp.id AS dcp_id,
                    dcp.machine_id,
                    m.machine_code,
                    dcp.product_id,
                    p.part_no,
                    COALESCE(dcp.part_label, p.part_name) AS part_name,
                    dcp.qty_p
                ')
                ->join('machines m', 'm.id = dcp.machine_id')
                ->join('products p', 'p.id = dcp.product_id')
                ->where('dcp.production_date', $date)
                ->where('dcp.shift_id', $shift['id'])
                ->where('dcp.qty_p >', 0)
                ->orderBy('m.line_position')
                ->get()->getResultArray();

            // hourly map
            $hourly = $db->table('die_casting_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map'][(int)$h['machine_id']][(int)$h['product_id']][(int)$h['time_slot_id']] = $h;
            }

            // build next process map (flow)
            $productIds = array_values(array_unique(array_map(fn($x) => (int)$x['product_id'], $shift['items'])));
            if (!$productIds) {
                $shift['next_process_map'] = [];
                $shift['wip_map'] = [];
                $shift['actual_totals'] = [];
                continue;
            }

            $flows = $db->table('product_process_flows ppf')
                ->select('ppf.product_id, ppf.process_id, ppf.sequence, pp.process_name')
                ->join('production_processes pp', 'pp.id = ppf.process_id')
                ->whereIn('ppf.product_id', $productIds)
                ->where('ppf.is_active', 1)
                ->orderBy('ppf.product_id', 'ASC')
                ->orderBy('ppf.sequence', 'ASC')
                ->get()->getResultArray();

            $flowByProduct = [];
            foreach ($flows as $f) {
                $pid = (int)$f['product_id'];
                $flowByProduct[$pid][] = [
                    'process_id'   => (int)$f['process_id'],
                    'process_name' => (string)$f['process_name'],
                    'sequence'     => (int)$f['sequence'],
                ];
            }

            $shift['next_process_map'] = [];
            foreach ($productIds as $pid) {
                $seq = $flowByProduct[$pid] ?? [];
                if (!$seq) {
                    $shift['next_process_map'][$pid] = null;
                    continue;
                }

                $idx = null;
                foreach ($seq as $i => $row) {
                    if ((int)$row['process_id'] === (int)$dcProcessId) { $idx = $i; break; }
                }

                if ($idx === null) {
                    $shift['next_process_map'][$pid] = isset($seq[1])
                        ? ['process_id' => $seq[1]['process_id'], 'process_name' => $seq[1]['process_name']]
                        : null;
                } else {
                    $shift['next_process_map'][$pid] = isset($seq[$idx + 1])
                        ? ['process_id' => $seq[$idx + 1]['process_id'], 'process_name' => $seq[$idx + 1]['process_name']]
                        : null;
                }
            }

            // actual totals (display)
            $actualRows = $db->table('die_casting_hourly')
                ->select('machine_id, product_id, SUM(qty_fg) AS total_fg')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->groupBy('machine_id, product_id')
                ->get()->getResultArray();

            $shift['actual_totals'] = [];
            foreach ($actualRows as $r) {
                $shift['actual_totals'][(int)$r['machine_id']][(int)$r['product_id']] = (int)$r['total_fg'];
            }

            /**
             * WIP MAP untuk tampilan hourly:
             * Ambil WIP DC stage: from=0 -> to=DC (bukan DC->next)
             */
            $shift['wip_map'] = [];
            if ($dcProcessId > 0 && $db->tableExists('production_wip')) {
                $select = 'dcp.machine_id, pw.product_id, pw.qty, pw.status';
                if ($wipHasQtyIn)  $select .= ', pw.qty_in';
                if ($wipHasQtyOut) $select .= ', pw.qty_out';
                if ($wipHasStock)  $select .= ', pw.stock';

                $wipRows = $db->table('production_wip pw')
                    ->select($select)
                    ->join('die_casting_production dcp', 'dcp.id = pw.source_id', 'left')
                    ->where('pw.source_table', 'die_casting_production')
                    ->where('pw.production_date', $date)
                    ->where('pw.from_process_id', 0)
                    ->where('pw.to_process_id', $dcProcessId)
                    ->where('dcp.shift_id', $shift['id'])
                    ->get()->getResultArray();

                foreach ($wipRows as $w) {
                    $mid = (int)($w['machine_id'] ?? 0);
                    $pid = (int)($w['product_id'] ?? 0);
                    if (!$mid || !$pid) continue;

                    $shift['wip_map'][$mid][$pid] = [
                        'qty'     => (int)($w['qty'] ?? 0),
                        'status'  => (string)($w['status'] ?? 'WAITING'),
                        'qty_in'  => $wipHasQtyIn  ? (int)($w['qty_in'] ?? 0) : null,
                        'qty_out' => $wipHasQtyOut ? (int)($w['qty_out'] ?? 0) : null,
                        'stock'   => $wipHasStock  ? (int)($w['stock'] ?? 0) : null,
                    ];
                }
            }
        }
        unset($shift);

        return view('die_casting/daily_production/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories
        ]);
    }

    /* =========================
     * STORE (bulk save via submit)
     * ========================= */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data kosong / terpotong');
        }

        $db->transBegin();

        try {
            $shiftIds = [];

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

                $shiftIds[(int)$row['shift_id']] = true;

                $db->table('die_casting_hourly')->replace([
                    'production_date' => (string)$row['date'],
                    'shift_id'        => (int)$row['shift_id'],
                    'machine_id'      => (int)$row['machine_id'],
                    'product_id'      => (int)$row['product_id'],
                    'time_slot_id'    => (int)$row['time_slot_id'],
                    'qty_fg'          => (int)($row['fg'] ?? 0),
                    'qty_ng'          => (int)($row['ng'] ?? 0),
                    'ng_category_id'  => $row['ng_category_id'] ?? null,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }

            $first = reset($items);
            $date  = (string)($first['date'] ?? '');

            foreach (array_keys($shiftIds) as $sid) {
                $this->syncDailyScheduleActual($db, $date, (int)$sid); // <-- ini akan update WIP stock DC
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily production tersimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * SAVE SLOT (ajax per cell)
     * ========================= */
    public function saveSlot()
    {
        $db = db_connect();
        $data = $this->request->getPost();

        if (
            empty($data['date']) ||
            empty($data['shift_id']) ||
            empty($data['machine_id']) ||
            empty($data['product_id']) ||
            empty($data['time_slot_id'])
        ) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Data tidak lengkap'
            ]);
        }

        $db->transBegin();

        try {
            $db->table('die_casting_hourly')->replace([
                'production_date' => (string)$data['date'],
                'shift_id'        => (int)$data['shift_id'],
                'machine_id'      => (int)$data['machine_id'],
                'product_id'      => (int)$data['product_id'],
                'time_slot_id'    => (int)$data['time_slot_id'],
                'qty_fg'          => (int)($data['fg'] ?? 0),
                'qty_ng'          => (int)($data['ng'] ?? 0),
                'ng_category_id'  => $data['ng_category_id'] ?? null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            // sync actual + update WIP DC stock
            $this->syncDailyScheduleActual($db, (string)$data['date'], (int)$data['shift_id']);

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return $this->response->setJSON(['status' => true]);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /* =====================================================
     * FINISH SHIFT (SHIFT 3 DC - hanya setelah shift berakhir)
     *
     * Alur:
     * 1) Sync qty_a / qty_ng (hourly -> dcp) + update stock WIP DC
     * 2) DC stage WIP (0->DC) : qty_out = qty_a, stock=0, status DONE
     * 3) Buat WIP transfer DC->NEXT : qty_in=qty_a, stock=qty_a, status WAITING
     * ===================================================== */
    public function finishShift()
    {
        $db      = db_connect();
        $date    = (string)$this->request->getPost('date');
        $shiftId = (int)$this->request->getPost('shift_id');

        if ($date === '' || $shiftId <= 0) {
            return $this->response->setJSON([
                'status'  => false,
                'message' => 'Data tidak lengkap'
            ]);
        }

        if (!$this->isShift3Dc($db, $shiftId)) {
            return $this->response->setJSON([
                'status'  => false,
                'message' => 'Finish Shift hanya untuk Shift 3 Die Casting'
            ]);
        }

        if (!$this->isShiftEnded($db, $shiftId, $date)) {
            return $this->response->setJSON([
                'status'  => false,
                'message' => 'Shift 3 belum berakhir, tombol belum boleh digunakan'
            ]);
        }

        $db->transBegin();

        try {
            $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
            if ($dcProcessId <= 0) {
                throw new \Exception('Process Die Casting tidak ditemukan');
            }

            // 1) sync actual (ini juga update stock WIP DC)
            $this->syncDailyScheduleActual($db, $date, $shiftId);

            // 2) transfer flow
            $count = $this->finishShift3TransferFlow($db, $date, $shiftId, $dcProcessId);

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return $this->response->setJSON([
                'status'  => true,
                'message' => 'Finish Shift OK: DC OUT terisi qty A, stock DC 0, dan IN proses next terisi qty A.',
                'count'   => $count
            ]);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /* =========================
     * CORE TRANSFER FLOW (finish shift 3)
     * ========================= */
    private function finishShift3TransferFlow($db, string $date, int $shiftId, int $dcProcessId): int
    {
        if (!$db->tableExists('production_wip')) return 0;

        // optional columns
        $hasQtyIn    = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut   = $db->fieldExists('qty_out', 'production_wip');
        $hasStock    = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        // ambil dcp shift 3
        $dcpRows = $db->table('die_casting_production')
            ->select('id, product_id, qty_p, qty_a')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->where('product_id >', 0)
            ->get()->getResultArray();

        if (!$dcpRows) return 0;

        $processed = 0;

        foreach ($dcpRows as $dcp) {
            $sourceId  = (int)$dcp['id'];
            $productId = (int)$dcp['product_id'];
            $qtyPlan   = (int)($dcp['qty_p'] ?? 0);
            $qtyActual = (int)($dcp['qty_a'] ?? 0);

            if ($sourceId <= 0 || $productId <= 0) continue;

            // next process setelah DC (baru dibuat saat finish shift)
            $nextProcessId = $this->resolveNextProcessByFlow($db, $productId, $dcProcessId);
            if (!$nextProcessId) {
                // kalau tidak ada next, tetap tutup DC stage saja
                $nextProcessId = 0;
            }

            /**
             * A) Update WIP DC stage (0 -> DC) jadi DONE
             *    - qty_out = qtyActual
             *    - stock   = 0
             *    - qty_in  tetap qtyPlan (plan)
             */
            $whereA = [
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => 0,
                'to_process_id'   => $dcProcessId,
                'source_table'    => 'die_casting_production',
                'source_id'       => $sourceId,
            ];

            $existA = $db->table('production_wip')->where($whereA)->get()->getRowArray();

            $payloadA = $whereA + [
                'qty'    => $qtyPlan > 0 ? $qtyPlan : $qtyActual,
                'status' => 'DONE',
            ];
            if ($hasQtyIn)  $payloadA['qty_in']  = $qtyPlan;
            if ($hasQtyOut) $payloadA['qty_out'] = $qtyActual;
            if ($hasStock)  $payloadA['stock']   = 0;
            if ($hasUpdatedAt) $payloadA['updated_at'] = $now;

            if ($existA) {
                $db->table('production_wip')->where('id', (int)$existA['id'])->update($payloadA);
            } else {
                if ($hasCreatedAt) $payloadA['created_at'] = $now;
                $db->table('production_wip')->insert($payloadA);
            }

            /**
             * B) Create/Update WIP transfer DC -> NEXT
             *    HANYA dibuat saat finish shift.
             *    - qty_in = qtyActual
             *    - stock  = qtyActual
             *    - qty_out= 0
             */
            if ($nextProcessId > 0 && $qtyActual > 0) {
                $whereB = [
                    'production_date' => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $dcProcessId,
                    'to_process_id'   => $nextProcessId,
                    // tetap trace dari sumber DC
                    'source_table'    => 'die_casting_production',
                    'source_id'       => $sourceId,
                ];

                $existB = $db->table('production_wip')->where($whereB)->get()->getRowArray();

                $payloadB = $whereB + [
                    'qty'    => $qtyActual,
                    'status' => 'WAITING',
                ];
                if ($hasQtyIn)  $payloadB['qty_in']  = $qtyActual;
                if ($hasQtyOut) $payloadB['qty_out'] = 0;
                if ($hasStock)  $payloadB['stock']   = $qtyActual;
                if ($hasUpdatedAt) $payloadB['updated_at'] = $now;

                if ($existB) {
                    // kalau sudah DONE jangan overwrite
                    if (($existB['status'] ?? '') !== 'DONE') {
                        $db->table('production_wip')->where('id', (int)$existB['id'])->update($payloadB);
                    }
                } else {
                    if ($hasCreatedAt) $payloadB['created_at'] = $now;
                    $db->table('production_wip')->insert($payloadB);
                }
            }

            $processed++;
        }

        return $processed;
    }

    /* =========================
     * Sync actual: hourly -> die_casting_production
     * + update STOCK di production_wip untuk DC stage (0->DC)
     * ========================= */
    private function syncDailyScheduleActual($db, string $date, int $shiftId): void
    {
        $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
        if ($dcProcessId <= 0) return;

        $dcpRows = $db->table('die_casting_production')
            ->select('id, machine_id, product_id, qty_p')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->get()->getResultArray();

        if (!$dcpRows) return;

        foreach ($dcpRows as $dcp) {
            $dcpId     = (int)$dcp['id'];
            $machineId = (int)$dcp['machine_id'];
            $productId = (int)$dcp['product_id'];
            $qtyPlan   = (int)($dcp['qty_p'] ?? 0);

            if ($dcpId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $sum = $db->table('die_casting_hourly')
                ->select('SUM(qty_fg) AS total_fg, SUM(qty_ng) AS total_ng')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->where('machine_id', $machineId)
                ->where('product_id', $productId)
                ->get()->getRowArray();

            $fg = (int)($sum['total_fg'] ?? 0);
            $ng = (int)($sum['total_ng'] ?? 0);

            // update dcp actual
            $db->table('die_casting_production')
                ->where('id', $dcpId)
                ->update([
                    'qty_a'  => $fg,
                    'qty_ng' => $ng
                ]);

            // update/create WIP DC stage stock = qty_a (fg)
            $this->upsertWipDcStage($db, $date, $dcpId, $productId, $qtyPlan, $fg, $dcProcessId);
        }
    }

    /**
     * WIP DC stage (0 -> DC):
     * - qty_in = qty plan
     * - stock  = qty actual (fg) dari hourly
     * - qty_out tetap 0 sebelum finish
     */
    private function upsertWipDcStage($db, string $date, int $sourceId, int $productId, int $qtyPlan, int $qtyActual, int $dcProcessId): void
    {
        if (!$db->tableExists('production_wip')) return;

        $hasQtyIn    = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut   = $db->fieldExists('qty_out', 'production_wip');
        $hasStock    = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');

        $where = [
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => 0,
            'to_process_id'   => $dcProcessId,
            'source_table'    => 'die_casting_production',
            'source_id'       => $sourceId,
        ];

        $exist = $db->table('production_wip')->where($where)->get()->getRowArray();

        $now = date('Y-m-d H:i:s');

        // kalau sudah DONE jangan diubah (finish shift sudah mengunci)
        if ($exist && strtoupper((string)($exist['status'] ?? '')) === 'DONE') {
            return;
        }

        $payload = $where + [
            'qty'    => $qtyPlan,
            'status' => 'WAITING', // atau IN_PROGRESS, tapi konsisten saja
        ];

        if ($hasQtyIn)  $payload['qty_in']  = $qtyPlan;
        if ($hasQtyOut) $payload['qty_out'] = 0;
        if ($hasStock)  $payload['stock']   = $qtyActual; // <-- ini yang kamu butuhkan (stock dari qty A)
        if ($hasUpdatedAt) $payload['updated_at'] = $now;

        if ($exist) {
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($hasCreatedAt) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /* =========================
     * SHIFT 3 DC?
     * ========================= */
    private function isShift3Dc($db, int $shiftId): bool
    {
        $shift = $db->table('shifts')
            ->select('shift_code, shift_name, is_active')
            ->where('id', $shiftId)
            ->get()->getRowArray();

        if (!$shift) return false;
        if ((int)($shift['is_active'] ?? 0) !== 1) return false;

        $isShift3 = ((int)($shift['shift_code'] ?? 0) === 3);
        $isDC     = (stripos((string)($shift['shift_name'] ?? ''), 'DC') !== false);

        return $isShift3 && $isDC;
    }

    /* =========================
     * SHIFT ended?
     * ========================= */
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

        if ($endDT <= $startDT) {
            $endDT += 86400; // lewat tengah malam
        }

        return time() >= $endDT;
    }

    /* =========================
     * Resolve next process by flow
     * ========================= */
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

        // fallback: ambil proses kedua jika from tidak ketemu
        if ($idx === null) {
            return isset($flows[1]) ? (int)$flows[1]['process_id'] : null;
        }

        return isset($flows[$idx + 1]) ? (int)$flows[$idx + 1]['process_id'] : null;
    }

    /* =========================
     * Helper: get process id by name
     * ========================= */
    private function getProcessIdByName($db, string $processName): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', $processName)
            ->get()->getRowArray();

        return (int)($row['id'] ?? 0);
    }
}
