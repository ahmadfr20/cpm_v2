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
            return $seq[1] ?? null;
        }

        return $seq[$idx + 1] ?? null;
    }

    /* =====================================================
     * UPSERT WIP (support qty_in/qty_out/stock)
     * ===================================================== */
    private function upsertProductionWip(
        $db,
        string $date,
        int $productId,
        int $fromProcessId,
        int $toProcessId,
        int $qty,
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

        if ($qtyIn !== null && $db->fieldExists('qty_in', 'production_wip'))   $payload['qty_in'] = $qtyIn;
        if ($qtyOut !== null && $db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = $qtyOut;
        if ($stock !== null && $db->fieldExists('stock', 'production_wip'))   $payload['stock'] = $stock;

        $now = date('Y-m-d H:i:s');
        if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

        if ($exist) {
            // kalau DONE jangan dioverwrite kecuali memang set DONE
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
     * - HANYA buat WIP "IN ke DIE CASTING"
     * - TIDAK mengirim ke proses berikutnya (Machining)
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
                $product = $db->table('products')
                    ->select('id, part_name, cycle_time, cavity')
                    ->where('id', $productId)
                    ->get()->getRowArray();
                if (!$product) continue;

                $partLabel = (($product['part_name'] ?? '') ?: '-') . ' #1';

                // UPSERT die_casting_production (plan)
                $exist = $db->table('die_casting_production')
                    ->where([
                        'production_date' => $date,
                        'shift_id'        => $shiftId,
                        'machine_id'      => $machineId
                    ])
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

                /**
                 * ✅ WIP untuk DIE CASTING SAJA (IN masuk ke DC)
                 * Supaya di inventory, kolom DIE CASTING - IN terisi.
                 *
                 * from_process_id = DC
                 * to_process_id   = DC
                 * qty_in          = qty plan
                 * stock           = 0 (stock TIDAK pakai plan)
                 *
                 * status: SCHEDULED kalau hari ini, else WAITING
                 */
                $wipStatus = ($date === $today) ? 'SCHEDULED' : 'WAITING';

                $this->upsertProductionWip(
                    $db,
                    $date,
                    $productId,
                    $processIdDC,
                    $processIdDC,
                    $qtyP,                 // qty legacy
                    $wipStatus,
                    'die_casting_production',
                    $sourceId,
                    $qtyP,                 // qty_in = PLAN
                    0,                     // qty_out
                    0                      // stock selalu 0 (stock pakai qtyA di finish)
                );
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Schedule DC tersimpan. WIP hanya masuk ke DIE CASTING (tidak ke proses berikutnya).');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * FINISH SHIFT
     * - qtyA diambil dari hourly (SUM qty_fg)
     * - DC WIP: qty_out = qtyA, stock = 0, DONE
     * - buat WIP ke proses berikutnya: qty_in = qtyA, stock = qtyA, WAITING
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

            // 1) Hitung actual dari hourly
            $actuals = [];
            if ($db->tableExists('die_casting_hourly')) {
                $actuals = $db->table('die_casting_hourly')
                    ->select('machine_id, product_id, SUM(qty_fg) AS total_fg, SUM(qty_ng) AS total_ng')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->groupBy('machine_id, product_id')
                    ->get()->getResultArray();
            }

            // Map actual: [machine_id_product_id] => fg/ng
            $actMap = [];
            foreach ($actuals as $a) {
                $key = (int)$a['machine_id'] . '_' . (int)$a['product_id'];
                $actMap[$key] = [
                    'fg' => (int)($a['total_fg'] ?? 0),
                    'ng' => (int)($a['total_ng'] ?? 0),
                ];
            }

            // 2) Ambil semua rows schedule DC pada shift tsb
            $rows = $db->table('die_casting_production')
                ->select('id, machine_id, product_id, qty_p')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->where('qty_p >', 0)
                ->get()->getResultArray();

            $processed = 0;

            foreach ($rows as $r) {
                $sourceId  = (int)$r['id'];
                $machineId = (int)$r['machine_id'];
                $productId = (int)$r['product_id'];

                if ($sourceId <= 0 || $productId <= 0) continue;

                $key = $machineId . '_' . $productId;
                $qtyA = (int)($actMap[$key]['fg'] ?? 0);
                $qtyNG = (int)($actMap[$key]['ng'] ?? 0);

                /**
                 * Update die_casting_production actual
                 */
                $db->table('die_casting_production')
                    ->where('id', $sourceId)
                    ->update([
                        'qty_a'        => $qtyA,
                        'qty_ng'       => $qtyNG,
                        'is_completed' => 1,
                    ]);

                /**
                 * ✅ A) Update WIP DIE CASTING (DC -> DC)
                 * qty_out = qtyA (actual)
                 * stock   = 0
                 * status  = DONE
                 */
                $this->upsertProductionWip(
                    $db,
                    $date,
                    $productId,
                    $processIdDC,
                    $processIdDC,
                    $qtyA, // qty legacy pakai actual
                    'DONE',
                    'die_casting_production',
                    $sourceId,
                    null,  // qty_in biarkan existing (plan) => jangan override
                    $qtyA, // qty_out = actual
                    0      // stock = 0
                );

                /**
                 * ✅ B) Create/Update WIP ke proses berikutnya (DC -> NEXT)
                 * qty_in = qtyA
                 * stock  = qtyA (ini yang akan muncul sebagai STOCK di proses next)
                 * status = WAITING (karena next proses belum diproses)
                 */
                $nextProcessId = $this->getNextProcessIdByFlow($db, $productId, $processIdDC);
                if ($nextProcessId && $qtyA > 0) {
                    $this->upsertProductionWip(
                        $db,
                        $date,
                        $productId,
                        $processIdDC,
                        $nextProcessId,
                        $qtyA,
                        'WAITING',
                        'die_casting_production',
                        $sourceId,
                        $qtyA,   // qty_in
                        0,      // qty_out
                        $qtyA   // stock = actual qtyA
                    );
                }

                $processed++;
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return $this->response->setJSON([
                'status'  => true,
                'message' => 'Finish shift OK. WIP DC selesai (qty_out=qtyA, stock=0) dan qtyA masuk ke proses berikutnya sebagai IN/Stock.',
                'count'   => $processed
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }
}
