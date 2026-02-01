<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionController extends BaseController
{
    /* =========================
     * INDEX (biarkan punyamu, tidak wajib diubah)
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

        $wipHasQtyIn  = $db->tableExists('production_wip') ? $db->fieldExists('qty_in', 'production_wip') : false;
        $wipHasQtyOut = $db->tableExists('production_wip') ? $db->fieldExists('qty_out', 'production_wip') : false;
        $wipHasStock  = $db->tableExists('production_wip') ? $db->fieldExists('stock', 'production_wip') : false;

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

            // WIP MAP (0->DC)
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
     * STORE
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
                ) continue;

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
            $isAdmin = $this->isAdminSession();

            foreach (array_keys($shiftIds) as $sid) {
                $this->syncDailyScheduleActual($db, $date, (int)$sid, $isAdmin);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily production tersimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * SAVE SLOT
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

            $this->syncDailyScheduleActual($db, (string)$data['date'], (int)$data['shift_id'], $this->isAdminSession());

            if ($db->transStatus() === false) throw new \Exception('DB error');

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
     * FINISH SHIFT 3:
     * FIX: proses SEMUA SHIFT DC (shift1+shift2+shift3) untuk tanggal tsb,
     * supaya stock DC agregasi benar-benar pindah ke flow berikutnya.
     * ===================================================== */
    public function finishShift()
    {
        $db      = db_connect();
        $date    = (string)$this->request->getPost('date');
        $shiftId = (int)$this->request->getPost('shift_id');

        if ($date === '' || $shiftId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        $isAdmin = $this->isAdminSession();

        // tetap wajib shift DC
        if (!$this->isDcShift($db, $shiftId)) {
            return $this->response->setJSON(['status' => false, 'message' => 'Finish Shift hanya boleh untuk shift Die Casting']);
        }

        // non-admin: tetap wajib shift 3 + shift ended
        if (!$isAdmin) {
            if (!$this->isShift3Dc($db, $shiftId)) {
                return $this->response->setJSON(['status' => false, 'message' => 'Finish Shift hanya untuk Shift 3 Die Casting']);
            }
            if (!$this->isShiftEnded($db, $shiftId, $date)) {
                return $this->response->setJSON(['status' => false, 'message' => 'Shift 3 belum berakhir, tombol belum boleh digunakan']);
            }
        }

        $db->transBegin();

        try {
            $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
            if ($dcProcessId <= 0) throw new \Exception('Process Die Casting tidak ditemukan');

            // 1) sync ulang realtime untuk SEMUA shift DC di tanggal tsb
            $dcShiftIds = $this->getDcShiftIds($db);
            foreach ($dcShiftIds as $sid) {
                // admin boleh force unlock untuk testing
                $this->syncDailyScheduleActual($db, $date, (int)$sid, $isAdmin);
            }

            // 2) transfer flow untuk SEMUA shift DC (bukan cuma shift 3)
            $count = $this->finishShiftTransferFlowAllDc($db, $date, $dcProcessId, $dcShiftIds, $isAdmin);

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return $this->response->setJSON([
                'status'  => true,
                'message' => "Finish Shift OK. Transferred rows: {$count}",
                'count'   => $count
            ]);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /* =====================================================
     * TRANSFER ALL DC:
     * A) DC stage (0->DC): qty_out = stock, stock=0, status DONE
     * B) NEXT stage (DC->NEXT): qty_in=qty_out, stock=qty_out, WAITING
     * ===================================================== */
    private function finishShiftTransferFlowAllDc($db, string $date, int $dcProcessId, array $dcShiftIds, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;

        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        // ambil semua DCP untuk tanggal tsb dan SEMUA shift DC
        $dcpRows = $db->table('die_casting_production')
            ->select('id, product_id, qty_p, qty_a, shift_id')
            ->where('production_date', $date)
            ->whereIn('shift_id', $dcShiftIds)
            ->where('product_id >', 0)
            ->get()->getResultArray();

        if (!$dcpRows) return 0;

        $processed = 0;

        foreach ($dcpRows as $dcp) {
            $sourceId  = (int)$dcp['id'];
            $productId = (int)$dcp['product_id'];
            $qtyPlan   = (int)($dcp['qty_p'] ?? 0);
            $qtyA      = (int)($dcp['qty_a'] ?? 0);

            if ($sourceId <= 0 || $productId <= 0) continue;

            // next process
            $nextProcessId = $this->resolveNextProcessByFlow($db, $productId, $dcProcessId) ?? 0;

            // cari WIP DC stage (0->DC) by source
            $whereA = [
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => 0,
                'to_process_id'   => $dcProcessId,
                'source_table'    => 'die_casting_production',
                'source_id'       => $sourceId,
            ];

            $existA = $db->table('production_wip')->where($whereA)->get()->getRowArray();

            // kalau belum ada, buat minimal (harusnya sudah ada dari sync)
            if (!$existA) {
                $remain = max(0, $qtyPlan - $qtyA);
                $insA = $whereA + ['qty' => $qtyPlan, 'status' => 'WAITING'];
                if ($hasQtyIn)  $insA['qty_in']  = $remain;
                if ($hasQtyOut) $insA['qty_out'] = 0;
                if ($hasStock)  $insA['stock']   = $qtyA;
                if ($hasCreatedAt) $insA['created_at'] = $now;
                if ($hasUpdatedAt) $insA['updated_at'] = $now;
                $db->table('production_wip')->insert($insA);
                $existA = $db->table('production_wip')->where($whereA)->get()->getRowArray();
            }

            // penting: transfer dari STOCK (kalau stock kosong fallback ke qty_a)
            $stockNow    = $hasStock ? (int)($existA['stock'] ?? 0) : 0;
            $transferQty = max($stockNow, $qtyA);

            if ($transferQty <= 0) {
                $processed++;
                continue;
            }

            // update DC stage -> DONE
            $remainIn = max(0, $qtyPlan - $transferQty);

            $updA = [
                'qty'    => $qtyPlan > 0 ? $qtyPlan : $transferQty,
                'status' => 'DONE',
            ];
            if ($hasQtyIn)  $updA['qty_in']  = $remainIn;
            if ($hasQtyOut) $updA['qty_out'] = $transferQty;
            if ($hasStock)  $updA['stock']   = 0; // <-- stock habis dipindah
            if ($hasUpdatedAt) $updA['updated_at'] = $now;

            // kalau sudah DONE dan non-admin: skip (biar tidak double transfer)
            if (!$forceAdmin && $existA && strtoupper((string)($existA['status'] ?? '')) === 'DONE') {
                $processed++;
                continue;
            }

            $db->table('production_wip')->where('id', (int)$existA['id'])->update($updA);

            // buat/update flow next
// buat/update flow next
        if ($nextProcessId > 0) {
            $whereB = [
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => $dcProcessId,
                'to_process_id'   => $nextProcessId,
                'source_table'    => 'die_casting_production',
                'source_id'       => $sourceId,
            ];

            $existB = $db->table('production_wip')->where($whereB)->get()->getRowArray();

            $payloadB = $whereB + [
                'qty'    => $transferQty,
                'status' => 'WAITING',
            ];

            if ($hasQtyIn)  $payloadB['qty_in']  = $transferQty;

            // ✅ FIX INI: agar kolom OUT Die Casting terisi pada report
            if ($hasQtyOut) $payloadB['qty_out'] = $transferQty;

            if ($hasStock)  $payloadB['stock']   = 0;
            if ($hasUpdatedAt) $payloadB['updated_at'] = $now;

            if ($existB) {
                // jangan timpa DONE kecuali admin test
                if ($forceAdmin || strtoupper((string)($existB['status'] ?? '')) !== 'DONE') {
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

    /* =====================================================
     * SYNC hourly -> dcp + WIP realtime
     * - qty_in = max(0, plan - totalFG)
     * - stock  = totalFG
     * - qty_out = 0 sebelum finish
     * ===================================================== */
    private function syncDailyScheduleActual($db, string $date, int $shiftId, bool $forceAdmin = false): void
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

            // update dcp
            $db->table('die_casting_production')
                ->where('id', $dcpId)
                ->update([
                    'qty_a'  => $fg,
                    'qty_ng' => $ng
                ]);

            // upsert WIP realtime
            $this->upsertWipDcStageRealtime($db, $date, $dcpId, $productId, $qtyPlan, $fg, $dcProcessId, $forceAdmin);
        }
    }

    private function upsertWipDcStageRealtime(
        $db,
        string $date,
        int $sourceId,
        int $productId,
        int $qtyPlan,
        int $totalFg,
        int $dcProcessId,
        bool $forceAdmin = false
    ): void {
        if (!$db->tableExists('production_wip')) return;

        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
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

        // non-admin lock DONE
        if ($exist && strtoupper((string)($exist['status'] ?? '')) === 'DONE' && !$forceAdmin) {
            return;
        }

        $remaining = max(0, $qtyPlan - $totalFg);

        $payload = $where + [
            'qty'    => $qtyPlan,
            'status' => 'WAITING',
        ];

        if ($hasQtyIn)  $payload['qty_in']  = $remaining;
        if ($hasQtyOut) $payload['qty_out'] = 0;
        if ($hasStock)  $payload['stock']   = $totalFg;
        if ($hasUpdatedAt) $payload['updated_at'] = $now;

        if ($exist) {
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($hasCreatedAt) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /* =========================
     * Helpers
     * ========================= */

    private function getDcShiftIds($db): array
    {
        $rows = $db->table('shifts')
            ->select('id')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        return array_values(array_map(fn($r) => (int)$r['id'], $rows));
    }

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

    private function isDcShift($db, int $shiftId): bool
    {
        $shift = $db->table('shifts')
            ->select('shift_name, is_active')
            ->where('id', $shiftId)
            ->get()->getRowArray();

        if (!$shift) return false;
        if ((int)($shift['is_active'] ?? 0) !== 1) return false;

        return (stripos((string)($shift['shift_name'] ?? ''), 'DC') !== false);
    }

    private function isAdminSession(): bool
    {
        $role = strtoupper((string)(session()->get('role') ?? ''));
        return $role === 'ADMIN';
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

    private function getProcessIdByName($db, string $processName): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', $processName)
            ->get()->getRowArray();

        return (int)($row['id'] ?? 0);
    }
}
