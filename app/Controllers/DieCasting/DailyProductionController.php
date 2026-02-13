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
        $isAdmin  = $this->isAdminSession();

        // SHIFT DC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // NG category (Die Casting)
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

            $shift['shift_start'] = $shift['slots'][0]['time_start'] ?? null;
            $lastSlot = !empty($shift['slots']) ? $shift['slots'][count($shift['slots']) - 1] : null;
            $shift['shift_end']   = $lastSlot['time_end'] ?? null;

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
                    p.part_prod,
                    p.part_name,
                    dcp.qty_p
                ')
                ->join('machines m', 'm.id = dcp.machine_id')
                ->join('products p', 'p.id = dcp.product_id')
                ->where('dcp.production_date', $date)
                ->where('dcp.shift_id', $shift['id'])
                ->where('dcp.qty_p >', 0)
                ->orderBy('m.line_position')
                ->get()->getResultArray();

            // hourly map + hourly ids
            $hourly = $db->table('die_casting_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['hourly_map'] = [];
            $hourlyIds = [];
            foreach ($hourly as $h) {
                $mid = (int)$h['machine_id'];
                $pid = (int)$h['product_id'];
                $ts  = (int)$h['time_slot_id'];
                $shift['hourly_map'][$mid][$pid][$ts] = $h;
                $hourlyIds[] = (int)$h['id'];
            }

            // NG detail map
            $shift['ng_detail_map'] = [];
            if ($db->tableExists('die_casting_hourly_ng_details') && !empty($hourlyIds)) {
                $details = $db->table('die_casting_hourly_ng_details d')
                    ->select('d.hourly_id, d.ng_category_id, d.qty, nc.ng_code, nc.ng_name')
                    ->join('ng_categories nc', 'nc.id = d.ng_category_id', 'inner')
                    ->whereIn('d.hourly_id', $hourlyIds)
                    ->orderBy('nc.ng_code', 'ASC')
                    ->get()->getResultArray();

                $hourlyIndex = [];
                foreach ($hourly as $h) {
                    $hourlyIndex[(int)$h['id']] = [
                        'machine_id' => (int)$h['machine_id'],
                        'product_id' => (int)$h['product_id'],
                        'time_slot_id' => (int)$h['time_slot_id'],
                    ];
                }

                foreach ($details as $d) {
                    $hid = (int)$d['hourly_id'];
                    if (!isset($hourlyIndex[$hid])) continue;

                    $mid = $hourlyIndex[$hid]['machine_id'];
                    $pid = $hourlyIndex[$hid]['product_id'];
                    $ts  = $hourlyIndex[$hid]['time_slot_id'];

                    $shift['ng_detail_map'][$mid][$pid][$ts][] = [
                        'ng_category_id' => (int)$d['ng_category_id'],
                        'qty' => (int)$d['qty'],
                        'ng_code' => (int)$d['ng_code'],
                        'ng_name' => (string)$d['ng_name'],
                    ];
                }
            }

            // WIP MAP (0->DC) => buat report WIP
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

            // tombol finish (admin always true)
            $shift['finish_allowed'] = $isAdmin ? true : $this->isNearLastSlotEnd($db, (int)$shift['id'], $date, 15);
        }
        unset($shift);

        return view('die_casting/daily_production/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories,
            'isAdmin'      => $isAdmin,
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

                $exist = $db->table('die_casting_hourly')
                    ->where('production_date', (string)$row['date'])
                    ->where('shift_id', (int)$row['shift_id'])
                    ->where('machine_id', (int)$row['machine_id'])
                    ->where('product_id', (int)$row['product_id'])
                    ->where('time_slot_id', (int)$row['time_slot_id'])
                    ->get()->getRowArray();

                if ($exist) {
                    $hourlyId = (int)$exist['id'];
                    $db->table('die_casting_hourly')->where('id', $hourlyId)->update([
                        'qty_fg'         => (int)($row['fg'] ?? 0),
                        'qty_ng'         => (int)($row['ng'] ?? 0),
                        'ng_category_id' => null,
                        'updated_at'     => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $db->table('die_casting_hourly')->insert([
                        'production_date' => (string)$row['date'],
                        'shift_id'        => (int)$row['shift_id'],
                        'machine_id'      => (int)$row['machine_id'],
                        'product_id'      => (int)$row['product_id'],
                        'time_slot_id'    => (int)$row['time_slot_id'],
                        'qty_fg'          => (int)($row['fg'] ?? 0),
                        'qty_ng'          => (int)($row['ng'] ?? 0),
                        'ng_category_id'  => null,
                        'created_at'      => date('Y-m-d H:i:s'),
                    ]);
                    $hourlyId = (int)$db->insertID();
                }

                $this->saveNgDetails($db, $hourlyId, $row['ng_details'] ?? []);
                $sumNg = $this->sumNgDetail($db, $hourlyId);
                $db->table('die_casting_hourly')->where('id', $hourlyId)->update(['qty_ng' => $sumNg]);
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

    /* =====================================================
     * FINISH SHIFT (1/2/3)
     * SHIFT 1/2: pindah stock ke shift berikutnya + ZERO semua nilai shift tsb
     * SHIFT 3: transfer ke proses berikutnya (qty_out) + ZERO shift 3
     * ===================================================== */
    public function finishShift()
    {
        $db      = db_connect();
        $date    = (string)$this->request->getPost('date');
        $shiftId = (int)$this->request->getPost('shift_id');

        if ($date === '' || $shiftId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        if (!$this->isDcShift($db, $shiftId)) {
            return $this->response->setJSON(['status' => false, 'message' => 'Finish Shift hanya untuk Die Casting']);
        }

        $isAdmin = $this->isAdminSession();

        if (!$isAdmin && !$this->isNearLastSlotEnd($db, $shiftId, $date, 15)) {
            return $this->response->setJSON(['status' => false, 'message' => 'Tombol aktif hanya mendekati jam terakhir shift']);
        }

        $db->transBegin();
        try {
            $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
            if ($dcProcessId <= 0) throw new \Exception('Process Die Casting tidak ditemukan');

            // sync shift ini dulu (ambil FG terbaru)
            $this->syncDailyScheduleActual($db, $date, $shiftId, $isAdmin);

            $shiftCode = (int)$db->table('shifts')->select('shift_code')->where('id', $shiftId)->get()->getRow('shift_code');

            // urutan shift DC
            $dcShiftIds = $this->getDcShiftIds($db);
            $idx = array_search($shiftId, $dcShiftIds, true);
            if ($idx === false) throw new \Exception('Shift DC tidak valid');

            if ($shiftCode === 1 || $shiftCode === 2) {
                $nextShiftId = (int)($dcShiftIds[$idx + 1] ?? 0);
                if (!$nextShiftId) throw new \Exception('Tidak ada shift berikutnya');

                // 1) pindahkan stock ke shift berikutnya (WIP 0->DC)
                $moved = $this->transferDcStockToNextDcShift($db, $date, $shiftId, $nextShiftId, $dcProcessId, $isAdmin);

                // 2) ZERO semua data shift yang selesai (ini yang bikin “hilang” dari hourly + wip + dcp)
                $this->zeroOutDcShift($db, $date, $shiftId, $dcProcessId);

                // 3) sync shift berikutnya supaya WIP qty_in menyesuaikan stock baru
                $this->syncDailyScheduleActual($db, $date, $nextShiftId, $isAdmin);

                // 4) mark completed
                $this->markDcShiftCompleted($db, $date, $shiftId);

                $db->transCommit();
                return $this->response->setJSON([
                    'status' => true,
                    'message' => "Finish Shift {$shiftCode} OK. Stock moved: {$moved} & shift cleared",
                    'count' => $moved
                ]);
            }

            if ($shiftCode === 3) {
                // sync semua shift DC biar qty_a benar
                foreach ($dcShiftIds as $sid) {
                    $this->syncDailyScheduleActual($db, $date, (int)$sid, $isAdmin);
                }

                // 1) transfer stock shift 3 ke proses berikutnya (qty_out)
                $count = $this->finishShiftTransferFlowShift3Only($db, $date, $dcProcessId, $shiftId, $isAdmin);

                // 2) ZERO shift 3 (hourly, dcp qty, wip stock)
                $this->zeroOutDcShift($db, $date, $shiftId, $dcProcessId);

                $this->markDcShiftCompleted($db, $date, $shiftId);

                $db->transCommit();
                return $this->response->setJSON([
                    'status' => true,
                    'message' => "Finish Shift 3 OK. Transferred: {$count} & shift cleared",
                    'count' => $count
                ]);
            }

            throw new \Exception('Shift DC tidak dikenali');

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /* =====================================================
     * ZERO OUT SHIFT DC (PENTING!)
     * - die_casting_hourly => qty_fg=0 qty_ng=0 (dan delete detail)
     * - die_casting_production => qty_a=0 qty_ng=0
     * - production_wip (0->DC untuk shift ini) => stock=0 qty_in=0 qty_out=0 status DONE
     * ===================================================== */
    private function zeroOutDcShift($db, string $date, int $shiftId, int $dcProcessId): void
    {
        $now = date('Y-m-d H:i:s');

        // 1) ambil hourly rows untuk shift ini
        $hourlyRows = $db->table('die_casting_hourly')
            ->select('id')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->get()->getResultArray();

        $hourlyIds = array_map(fn($r) => (int)$r['id'], $hourlyRows);

        // 2) delete ng details
        if (!empty($hourlyIds) && $db->tableExists('die_casting_hourly_ng_details')) {
            $db->table('die_casting_hourly_ng_details')->whereIn('hourly_id', $hourlyIds)->delete();
        }

        // 3) set hourly qty = 0 (jangan delete supaya form masih tampil tapi kosong)
        $db->table('die_casting_hourly')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->update([
                'qty_fg' => 0,
                'qty_ng' => 0,
                'ng_category_id' => null,
                'updated_at' => $now,
            ]);

        // 4) set DCP qty_a/qty_ng = 0
        $db->table('die_casting_production')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->update([
                'qty_a'  => 0,
                'qty_ng' => 0,
            ]);

        // 5) set WIP stage (0->DC) untuk DCP shift ini = 0
        if ($db->tableExists('production_wip')) {
            $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
            $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
            $hasStock  = $db->fieldExists('stock', 'production_wip');
            $hasUpd    = $db->fieldExists('updated_at', 'production_wip');

            // ambil semua dcp id shift ini
            $dcpIds = $db->table('die_casting_production')
                ->select('id')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->get()->getResultArray();

            $ids = array_map(fn($r) => (int)$r['id'], $dcpIds);

            if (!empty($ids)) {
                $upd = ['status' => 'DONE'];
                if ($hasQtyIn)  $upd['qty_in']  = 0;
                if ($hasQtyOut) $upd['qty_out'] = 0;
                if ($hasStock)  $upd['stock']   = 0;
                if ($hasUpd)    $upd['updated_at'] = $now;

                $db->table('production_wip')
                    ->where('production_date', $date)
                    ->where('from_process_id', 0)
                    ->where('to_process_id', $dcProcessId)
                    ->where('source_table', 'die_casting_production')
                    ->whereIn('source_id', $ids)
                    ->update($upd);
            }
        }
    }

    /* =====================================================
     * SHIFT 1/2: pindahkan stock ke shift berikutnya
     * ===================================================== */
    private function transferDcStockToNextDcShift($db, string $date, int $fromShiftId, int $toShiftId, int $dcProcessId, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;

        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');

        if (!$hasStock) return 0;

        $now = date('Y-m-d H:i:s');

        // DCP from shift
        $fromDcp = $db->table('die_casting_production')
            ->select('id, machine_id, product_id, qty_p')
            ->where('production_date', $date)
            ->where('shift_id', $fromShiftId)
            ->where('product_id >', 0)
            ->get()->getResultArray();
        if (!$fromDcp) return 0;

        // DCP to shift (mapping machine+product => dcp_id)
        $toDcpRows = $db->table('die_casting_production')
            ->select('id, machine_id, product_id, qty_p')
            ->where('production_date', $date)
            ->where('shift_id', $toShiftId)
            ->where('product_id >', 0)
            ->get()->getResultArray();

        $toMap = [];
        foreach ($toDcpRows as $t) {
            $toMap[(int)$t['machine_id']][(int)$t['product_id']] = [
                'dcp_id' => (int)$t['id'],
                'qty_p'  => (int)($t['qty_p'] ?? 0),
            ];
        }

        $moved = 0;

        foreach ($fromDcp as $dcp) {
            $sourceId  = (int)$dcp['id'];
            $machineId = (int)$dcp['machine_id'];
            $productId = (int)$dcp['product_id'];

            if (!$sourceId || !$machineId || !$productId) continue;

            // wip A (0->DC) by sourceId
            $whereA = [
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => 0,
                'to_process_id'   => $dcProcessId,
                'source_table'    => 'die_casting_production',
                'source_id'       => $sourceId,
            ];

            $existA = $db->table('production_wip')->where($whereA)->get()->getRowArray();
            if (!$existA) continue;

            // kalau sudah DONE, skip (kecuali admin)
            if (!$forceAdmin && strtoupper((string)($existA['status'] ?? '')) === 'DONE') {
                continue;
            }

            $stockNow = (int)($existA['stock'] ?? 0);
            if ($stockNow <= 0) continue;

            $targetInfo = $toMap[$machineId][$productId] ?? null;
            if (!$targetInfo) {
                // tidak ada plan shift berikutnya => tetap dianggap “keluar”, tapi shift asal akan di-zero oleh zeroOutDcShift()
                $moved += $stockNow;
                continue;
            }

            $targetDcpId = (int)$targetInfo['dcp_id'];
            $toPlan      = (int)$targetInfo['qty_p'];

            // wip B (0->DC) by targetDcpId
            $whereB = [
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => 0,
                'to_process_id'   => $dcProcessId,
                'source_table'    => 'die_casting_production',
                'source_id'       => $targetDcpId,
            ];

            $existB = $db->table('production_wip')->where($whereB)->get()->getRowArray();

            if ($existB) {
                $newStock = (int)($existB['stock'] ?? 0) + $stockNow;
                $updB = [
                    'stock'  => $newStock,
                    'status' => 'WAITING',
                ];
                if ($hasQtyIn)  $updB['qty_in']  = max(0, $toPlan - $newStock);
                if ($hasQtyOut) $updB['qty_out'] = 0;
                if ($hasUpdatedAt) $updB['updated_at'] = $now;

                $db->table('production_wip')->where('id', (int)$existB['id'])->update($updB);
            } else {
                $insB = $whereB + [
                    'qty'    => $toPlan,
                    'status' => 'WAITING',
                    'stock'  => $stockNow,
                ];
                if ($hasQtyIn)  $insB['qty_in']  = max(0, $toPlan - $stockNow);
                if ($hasQtyOut) $insB['qty_out'] = 0;
                if ($hasCreatedAt) $insB['created_at'] = $now;
                if ($hasUpdatedAt) $insB['updated_at'] = $now;

                $db->table('production_wip')->insert($insB);
            }

            $moved += $stockNow;
        }

        return $moved;
    }

    /* =====================================================
     * SHIFT 3: transfer ke flow berikutnya pakai qty_out
     * ===================================================== */
    private function finishShiftTransferFlowShift3Only($db, string $date, int $dcProcessId, int $shift3Id, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;

        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        $dcpRows = $db->table('die_casting_production')
            ->select('id, product_id, qty_p, qty_a')
            ->where('production_date', $date)
            ->where('shift_id', $shift3Id)
            ->where('product_id >', 0)
            ->get()->getResultArray();

        if (!$dcpRows) return 0;

        $processed = 0;

        foreach ($dcpRows as $dcp) {
            $sourceId  = (int)$dcp['id'];
            $productId = (int)$dcp['product_id'];
            $qtyPlan   = (int)($dcp['qty_p'] ?? 0);
            $qtyA      = (int)($dcp['qty_a'] ?? 0);

            $whereA = [
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => 0,
                'to_process_id'   => $dcProcessId,
                'source_table'    => 'die_casting_production',
                'source_id'       => $sourceId,
            ];

            $existA = $db->table('production_wip')->where($whereA)->get()->getRowArray();
            if (!$existA) continue;

            if (!$forceAdmin && strtoupper((string)($existA['status'] ?? '')) === 'DONE') {
                $processed++;
                continue;
            }

            $stockNow = $hasStock ? (int)($existA['stock'] ?? 0) : 0;
            $transferQty = max($stockNow, $qtyA);
            if ($transferQty <= 0) { $processed++; continue; }

            $nextProcessId = $this->resolveNextProcessByFlow($db, $productId, $dcProcessId) ?? 0;

            // update DC stage: qty_out = transferQty, stock=0, DONE
            $updA = [
                'qty'    => $qtyPlan > 0 ? $qtyPlan : $transferQty,
                'status' => 'DONE',
            ];
            if ($hasQtyIn)  $updA['qty_in']  = 0;
            if ($hasQtyOut) $updA['qty_out'] = $transferQty;
            if ($hasStock)  $updA['stock']   = 0;
            if ($hasUpdatedAt) $updA['updated_at'] = $now;

            $db->table('production_wip')->where('id', (int)$existA['id'])->update($updA);

            // create/update next stage (DC->NEXT): qty_in=transfer, stock=transfer, WAITING
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
                if ($hasQtyOut) $payloadB['qty_out'] = 0;
                if ($hasStock)  $payloadB['stock']   = $transferQty;
                if ($hasUpdatedAt) $payloadB['updated_at'] = $now;

                if ($existB) {
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

            $db->table('die_casting_production')
                ->where('id', $dcpId)
                ->update(['qty_a' => $fg, 'qty_ng' => $ng]);

            $this->upsertWipDcStageRealtime($db, $date, $dcpId, $productId, $qtyPlan, $fg, $dcProcessId, $forceAdmin);
        }
    }

    private function upsertWipDcStageRealtime($db, string $date, int $sourceId, int $productId, int $qtyPlan, int $totalFg, int $dcProcessId, bool $forceAdmin = false): void
    {
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

        if ($exist && strtoupper((string)($exist['status'] ?? '')) === 'DONE' && !$forceAdmin) {
            return;
        }

        // jangan turunkan stock kalau sudah ada carry
        $existingStock = $exist ? (int)($exist['stock'] ?? 0) : 0;
        $stockVal = $hasStock ? max($existingStock, $totalFg) : 0;

        $remaining = max(0, $qtyPlan - $stockVal);

        $payload = $where + [
            'qty'    => $qtyPlan,
            'status' => 'WAITING',
        ];

        if ($hasQtyIn)  $payload['qty_in']  = $remaining;
        if ($hasQtyOut) $payload['qty_out'] = 0;
        if ($hasStock)  $payload['stock']   = $stockVal;
        if ($hasUpdatedAt) $payload['updated_at'] = $now;

        if ($exist) {
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($hasCreatedAt) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    /* =========================
     * NG details helpers
     * ========================= */
    private function saveNgDetails($db, int $hourlyId, $ngDetails): void
    {
        if (!$db->tableExists('die_casting_hourly_ng_details')) return;
        if (!is_array($ngDetails)) $ngDetails = [];

        $db->table('die_casting_hourly_ng_details')->where('hourly_id', $hourlyId)->delete();

        $batch = [];
        foreach ($ngDetails as $d) {
            $ngId = (int)($d['ng_category_id'] ?? 0);
            $qty  = (int)($d['qty'] ?? 0);
            if ($ngId <= 0 || $qty <= 0) continue;
            $batch[] = [
                'hourly_id' => $hourlyId,
                'ng_category_id' => $ngId,
                'qty' => $qty,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($batch)) {
            $db->table('die_casting_hourly_ng_details')->insertBatch($batch);
        }
    }

    private function sumNgDetail($db, int $hourlyId): int
    {
        if (!$db->tableExists('die_casting_hourly_ng_details')) return 0;
        $row = $db->table('die_casting_hourly_ng_details')
            ->select('SUM(qty) AS s')
            ->where('hourly_id', $hourlyId)
            ->get()->getRowArray();
        return (int)($row['s'] ?? 0);
    }

    /* =========================
     * Finish shift helpers
     * ========================= */
    private function markDcShiftCompleted($db, string $date, int $shiftId): void
    {
        if ($db->fieldExists('is_completed', 'die_casting_production')) {
            $db->table('die_casting_production')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->update(['is_completed' => 1]);
        }

        if ($db->tableExists('daily_schedules') && $db->fieldExists('is_completed', 'daily_schedules')) {
            $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
            $db->table('daily_schedules')
                ->where('schedule_date', $date)
                ->where('process_id', $dcProcessId)
                ->where('shift_id', $shiftId)
                ->update(['is_completed' => 1]);
        }
    }

    private function isNearLastSlotEnd($db, int $shiftId, string $date, int $minutesBeforeEnd = 15): bool
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

        $now = time();
        $windowStart = $endDT - ($minutesBeforeEnd * 60);
        return $now >= $windowStart && $now <= $endDT;
    }

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
