<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    /* =========================
     * INDEX
     * ========================= */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // SHIFT Die Casting (DC)
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($shifts as &$shift) {
            $shift['total_minute'] = $this->getTotalMinuteShift($db, (int)$shift['id']);
        }
        unset($shift);

        // Mesin Die Casting
        $machines = $db->table('machines')
            ->where('production_line', 'Die Casting')
            ->orderBy('line_position')
            ->get()
            ->getResultArray();

        // Existing schedule (die_casting_production)
        $existing = $db->table('die_casting_production')
            ->where('production_date', $date)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($existing as $e) {
            $map[(int)$e['shift_id']][(int)$e['machine_id']] = $e;
        }

        return view('die_casting/daily_schedule/index', [
            'date'     => $date,
            'shifts'   => $shifts,
            'machines' => $machines,
            'map'      => $map
        ]);
    }

    /* =========================
     * Process ID Die Casting
     * ========================= */
    private function getProcessIdDieCasting($db): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Die Casting')
            ->get()
            ->getRowArray();

        if (!$row) {
            throw new \Exception('Process "Die Casting" belum ada di master production_processes');
        }
        return (int)$row['id'];
    }

    /* =========================
     * Helper total menit shift
     * ========================= */
    private function getTotalMinuteShift($db, int $shiftId): int
    {
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()
            ->getResultArray();

        $totalMinute = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400;
            $totalMinute += (int)(($end - $start) / 60);
        }
        return (int)$totalMinute;
    }

    /* =====================================================
     * VALIDASI: Produk harus punya flow DC aktif
     * ===================================================== */
    private function validateProductHasFlowDC($db, int $productId, int $processIdDC): bool
    {
        return $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processIdDC)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /* =====================================================
     * AJAX: Produk + target (berdasarkan flow DC)
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdDC = $this->getProcessIdDieCasting($db);
        $totalMinute = $this->getTotalMinuteShift($db, $shiftId);

        $products = $db->table('product_process_flows ppf')
            ->select('
                p.id,
                p.part_no,
                p.part_name,
                p.weight_ascas,
                p.weight_runner,
                p.cycle_time,
                p.cavity,
                p.efficiency_rate
            ')
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdDC)
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($products as &$p) {
            $cycle  = (int)($p['cycle_time'] ?? 0);
            $cavity = (int)($p['cavity'] ?? 0);

            $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            if ($cycle > 0 && $cavity > 0 && $totalMinute > 0) {
                $target = floor((($totalMinute * 60) / $cycle) * $cavity * $eff);
                $p['target'] = min((int)$target, 1200);
            } else {
                $p['target'] = 0;
            }
        }
        unset($p);

        return $this->response->setJSON($products);
    }

    /* =====================================================
     * Flow helper: ambil urutan process_id aktif suatu product
     * ===================================================== */
    private function getActiveFlowProcessIds($db, int $productId): array
    {
        if (!$db->tableExists('product_process_flows')) return [];

        $rows = $db->table('product_process_flows')
            ->select('process_id')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(fn($r) => (int)$r['process_id'], $rows);
    }

    private function getNextProcessIdByFlow($db, int $productId, int $currentProcessId): ?int
    {
        $seq = $this->getActiveFlowProcessIds($db, $productId);
        if (!$seq) return null;

        $idx = array_search($currentProcessId, $seq, true);
        if ($idx === false) {
            // fallback: kalau DC tidak ketemu, ambil proses ke-2 kalau ada
            return $seq[1] ?? null;
        }

        return $seq[$idx + 1] ?? null;
    }

    /* =====================================================
     * UPSERT daily_schedules header
     * NOTE: schedule = belum selesai => is_completed = 0
     * ===================================================== */
    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        $builder = $db->table('daily_schedules');
        $exist = $builder->where([
                'schedule_date' => $date,
                'shift_id'      => $shiftId,
                'section'       => $section,
            ])
            ->get()
            ->getRowArray();

        $now = date('Y-m-d H:i:s');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'daily_schedules');

        if ($exist) {
            $data = ['is_completed' => 0];
            if ($db->fieldExists('process_id', 'daily_schedules')) $data['process_id'] = $processId;
            if ($hasUpdatedAt) $data['updated_at'] = $now;

            $builder->where('id', (int)$exist['id'])->update($data);
            return (int)$exist['id'];
        }

        $insert = [
            'schedule_date' => $date,
            'shift_id'      => $shiftId,
            'section'       => $section,
            'is_completed'  => 0,
            'created_at'    => $now,
        ];
        if ($db->fieldExists('process_id', 'daily_schedules')) $insert['process_id'] = $processId;
        if ($hasUpdatedAt) $insert['updated_at'] = $now;

        $builder->insert($insert);
        return (int)$db->insertID();
    }

    /* =====================================================
     * UPSERT daily_schedule_items (1 row per machine)
     * ===================================================== */
    private function upsertDailyScheduleItem(
        $db,
        int $dailyScheduleId,
        int $shiftId,
        int $machineId,
        int $productId,
        int $cycleTime,
        int $cavity,
        int $targetPerHour,
        int $targetPerShift
    ): int {
        // enforce 1 selected per machine
        if ($db->fieldExists('is_selected', 'daily_schedule_items')) {
            $db->table('daily_schedule_items')
                ->where(['daily_schedule_id' => $dailyScheduleId, 'machine_id' => $machineId])
                ->set('is_selected', 0)
                ->update();
        }

        $exist = $db->table('daily_schedule_items')
            ->where(['daily_schedule_id' => $dailyScheduleId, 'machine_id' => $machineId])
            ->get()
            ->getRowArray();

        $payload = [
            'daily_schedule_id' => $dailyScheduleId,
            'shift_id'          => $shiftId,
            'machine_id'        => $machineId,
            'product_id'        => $productId,
            'cycle_time'        => $cycleTime,
            'cavity'            => $cavity,
            'target_per_hour'   => $targetPerHour,
            'target_per_shift'  => $targetPerShift,
        ];
        if ($db->fieldExists('is_selected', 'daily_schedule_items')) $payload['is_selected'] = 1;

        if ($exist) {
            $db->table('daily_schedule_items')->where('id', (int)$exist['id'])->update($payload);
            return (int)$exist['id'];
        }

        $db->table('daily_schedule_items')->insert($payload);
        return (int)$db->insertID();
    }

    /* =====================================================
     * UPSERT WIP (status sesuai kebutuhan)
     * - qty_in/out/stock optional (kalau kolom ada)
     * ===================================================== */
    private function upsertProductionWip(
        $db,
        string $date,
        int $productId,
        int $fromProcessId,
        int $toProcessId,
        int $qty,              // tetap isi kolom qty (legacy)
        string $status,
        string $sourceTable,
        int $sourceId,
        ?int $qtyIn = null,
        ?int $qtyOut = null,
        ?int $stock = null
    ): int {
        if (!$db->tableExists('production_wip')) return 0;

        $where = [
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => $fromProcessId,
            'to_process_id'   => $toProcessId,
            'source_table'    => $sourceTable,
            'source_id'       => $sourceId,
        ];

        $exist = $db->table('production_wip')->where($where)->get()->getRowArray();

        $payload = $where + [
            'qty'    => $qty,
            'status' => $status,
        ];

        // optional columns
        if ($qtyIn !== null && $db->fieldExists('qty_in', 'production_wip'))   $payload['qty_in'] = $qtyIn;
        if ($qtyOut !== null && $db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = $qtyOut;
        if ($stock !== null && $db->fieldExists('stock', 'production_wip'))   $payload['stock'] = $stock;

        $now = date('Y-m-d H:i:s');
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            // jangan overwrite kalau DONE (kecuali memang kita set DONE)
            if (($exist['status'] ?? '') === 'DONE' && $status !== 'DONE') {
                return (int)$exist['id'];
            }

            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
            return (int)$exist['id'];
        }

        if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
        $db->table('production_wip')->insert($payload);
        return (int)$db->insertID();
    }

    /* =====================================================
     * STORE SCHEDULE (PLAN)
     * - buat WIP DC->next = SCHEDULED (hari ini), qty_in=qty_p
     * - is_completed tetap 0
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Tidak ada data');
        }

        $today = date('Y-m-d');

        $db->transBegin();
        try {
            $processIdDC = $this->getProcessIdDieCasting($db);

            foreach ($items as $row) {
                if (empty($row['date']) || empty($row['shift_id']) || empty($row['machine_id'])) continue;

                $date      = (string)$row['date'];
                $shiftId   = (int)$row['shift_id'];
                $machineId = (int)$row['machine_id'];

                $productId = (int)($row['product_id'] ?? 0);
                $qtyP      = (int)($row['qty_p'] ?? 0);
                $statusRow = (string)($row['status'] ?? 'Normal');

                if ($productId <= 0 || $qtyP <= 0) continue;

                // wajib punya flow DC
                if (!$this->validateProductHasFlowDC($db, $productId, $processIdDC)) continue;

                // master product
                $product = $db->table('products')->select('id, part_name, cycle_time, cavity')->where('id', $productId)->get()->getRowArray();
                if (!$product) continue;

                $partLabel = (($product['part_name'] ?? '') ?: '-') . ' #1';

                // UPSERT die_casting_production (plan)
                $exist = $db->table('die_casting_production')
                    ->where(['production_date' => $date, 'shift_id' => $shiftId, 'machine_id' => $machineId])
                    ->get()->getRowArray();

                $now = date('Y-m-d H:i:s');

                if ($exist) {
                    $db->table('die_casting_production')->where('id', (int)$exist['id'])->update([
                        'product_id'   => $productId,
                        'qty_p'        => $qtyP,
                        'qty_a'        => 0,
                        'qty_ng'       => 0,
                        'status'       => $statusRow,
                        'part_label'   => $partLabel,
                        'process_id'   => $processIdDC,
                        'is_completed' => 0,
                    ]);
                    $sourceId = (int)$exist['id'];
                } else {
                    $db->table('die_casting_production')->insert([
                        'production_date' => $date,
                        'shift_id'        => $shiftId,
                        'machine_id'      => $machineId,
                        'product_id'      => $productId,
                        'part_label'      => $partLabel,
                        'qty_p'           => $qtyP,
                        'qty_a'           => 0,
                        'qty_ng'          => 0,
                        'status'          => $statusRow,
                        'process_id'      => $processIdDC,
                        'is_completed'    => 0,
                        'created_at'      => $now,
                    ]);
                    $sourceId = (int)$db->insertID();
                }

                // daily_schedules header (plan)
                $shiftRow = $db->table('shifts')->select('shift_name')->where('id', $shiftId)->get()->getRowArray();
                $section  = (string)($shiftRow['shift_name'] ?? 'DC');

                $dailyScheduleId = $this->upsertDailyScheduleHeader($db, $date, $processIdDC, $shiftId, $section);

                // daily_schedule_items detail
                $totalMinute = $this->getTotalMinuteShift($db, $shiftId);
                $hours = $totalMinute > 0 ? max(1, (int)ceil($totalMinute / 60)) : 1;

                $cycleTime = (int)($product['cycle_time'] ?? 0);
                $cavity    = (int)($product['cavity'] ?? 0);
                $targetPerShift = $qtyP;
                $targetPerHour  = (int)floor($qtyP / $hours);

                $this->upsertDailyScheduleItem(
                    $db,
                    $dailyScheduleId,
                    $shiftId,
                    $machineId,
                    $productId,
                    $cycleTime,
                    $cavity,
                    $targetPerHour,
                    $targetPerShift
                );

                // ===== WIP schedule: DC -> next (SCHEDULED hanya jika hari ini)
                $nextProcessId = $this->getNextProcessIdByFlow($db, $productId, $processIdDC);
                if ($nextProcessId) {
                    $wipStatus = ($date === $today) ? 'SCHEDULED' : 'WAITING';

                    $this->upsertProductionWip(
                        $db,
                        $date,
                        $productId,
                        $processIdDC,
                        $nextProcessId,
                        $qtyP,               // qty legacy
                        $wipStatus,
                        'die_casting_production',
                        $sourceId,
                        $qtyP,               // qty_in
                        0,                   // qty_out
                        0                    // stock
                    );
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Schedule DC tersimpan (WIP status SCHEDULED untuk hari ini).');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * FINISH SHIFT (dipanggil setelah shift berakhir)
     * - update qty_a dari hourly (kalau tabel hourly ada)
     * - ubah WIP DC->next jadi DONE, qty_out=qty_a, stock=0
     * - buat WIP next->nextNext status WAITING qty_in=qty_a
     * ===================================================== */
    public function finishShift()
    {
        $db = db_connect();

        $date    = (string)$this->request->getPost('date');
        $shiftId = (int)$this->request->getPost('shift_id');

        if (!$date || $shiftId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        $db->transBegin();
        try {
            $processIdDC = $this->getProcessIdDieCasting($db);

            // 1) Update qty_a/qty_ng dari hourly (jika ada)
            if ($db->tableExists('die_casting_hourly')) {
                $actuals = $db->table('die_casting_hourly')
                    ->select('machine_id, product_id, SUM(qty_fg) AS total_fg, SUM(qty_ng) AS total_ng')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->groupBy('machine_id, product_id')
                    ->get()->getResultArray();

                foreach ($actuals as $a) {
                    $db->table('die_casting_production')
                        ->where([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => (int)$a['machine_id'],
                            'product_id'      => (int)$a['product_id'],
                        ])
                        ->update([
                            'qty_a'        => (int)($a['total_fg'] ?? 0),
                            'qty_ng'       => (int)($a['total_ng'] ?? 0),
                            'is_completed' => 1,
                        ]);
                }
            } else {
                // kalau tidak ada hourly, tetap anggap finish (set completed)
                $db->table('die_casting_production')
                    ->where(['production_date' => $date, 'shift_id' => $shiftId])
                    ->set('is_completed', 1)
                    ->update();
            }

            // 2) Ambil semua production rows yang punya actual FG
            $rows = $db->table('die_casting_production')
                ->select('id, product_id, qty_p, qty_a')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->where('qty_p >', 0)
                ->get()->getResultArray();

            $processed = 0;

            foreach ($rows as $r) {
                $sourceId  = (int)$r['id'];
                $productId = (int)$r['product_id'];
                $qtyA      = (int)($r['qty_a'] ?? 0);

                if ($productId <= 0) continue;

                $nextProcessId = $this->getNextProcessIdByFlow($db, $productId, $processIdDC);
                if (!$nextProcessId) continue;

                // 3) Update WIP DC->next jadi DONE pakai qtyA
                //    - qty_out = qtyA
                //    - stock = 0 (karena langsung dipindah)
                //    - qty legacy = qtyA
                $wipId = $this->upsertProductionWip(
                    $db,
                    $date,
                    $productId,
                    $processIdDC,
                    $nextProcessId,
                    $qtyA,
                    'DONE',
                    'die_casting_production',
                    $sourceId,
                    null,
                    $qtyA,
                    0
                );

                // 4) Buat WIP next->nextNext status WAITING (schedule next process)
                //    qty_in = qtyA, qty=qtyA
                if ($qtyA > 0) {
                    $nextNextProcessId = $this->getNextProcessIdByFlow($db, $productId, $nextProcessId);
                    if ($nextNextProcessId) {
                        $this->upsertProductionWip(
                            $db,
                            $date,
                            $productId,
                            $nextProcessId,
                            $nextNextProcessId,
                            $qtyA,
                            'WAITING',
                            'production_wip',
                            $wipId ?: $sourceId,
                            $qtyA,
                            0,
                            0
                        );
                    }
                }

                $processed++;
            }

            // 5) daily_schedules -> completed
            if ($db->tableExists('daily_schedules')) {
                $db->table('daily_schedules')
                    ->where(['schedule_date' => $date, 'shift_id' => $shiftId])
                    ->set('is_completed', 1)
                    ->update();
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return $this->response->setJSON([
                'status'  => true,
                'message' => 'Finish shift berhasil. WIP DC->next jadi DONE, dan WIP next dibuat WAITING.',
                'count'   => $processed
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /* =========================
     * VIEW RESULT
     * ========================= */
public function view()
{
    $db   = db_connect();
    $date = $this->request->getGet('date') ?? date('Y-m-d');

    // Process Die Casting
    $processDC = $db->table('production_processes')
        ->select('id, process_name')
        ->where('process_name', 'Die Casting')
        ->get()->getRowArray();

    $processIdDC = (int)($processDC['id'] ?? 0);

    // Map process_name (buat next process)
    $processNameMap = [];
    $processes = $db->table('production_processes')->select('id, process_name')->get()->getResultArray();
    foreach ($processes as $pp) {
        $processNameMap[(int)$pp['id']] = $pp['process_name'];
    }

    // Helper: next process by flow (sequence + 1)
    $getNextProcessIdByFlow = function($productId, $currentProcessId) use ($db) {
        if (!$currentProcessId) return null;

        $cur = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => (int)$productId,
                'process_id' => (int)$currentProcessId,
                'is_active'  => 1
            ])->get()->getRowArray();

        if (!$cur) return null;

        $seq = (int)$cur['sequence'];

        $next = $db->table('product_process_flows')
            ->select('process_id')
            ->where([
                'product_id' => (int)$productId,
                'sequence'   => $seq + 1,
                'is_active'  => 1
            ])->get()->getRowArray();

        return $next ? (int)$next['process_id'] : null;
    };

    /**
     * INVENTORY DC:
     * ambil dari die_casting_production (plan/actual) + wip DC->next
     * key wip: source_table='die_casting_production' & source_id=dcp.id
     */
    $rows = $db->table('die_casting_production dcp')
        ->select('
            dcp.id AS dcp_id,
            dcp.production_date,
            dcp.shift_id,
            s.shift_name,

            dcp.machine_id,
            m.machine_code,
            m.line_position,

            dcp.product_id,
            p.part_no,
            COALESCE(dcp.part_label, p.part_name) AS part_name,

            dcp.qty_p,
            dcp.qty_a,
            dcp.qty_ng,

            pw.status AS wip_status,
            pw.qty AS wip_qty_legacy,
            pw.qty_in,
            pw.qty_out,
            pw.stock,
            pw.to_process_id
        ')
        ->join('shifts s', 's.id = dcp.shift_id', 'left')
        ->join('machines m', 'm.id = dcp.machine_id', 'left')
        ->join('products p', 'p.id = dcp.product_id', 'left')
        ->join(
            'production_wip pw',
            "pw.source_table = 'die_casting_production'
             AND pw.source_id = dcp.id
             AND pw.production_date = dcp.production_date",
            'left'
        )
        ->where('dcp.production_date', $date)
        ->orderBy('s.shift_name', 'ASC')
        ->orderBy('m.line_position', 'ASC')
        ->get()->getResultArray();

    // Tambahkan next process name (dari flow, fallback dari pw.to_process_id)
    foreach ($rows as &$r) {
        $productId = (int)($r['product_id'] ?? 0);

        $nextId = null;
        if ($processIdDC > 0 && $productId > 0) {
            $nextId = $getNextProcessIdByFlow($productId, $processIdDC);
        }
        // fallback kalau flow tidak ketemu, pakai to_process_id dari WIP
        if (!$nextId) {
            $nextId = (int)($r['to_process_id'] ?? 0);
        }

        $r['next_process_name'] = $nextId ? ($processNameMap[$nextId] ?? '-') : '-';

        // normalisasi angka inventory
        $r['qty_in']  = (int)($r['qty_in'] ?? 0);
        $r['qty_out'] = (int)($r['qty_out'] ?? 0);
        $r['stock']   = (int)($r['stock'] ?? 0);

        // status wip default
        $r['wip_status'] = $r['wip_status'] ?? '-';
    }
    unset($r);

    return view('die_casting/daily_schedule/inventory', [
        'date' => $date,
        'rows' => $rows,
    ]);
}


}
