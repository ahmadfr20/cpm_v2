<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionAchievementController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $tz  = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);

        // process DC
        $dcProcess = $db->table('production_processes')
            ->where('process_code', 'DC')
            ->get()->getRowArray();
        $dcProcessId = (int)($dcProcess['id'] ?? 1);

        // ng categories DC
        $ngCategories = $db->table('ng_categories')
            ->where('process_name', 'Die Casting')
            ->orderBy('ng_code')
            ->get()->getResultArray();

        // shifts DC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        $allProductIds = [];
        $allDcpIds     = [];

        foreach ($shifts as &$shift) {

            // slots shift
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id AS time_slot_id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start', 'ASC')
                ->get()->getResultArray();

            $shift['slots'] = $slots;

            $shiftStart = $slots[0]['time_start'] ?? null;
            $shiftEnd   = null;

            if (!empty($slots)) {
                $last = end($slots);
                $shiftEnd = $last['time_end'] ?? null;
                reset($slots);
            }

            // ===== rule: editable hanya 1 jam setelah shift berakhir =====
            $shift['isEditable']   = false;
            $shift['editDeadline'] = null;

            if ($shiftStart && $shiftEnd) {
                $startDt = new \DateTime($date . ' ' . $shiftStart, $tz);
                $endDt   = new \DateTime($date . ' ' . $shiftEnd, $tz);

                // shift lewat midnight
                if ($endDt <= $startDt) {
                    $endDt->modify('+1 day');
                }

                $deadline = (clone $endDt)->modify('+1 hour');

                $shift['editDeadline'] = $deadline->format('Y-m-d H:i:s');
                $shift['isEditable']   = ($now >= $endDt && $now <= $deadline);
            }

            // data header + sum hourly
            $items = $db->table('die_casting_production dcp')
                ->select("
                    dcp.id AS production_id,
                    dcp.machine_id,
                    m.machine_code,
                    dcp.product_id,
                    p.part_no,
                    COALESCE(dcp.part_label, p.part_name) AS part_name,
                    dcp.qty_p AS target,
                    dcp.qty_a AS qty_a,
                    dcp.qty_ng AS qty_ng,
                    IFNULL(SUM(dh.qty_fg),0) AS sum_hourly_fg,
                    IFNULL(SUM(dh.qty_ng),0) AS sum_hourly_ng,
                    MAX(dh.ng_category_id) AS ng_category_id,
                    IFNULL(SUM(dh.downtime_minute),0) AS downtime
                ")
                ->join('machines m', 'm.id = dcp.machine_id', 'left')
                ->join('products p', 'p.id = dcp.product_id')
                ->join(
                    'die_casting_hourly dh',
                    'dh.production_date = dcp.production_date
                     AND dh.shift_id = dcp.shift_id
                     AND dh.machine_id = dcp.machine_id
                     AND dh.product_id = dcp.product_id',
                    'left'
                )
                ->where('dcp.production_date', $date)
                ->where('dcp.shift_id', $shift['id'])
                ->where('dcp.qty_p >', 0)
                ->groupBy('dcp.id, dcp.machine_id, dcp.product_id, dcp.qty_p, dcp.qty_a, dcp.qty_ng, m.machine_code, p.part_no, part_name')
                ->orderBy('m.line_position', 'ASC')
                ->get()->getResultArray();

            foreach ($items as &$it) {
                // tampilkan actual: pakai header kalau sudah ada, fallback sum hourly
                $it['fg_display'] = ((int)$it['qty_a'] > 0) ? (int)$it['qty_a'] : (int)$it['sum_hourly_fg'];
                $it['ng_display'] = ((int)$it['qty_ng'] > 0) ? (int)$it['qty_ng'] : (int)$it['sum_hourly_ng']; // ✅ FIX SYNTAX

                $allProductIds[] = (int)$it['product_id'];
                $allDcpIds[]     = (int)$it['production_id'];
            }
            unset($it);

            $shift['items'] = $items;
        }
        unset($shift);

        $allProductIds = array_values(array_unique($allProductIds));
        $allDcpIds     = array_values(array_unique($allDcpIds));

        // ===== NEXT PROCESS MAP =====
        $nextProcessMap = [];
        if (!empty($allProductIds)) {
            $flows = $db->table('product_process_flows ppf')
                ->select('ppf.product_id, ppf.process_id, ppf.sequence, pp.process_name')
                ->join('production_processes pp', 'pp.id = ppf.process_id')
                ->whereIn('ppf.product_id', $allProductIds)
                ->where('ppf.is_active', 1)
                ->orderBy('ppf.product_id', 'ASC')
                ->orderBy('ppf.sequence', 'ASC')
                ->get()->getResultArray();

            $byProduct = [];
            foreach ($flows as $f) {
                $pid = (int)$f['product_id'];
                $byProduct[$pid][] = [
                    'process_id'   => (int)$f['process_id'],
                    'process_name' => $f['process_name'],
                    'sequence'     => (int)$f['sequence'],
                ];
            }

            foreach ($byProduct as $pid => $list) {
                $dcSeq = null;
                foreach ($list as $row) {
                    if ((int)$row['process_id'] === $dcProcessId) {
                        $dcSeq = (int)$row['sequence'];
                        break;
                    }
                }

                $next = null;
                if ($dcSeq !== null) {
                    foreach ($list as $row) {
                        if ((int)$row['sequence'] === ($dcSeq + 1)) {
                            $next = $row;
                            break;
                        }
                    }
                }

                $nextProcessMap[$pid] = [
                    'to_process_id'   => $next['process_id'] ?? null,
                    'to_process_name' => $next['process_name'] ?? '-'
                ];
            }
        }

        // ===== WIP MAP =====
        $wipMap = [];
        if (!empty($allDcpIds)) {
            $wips = $db->table('production_wip')
                ->select('source_id, qty, status')
                ->where('source_table', 'die_casting_production')
                ->whereIn('source_id', $allDcpIds)
                ->get()->getResultArray();

            foreach ($wips as $w) {
                $wipMap[(int)$w['source_id']] = [
                    'qty'    => (int)($w['qty'] ?? 0),
                    'status' => $w['status'] ?? 'WAITING'
                ];
            }
        }

        // tempelkan flow+wip ke items
        foreach ($shifts as &$shift) {
            foreach ($shift['items'] as &$it) {
                $pid   = (int)$it['product_id'];
                $dcpId = (int)$it['production_id'];

                $it['next_process_name'] = $nextProcessMap[$pid]['to_process_name'] ?? '-';
                $it['next_process_id']   = $nextProcessMap[$pid]['to_process_id'] ?? null;

                $it['wip_qty']    = $wipMap[$dcpId]['qty'] ?? 0;
                $it['wip_status'] = $wipMap[$dcpId]['status'] ?? 'WAITING';
            }
            unset($it);
        }
        unset($shift);

        return view('die_casting/daily_production_achievement/index', [
            'date'        => $date,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories
        ]);
    }

    public function store()
    {
        $db = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data kosong / terpotong');
        }

        $tz  = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);

        // validasi window koreksi 1 jam (berdasarkan shift_id dari item pertama)
        $first   = reset($items);
        $date    = $first['date'] ?? null;
        $shiftId = (int)($first['shift_id'] ?? 0);

        if (!$date || !$shiftId) {
            return redirect()->back()->with('error', 'Data shift/tanggal tidak valid');
        }

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('ts.time_start', 'ASC')
            ->get()->getResultArray();

        $shiftStart = $slots[0]['time_start'] ?? null;
        $shiftEnd   = null;
        if (!empty($slots)) {
            $last = end($slots);
            $shiftEnd = $last['time_end'] ?? null;
        }

        if ($shiftStart && $shiftEnd) {
            $startDt = new \DateTime($date.' '.$shiftStart, $tz);
            $endDt   = new \DateTime($date.' '.$shiftEnd, $tz);
            if ($endDt <= $startDt) $endDt->modify('+1 day');

            $deadline = (clone $endDt)->modify('+1 hour');

            if (!($now >= $endDt && $now <= $deadline)) {
                return redirect()->back()->with(
                    'error',
                    'Waktu koreksi sudah habis. Koreksi hanya dapat dilakukan maksimal 1 jam setelah shift berakhir.'
                );
            }
        }

        $db->transBegin();

        try {
            foreach ($items as $row) {
                $productionId = (int)($row['production_id'] ?? 0);
                $date         = $row['date'] ?? null;
                $shiftId      = (int)($row['shift_id'] ?? 0);
                $machineId    = (int)($row['machine_id'] ?? 0);
                $productId    = (int)($row['product_id'] ?? 0);

                if (!$productionId || !$date || !$shiftId || !$machineId || !$productId) {
                    continue;
                }

                $fg = (int)($row['fg'] ?? 0);
                $ng = (int)($row['ng'] ?? 0);

                // Update header
                $db->table('die_casting_production')
                    ->where('id', $productionId)
                    ->update([
                        'qty_a'  => $fg,
                        'qty_ng' => $ng
                    ]);

                // Sync wip qty jika masih waiting
                $db->table('production_wip')
                    ->where([
                        'source_table' => 'die_casting_production',
                        'source_id'    => $productionId,
                        'status'       => 'WAITING'
                    ])
                    ->update(['qty' => $fg]);

                // simpan ng category & downtime ke last slot saja
                $ngCategoryId = $row['ng_category_id'] ?? null;
                $downtime     = (int)($row['downtime'] ?? 0);

                $lastSlot = $db->table('shift_time_slots sts')
                    ->select('ts.id AS time_slot_id')
                    ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                    ->where('sts.shift_id', $shiftId)
                    ->orderBy('ts.time_start', 'DESC')
                    ->get()->getRowArray();
                $lastSlotId = (int)($lastSlot['time_slot_id'] ?? 0);

                if ($lastSlotId > 0) {
                    $db->table('die_casting_hourly')
                        ->where([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => $machineId,
                            'product_id'      => $productId,
                        ])
                        ->update([
                            'downtime_minute' => 0,
                            'ng_category_id'  => null,
                        ]);

                    $db->table('die_casting_hourly')
                        ->where([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => $machineId,
                            'product_id'      => $productId,
                            'time_slot_id'    => $lastSlotId,
                        ])
                        ->update([
                            'downtime_minute' => $downtime,
                            'ng_category_id'  => $ngCategoryId ?: null,
                            'updated_at'      => $now->format('Y-m-d H:i:s')
                        ]);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily Production per Shift berhasil disimpan');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
