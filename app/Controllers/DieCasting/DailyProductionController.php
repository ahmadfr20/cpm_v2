<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        // ===== SHIFT DIE CASTING SAJA =====
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // ===== NG CATEGORY (DIE CASTING) =====
        $ngCategories = $db->table('ng_categories')
            ->where('process_name', 'Die Casting')
            ->orderBy('ng_code')
            ->get()->getResultArray();

        // ===== process_id untuk Die Casting =====
        $dcRow = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Die Casting')
            ->get()->getRowArray();

        $dcProcessId = (int)($dcRow['id'] ?? 0);

        foreach ($shifts as &$shift) {

            // ===== TIME SLOT =====
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()->getResultArray();

            // ===== TOTAL MENIT SHIFT =====
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $slot['minute'] = ($end - $start) / 60;
                $totalMinute += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            // ===== TARGET PER SHIFT =====
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
                ->get()
                ->getResultArray();

            // ===== HOURLY MAP =====
            $hourly = $db->table('die_casting_hourly')
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

            // =========================
            // Jika tidak ada item, siapkan default map
            // =========================
            $productIds = array_values(array_unique(array_map(fn($x) => (int)$x['product_id'], $shift['items'])));
            if (!$productIds) {
                $shift['next_process_map'] = [];
                $shift['wip_map'] = [];
                $shift['actual_totals'] = [];
                continue;
            }

            // =========================
            // NEXT PROCESS MAP (flow)
            // =========================
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

                // cari posisi Die Casting di flow
                $idx = null;
                if ($dcProcessId > 0) {
                    foreach ($seq as $i => $row) {
                        if ((int)$row['process_id'] === $dcProcessId) { $idx = $i; break; }
                    }
                }

                // Jika DC tidak ketemu di flow: fallback -> ambil proses kedua (index 1) jika ada
                if ($idx === null) {
                    $shift['next_process_map'][$pid] = isset($seq[1])
                        ? ['process_id' => $seq[1]['process_id'], 'process_name' => $seq[1]['process_name']]
                        : null;
                    continue;
                }

                // next = setelah idx
                $shift['next_process_map'][$pid] = isset($seq[$idx + 1])
                    ? ['process_id' => $seq[$idx + 1]['process_id'], 'process_name' => $seq[$idx + 1]['process_name']]
                    : null;
            }

            // =========================
            // ACTUAL TOTALS (fallback qty untuk tampilan WIP)
            // =========================
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

            // =========================
            // WIP MAP (JOIN -> dapat machine_id lewat die_casting_production)
            // =========================
            if ($dcProcessId > 0) {
                $wipRows = $db->table('production_wip pw')
                    ->select('dcp.machine_id, pw.product_id, pw.qty, pw.status, pp.process_name AS to_process_name')
                    ->join('die_casting_production dcp', 'dcp.id = pw.source_id', 'left')
                    ->join('production_processes pp', 'pp.id = pw.to_process_id', 'left')
                    ->where('pw.source_table', 'die_casting_production')
                    ->where('pw.production_date', $date)
                    ->where('pw.from_process_id', $dcProcessId)
                    ->where('dcp.shift_id', $shift['id'])
                    ->get()->getResultArray();
            } else {
                $wipRows = [];
            }

            $shift['wip_map'] = [];
            foreach ($wipRows as $w) {
                $mid = (int)($w['machine_id'] ?? 0);
                $pid = (int)($w['product_id'] ?? 0);
                if ($mid && $pid) {
                    $shift['wip_map'][$mid][$pid] = [
                        'qty'             => (int)($w['qty'] ?? 0),
                        'status'          => (string)($w['status'] ?? 'WAITING'),
                        'to_process_name' => (string)($w['to_process_name'] ?? '-'),
                    ];
                }
            }
        }

        return view('die_casting/daily_production/index', [
            'date'         => $date,
            'operator'     => $operator,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories
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

                $db->table('die_casting_hourly')->replace([
                    'production_date' => $row['date'],
                    'shift_id'        => $row['shift_id'],
                    'machine_id'      => $row['machine_id'],
                    'product_id'      => $row['product_id'],
                    'time_slot_id'    => $row['time_slot_id'],
                    'qty_fg'          => (int) ($row['fg'] ?? 0),
                    'qty_ng'          => (int) ($row['ng'] ?? 0),
                    'ng_category_id'  => $row['ng_category_id'] ?? null,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }

            $first = reset($items);
            $date  = $first['date'];
            $shiftId = $first['shift_id'];

            $this->syncDailyScheduleActual($date, $shiftId);

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
                'production_date' => $data['date'],
                'shift_id'        => $data['shift_id'],
                'machine_id'      => $data['machine_id'],
                'product_id'      => $data['product_id'],
                'time_slot_id'    => $data['time_slot_id'],
                'qty_fg'          => (int) ($data['fg'] ?? 0),
                'qty_ng'          => (int) ($data['ng'] ?? 0),
                'ng_category_id'  => $data['ng_category_id'] ?? null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->syncDailyScheduleActual($data['date'], $data['shift_id']);

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

    /**
     * Endpoint tombol "Finish Shift 3" (kirim ke next process sesuai flow)
     */
    public function finishShift()
    {
        $db = db_connect();
        $date = $this->request->getPost('date');
        $shiftId = $this->request->getPost('shift_id');

        if (!$date || !$shiftId) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Data tidak lengkap'
            ]);
        }

        $db->transBegin();

        try {
            // pastikan qty_a die_casting_production up to date
            $this->syncDailyScheduleActual($date, $shiftId);

            // buat/update WIP
            $count = $this->syncWipToNextProcessIfShift3($date, $shiftId);

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return $this->response->setJSON([
                'status' => true,
                'message' => 'WIP dibuat/diupdate',
                'count' => $count
            ]);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function syncDailyScheduleActual($date, $shiftId)
    {
        $db = db_connect();

        $actuals = $db->table('die_casting_hourly')
            ->select('machine_id, product_id,
                      SUM(qty_fg) total_fg,
                      SUM(qty_ng) total_ng')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->groupBy('machine_id, product_id')
            ->get()->getResultArray();

        foreach ($actuals as $a) {
            $db->table('die_casting_production')
                ->where([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $a['machine_id'],
                    'product_id'      => $a['product_id']
                ])
                ->update([
                    'qty_a'  => (int) $a['total_fg'],
                    'qty_ng' => (int) $a['total_ng']
                ]);
        }
    }

    /**
     * Buat/update WIP saat Shift 3 DC selesai.
     * Return jumlah row yang diproses.
     *
     * Status enum production_wip: WAITING / SCHEDULED / DONE
     */
    private function syncWipToNextProcessIfShift3(string $date, $shiftId): int
    {
        $db = db_connect();

        $shift = $db->table('shifts')
            ->select('shift_code, shift_name')
            ->where('id', $shiftId)
            ->get()->getRowArray();

        if (!$shift) return 0;

        $isShift3 = ((int)($shift['shift_code'] ?? 0) === 3);
        $isDC     = (stripos((string)($shift['shift_name'] ?? ''), 'DC') !== false);

        if (!$isShift3 || !$isDC) return 0;

        // Die Casting process_id
        $dcRow = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Die Casting')
            ->get()->getRowArray();

        $dcProcessId = (int)($dcRow['id'] ?? 0);
        if ($dcProcessId <= 0) return 0;

        $productions = $db->table('die_casting_production')
            ->select('id, product_id, machine_id, qty_a')
            ->where('production_date', $date)
            ->where('shift_id', $shiftId)
            ->where('qty_a >', 0)
            ->get()->getResultArray();

        $processed = 0;

        foreach ($productions as $p) {
            $productId = (int)$p['product_id'];
            $sourceId  = (int)$p['id'];
            $qtyToSend = (int)$p['qty_a'];
            if ($qtyToSend <= 0) continue;

            $toProcess = $this->resolveNextProcessByFlow($productId, $dcProcessId);
            if (!$toProcess) continue;

            $existing = $db->table('production_wip')
                ->where([
                    'source_table'    => 'die_casting_production',
                    'source_id'       => $sourceId,
                    'from_process_id' => $dcProcessId,
                    'to_process_id'   => $toProcess,
                ])
                ->get()->getRowArray();

            if ($existing) {
                // jika DONE, jangan ubah
                if (($existing['status'] ?? '') === 'DONE') {
                    continue;
                }

                $db->table('production_wip')
                    ->where('id', $existing['id'])
                    ->update([
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'qty'             => $qtyToSend,
                        'status'          => $existing['status'] ?? 'WAITING',
                    ]);
            } else {
                $db->table('production_wip')->insert([
                    'production_date' => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $dcProcessId,
                    'to_process_id'   => $toProcess,
                    'qty'             => $qtyToSend,
                    'source_table'    => 'die_casting_production',
                    'source_id'       => $sourceId,
                    'status'          => 'WAITING',
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * Cari proses berikutnya dari flow table product_process_flows.
     */
    private function resolveNextProcessByFlow(int $productId, int $fromProcessId): ?int
    {
        $db = db_connect();

        $flows = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();

        if (!$flows) return null;

        $idx = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === $fromProcessId) {
                $idx = $i;
                break;
            }
        }

        // fallback kalau DC tidak ditemukan: ambil proses kedua jika ada
        if ($idx === null) {
            return isset($flows[1]) ? (int)$flows[1]['process_id'] : null;
        }

        return isset($flows[$idx + 1]) ? (int)$flows[$idx + 1]['process_id'] : null;
    }
}
