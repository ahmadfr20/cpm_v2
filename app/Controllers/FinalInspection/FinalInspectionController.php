<?php

namespace App\Controllers\FinalInspection;

use App\Controllers\BaseController;

class FinalInspectionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        // SHIFT FI (contoh: shift_name mengandung "FI")
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'FI')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // Process id Final Inspection
        $fiProcessId = $this->getProcessIdByName($db, 'Final Inspection');
        if ($fiProcessId <= 0) {
            // fallback kalau di master pakai nama lain
            $fiProcessId = $this->getProcessIdByName($db, 'Final Inspection / QC');
        }

        // NG category untuk FI (kalau kamu punya)
        $ngCategories = $db->table('ng_categories')
            ->where('process_name', 'Final Inspection')
            ->orderBy('ng_code')
            ->get()->getResultArray();

        // Mesin khusus untuk output FI (HARUS ada di machines)
        $fiMachineId = $this->getFiMachineId($db); // lihat helper

        foreach ($shifts as &$shift) {
            // slots shift
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()->getResultArray();

            // total minute shift
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $slot['minute'] = ($end - $start) / 60;
                $totalMinute += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            // items: ambil dari WIP yang menuju Final Inspection
            $shift['items'] = $this->getFinalInspectionItemsFromWip($db, $date, $fiProcessId);

            // hourly map: ambil dari production_outputs (process = FI)
            $rows = $db->table('production_outputs')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->where('process_id', $fiProcessId)
                ->get()->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($rows as $r) {
                $pid = (int)$r['product_id'];
                $tid = (int)($r['time_slot_id'] ?? 0);
                $shift['hourly_map'][$pid][$tid] = $r;
            }
        }
        unset($shift);

        return view('final_inspection/daily_production/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories,
            'fiProcessId'  => $fiProcessId,
            'fiMachineId'  => $fiMachineId,
        ]);
    }

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data kosong / terpotong');
        }

        $db->transBegin();

        try {
            $first = reset($items);
            $date  = (string)($first['date'] ?? '');
            $shiftIds = [];

            $fiProcessId = (int)($first['process_id'] ?? 0);
            if ($fiProcessId <= 0) throw new \Exception('Process Final Inspection tidak valid');

            $fiMachineId = $this->getFiMachineId($db);
            if ($fiMachineId <= 0) throw new \Exception('Mesin FI belum diset. Buat 1 mesin khusus untuk Final Inspection.');

            foreach ($items as $row) {
                if (
                    empty($row['date']) ||
                    empty($row['shift_id']) ||
                    empty($row['product_id']) ||
                    empty($row['time_slot_id']) ||
                    empty($row['process_id'])
                ) continue;

                $shiftIds[(int)$row['shift_id']] = true;

                // Upsert output per jam
                // NOTE: production_outputs belum ada unique index.
                // Jadi kita "manual upsert" pakai where lalu update/insert.
                $where = [
                    'production_date' => (string)$row['date'],
                    'shift_id'        => (int)$row['shift_id'],
                    'time_slot_id'    => (int)$row['time_slot_id'],
                    'product_id'      => (int)$row['product_id'],
                    'machine_id'      => $fiMachineId,
                    'process_id'      => (int)$row['process_id'],
                ];

                $exist = $db->table('production_outputs')->where($where)->get()->getRowArray();

                $payload = $where + [
                    'qty_ok'    => (int)($row['ok'] ?? 0),
                    'qty_ng'    => (int)($row['ng'] ?? 0),
                    'created_at'=> date('Y-m-d H:i:s'),
                ];

                if ($exist) {
                    $db->table('production_outputs')->where('id', (int)$exist['id'])->update($payload);
                } else {
                    $db->table('production_outputs')->insert($payload);
                }
            }

            // sync WIP realtime (stock FI)
            foreach (array_keys($shiftIds) as $sid) {
                $this->syncFinalInspectionWipRealtime($db, $date, (int)$sid, $fiProcessId);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Final Inspection tersimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function saveSlot()
    {
        $db = db_connect();
        $data = $this->request->getPost();

        if (
            empty($data['date']) ||
            empty($data['shift_id']) ||
            empty($data['product_id']) ||
            empty($data['time_slot_id']) ||
            empty($data['process_id'])
        ) {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        $db->transBegin();

        try {
            $fiProcessId = (int)$data['process_id'];
            $fiMachineId = $this->getFiMachineId($db);
            if ($fiMachineId <= 0) throw new \Exception('Mesin FI belum diset.');

            $where = [
                'production_date' => (string)$data['date'],
                'shift_id'        => (int)$data['shift_id'],
                'time_slot_id'    => (int)$data['time_slot_id'],
                'product_id'      => (int)$data['product_id'],
                'machine_id'      => $fiMachineId,
                'process_id'      => $fiProcessId,
            ];

            $exist = $db->table('production_outputs')->where($where)->get()->getRowArray();

            $payload = $where + [
                'qty_ok'     => (int)($data['ok'] ?? 0),
                'qty_ng'     => (int)($data['ng'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if ($exist) {
                $db->table('production_outputs')->where('id', (int)$exist['id'])->update($payload);
            } else {
                $db->table('production_outputs')->insert($payload);
            }

            $this->syncFinalInspectionWipRealtime($db, (string)$data['date'], (int)$data['shift_id'], $fiProcessId);

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return $this->response->setJSON(['status' => true]);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /* =====================================================
     * Sync WIP FI realtime:
     * - Ambil incoming dari production_wip (to_process_id = FI)
     * - Hitung total OK+NG dari production_outputs (process FI) per product
     * - Update stock FI (sisa / progress)
     * ===================================================== */
    private function syncFinalInspectionWipRealtime($db, string $date, int $shiftId, int $fiProcessId): void
    {
        if (!$db->tableExists('production_wip')) return;

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

        // Ambil semua WIP yang menuju FI pada tanggal tsb (tanpa kunci shift)
        $wip = $db->table('production_wip')
            ->where('production_date', $date)
            ->where('to_process_id', $fiProcessId)
            ->get()->getResultArray();

        if (!$wip) return;

        $fiMachineId = $this->getFiMachineId($db);
        if ($fiMachineId <= 0) return;

        $now = date('Y-m-d H:i:s');

        foreach ($wip as $w) {
            $wipId = (int)($w['id'] ?? 0);
            $pid   = (int)($w['product_id'] ?? 0);
            if ($wipId <= 0 || $pid <= 0) continue;

            // total inspected for this product in FI on this date + shift
            // (kalau kamu mau FI dihitung lintas shift, hilangkan filter shift_id)
            $sum = $db->table('production_outputs')
                ->select('SUM(qty_ok) AS ok_sum, SUM(qty_ng) AS ng_sum')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->where('process_id', $fiProcessId)
                ->where('product_id', $pid)
                ->where('machine_id', $fiMachineId)
                ->get()->getRowArray();

            $ok = (int)($sum['ok_sum'] ?? 0);
            $ng = (int)($sum['ng_sum'] ?? 0);
            $done = $ok + $ng;

            // incoming qty
            $incoming = (int)($w['qty'] ?? 0);
            if ($hasQtyIn && isset($w['qty_in'])) $incoming = (int)$w['qty_in'];
            if ($incoming <= 0) $incoming = (int)($w['qty'] ?? 0);

            $remain = max(0, $incoming - $done);

            $upd = [];
            if ($hasStock) $upd['stock'] = $remain;           // stock di FI = sisa belum inspect
            if ($hasQtyOut) $upd['qty_out'] = $done;          // qty_out FI = sudah inspect (OK+NG)
            if ($hasUpdatedAt) $upd['updated_at'] = $now;

            // status DONE kalau sudah selesai inspect semua incoming
            if ($remain <= 0 && $incoming > 0) {
                $upd['status'] = 'DONE';
            } else {
                $upd['status'] = 'WAITING';
            }

            if ($upd) {
                $db->table('production_wip')->where('id', $wipId)->update($upd);
            }
        }
    }

    /* =====================================================
     * Items FI dari WIP (to_process_id = FI)
     * - Join products untuk part_no / part_name
     * - Join production_processes untuk nama prev process (optional)
     * ===================================================== */
    private function getFinalInspectionItemsFromWip($db, string $date, int $fiProcessId): array
    {
        if (!$db->tableExists('production_wip')) return [];

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        // Ambil semua incoming ke FI
        $rows = $db->table('production_wip pw')
            ->select('
                pw.id AS wip_id,
                pw.product_id,
                pw.from_process_id,
                pw.to_process_id,
                pw.qty,
                pw.qty_in,
                pw.qty_out,
                pw.stock,
                pw.status,
                pw.source_table,
                pw.source_id,
                p.part_no,
                p.part_name
            ')
            ->join('products p', 'p.id = pw.product_id')
            ->where('pw.production_date', $date)
            ->where('pw.to_process_id', $fiProcessId)
            ->orderBy('p.part_no', 'ASC')
            ->get()->getResultArray();

        // Agregasi per product (karena WIP bisa lebih dari 1 source_id)
        $items = [];
        foreach ($rows as $r) {
            $pid = (int)$r['product_id'];
            if (!isset($items[$pid])) {
                $items[$pid] = [
                    'product_id'   => $pid,
                    'part_no'      => (string)($r['part_no'] ?? ''),
                    'part_name'    => (string)($r['part_name'] ?? ''),
                    'incoming'     => 0,
                    'stock'        => 0,
                    'from_process' => (int)($r['from_process_id'] ?? 0),
                    'sources'      => [], // optional trace
                ];
            }

            $incoming = (int)($r['qty'] ?? 0);
            if ($hasQtyIn && isset($r['qty_in'])) $incoming = (int)$r['qty_in'];

            $items[$pid]['incoming'] += $incoming;

            if ($hasStock && isset($r['stock'])) {
                $items[$pid]['stock'] += (int)$r['stock'];
            } else {
                // fallback: stock = incoming - qty_out (kalau ada)
                $items[$pid]['stock'] += max(0, $incoming - (int)($r['qty_out'] ?? 0));
            }

            $items[$pid]['sources'][] = [
                'wip_id' => (int)($r['wip_id'] ?? 0),
                'source_table' => (string)($r['source_table'] ?? ''),
                'source_id' => (int)($r['source_id'] ?? 0),
            ];
        }

        return array_values($items);
    }

    /* =========================
     * Helpers
     * ========================= */

    private function getFiMachineId($db): int
    {
        // Cari mesin khusus FI. Kamu bisa pakai rule:
        // - machine_code = 'FI'
        // - atau machine_name like 'Final Inspection'
        $row = $db->table('machines')
            ->select('id')
            ->groupStart()
                ->where('machine_code', 'FI')
                ->orLike('machine_name', 'Final Inspection')
                ->orLike('machine_name', 'Final Inspection / QC')
            ->groupEnd()
            ->limit(1)
            ->get()->getRowArray();

        return (int)($row['id'] ?? 0);
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
