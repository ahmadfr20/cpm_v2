<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    /* ============================================
     * Helper: ambil process_id Machining
     * ============================================ */
    private function getProcessIdMachining($db): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Machining')
            ->get()
            ->getRowArray();

        if (!$row) {
            throw new \Exception('Process "Machining" belum ada di master production_processes');
        }

        return (int)$row['id'];
    }

    /* ============================================
     * Helper: hitung total detik shift
     * ============================================ */
    private function getTotalSecondShift($db, int $shiftId): int
    {
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()
            ->getResultArray();

        $totalSecond = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400; // lewat tengah malam
            $totalSecond += ($end - $start);
        }

        return (int)$totalSecond;
    }

    /* ============================================
     * Helper: validasi product punya flow Machining aktif
     * ============================================ */
    private function validateProductHasFlow($db, int $productId, int $processId): bool
    {
        return $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('process_id', $processId)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /* =====================================================
     * Helper: resolve PREV process by flow (sequence < current)
     * ===================================================== */
    private function resolvePrevProcessByFlow($db, int $productId, int $currentProcessId): ?int
    {
        $currentFlow = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1,
            ])
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getRowArray();

        if (!$currentFlow) return null;

        $curSeq = (int)$currentFlow['sequence'];

        $prevFlow = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->where('sequence <', $curSeq)
            ->orderBy('sequence', 'DESC')
            ->get()
            ->getRowArray();

        if (!$prevFlow) return null;

        return (int)$prevFlow['process_id'];
    }

    /* =====================================================
     * UPSERT WIP incoming untuk schedule Machining:
     * prev -> Machining, source_table=daily_schedule_items, source_id=itemId
     *
     * REQUIREMENT USER:
     * - qty_p(plan) pada schedule harus masuk ke qty_in production_wip
     * - stock pada tampilan "IN/OUT/STOCK" tidak boleh kosong (jadi stock=qty_in)
     * - OUTGOING Machining->next TIDAK dibuat di schedule (dibuat saat finish shift hourly)
     * ===================================================== */
    private function upsertIncomingWipSchedule(
        $db,
        string $date,
        int $productId,
        int $prevProcessId,
        int $machiningProcessId,
        int $scheduleItemId,
        int $planQty
    ): void {
        if (!$db->tableExists('production_wip')) return;
        if ($prevProcessId <= 0) return; // kalau Machining proses pertama, tidak ada incoming

        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        // $hasStock  = $db->fieldExists('stock', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

        $now = date('Y-m-d H:i:s');

        $key = [
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => $prevProcessId,
            'to_process_id'   => $machiningProcessId,
            'source_table'    => 'daily_schedule_items',
            'source_id'       => $scheduleItemId,
        ];

        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

        // Schedule = rencana masuk ke Machining
        $payload = $key + [
            'qty'    => $planQty,      // legacy qty, isi sama dengan plan
            'status' => 'SCHEDULED',
        ];

        // IMPORTANT: qty_in HARUS terisi dari plan
        if ($hasQtyIn)  $payload['qty_in'] = $planQty;

        // belum ada output saat schedule
        if ($hasQtyOut) $payload['qty_out'] = 0;

        // agar "Stock" tidak kosong pada view inventory, isi = plan (stock awal = masuk)
        // if ($hasStock)  $payload['stock'] = $planQty;

        if ($hasUpdatedAt) $payload['updated_at'] = $now;

        if ($exist) {
            // kalau sudah DONE jangan ditimpa oleh schedule
            if (($exist['status'] ?? '') !== 'DONE') {
                $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
            }
            return;
        }

        if ($hasCreatedAt) $payload['created_at'] = $now;
        $db->table('production_wip')->insert($payload);
    }

    /* ============================================
     * INDEX
     * ============================================ */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date');
        if (empty($date)) {
        $date = date('Y-m-d', strtotime('+1 day'));
        }

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Machining')
            ->get()
            ->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        $actualMap = [];
        if ($db->tableExists('machining_hourly')) {
            $actuals = $db->table('machining_hourly')
                ->select('shift_id, machine_id, product_id, SUM(qty_fg) act, SUM(qty_ng) ng')
                ->where('production_date', $date)
                ->groupBy('shift_id, machine_id, product_id')
                ->get()
                ->getResultArray();

            foreach ($actuals as $a) {
                $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
            }
        }

        return view('machining/schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

        /* ============================================
     * AJAX: daftar incoming WIP untuk Machining
     * - ambil produk dari production_wip yang to_process_id = Machining
     * - qty diambil dari qty_in (wajib)
     * ============================================ */
    public function incomingWip()
    {
        $db   = db_connect();
        $date = (string)($this->request->getGet('date') ?? date('Y-m-d'));

        if (!$db->tableExists('production_wip')) {
            return $this->response->setJSON(['status' => false, 'message' => 'production_wip tidak ada']);
        }

        $mcProcessId = $this->getProcessIdMachining($db);

        $hasQtyIn = $db->fieldExists('qty_in', 'production_wip');
        if (!$hasQtyIn) {
            return $this->response->setJSON(['status' => false, 'message' => 'Kolom qty_in tidak ada di production_wip']);
        }

        // Ambil aggregated per product (total qty_in)
        $rows = $db->table('production_wip pw')
            ->select('
                pw.product_id,
                SUM(COALESCE(pw.qty_in,0)) AS qty_available,
                p.part_no,
                p.part_name
            ')
            ->join('products p', 'p.id = pw.product_id')
            ->where('pw.production_date', $date)
            ->where('pw.to_process_id', $mcProcessId)
            ->where('pw.status', 'WAITING')
            ->groupBy('pw.product_id, p.part_no, p.part_name')
            ->having('qty_available > 0')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        $data = array_map(function ($r) {
            return [
                'product_id' => (int)$r['product_id'],
                'part_no'    => (string)$r['part_no'],
                'part_name'  => (string)$r['part_name'],
                'qty'        => (int)$r['qty_available'],
            ];
        }, $rows);

        return $this->response->setJSON(['status' => true, 'data' => $data]);
    }

    /* ============================================
     * AJAX: assign incoming WIP ke schedule Machining
     * - upsert daily_schedules + daily_schedule_items
     * - kurangi qty_in di production_wip sesuai qty assign
     * - qty_out di WIP bertambah, status DONE bila habis
     * ============================================ */
    public function assignIncomingWipBulk()
    {
        $db = db_connect();
        $role  = session()->get('role') ?? '';
        $today = date('Y-m-d');

        if ($role !== 'ADMIN' && $date <= $today) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh mulai besok.'
            ]);
        }


        $date      = (string)$this->request->getPost('date');
        $shiftId   = (int)$this->request->getPost('shift_id');
        $itemsJson = (string)$this->request->getPost('items');

        if ($date === '' || $shiftId <= 0 || $itemsJson === '') {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        $items = json_decode($itemsJson, true);
        if (!$items || !is_array($items)) {
            return $this->response->setJSON(['status' => false, 'message' => 'Format items tidak valid']);
        }

        $db->transBegin();
        try {
            $mcProcessId = $this->getProcessIdMachining($db);

            if (!$db->tableExists('production_wip')) {
                throw new \Exception('production_wip tidak ada');
            }

            $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
            $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
            $hasStock     = $db->fieldExists('stock', 'production_wip');
            $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

            if (!$hasQtyIn) throw new \Exception('Kolom qty_in tidak ada di production_wip');

            $now = date('Y-m-d H:i:s');

            // header schedule per shift (dibuat sekali)
            $schedule = $db->table('daily_schedules')
                ->where([
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Machining',
                ])
                ->get()->getRowArray();

            if (!$schedule) {
                $insertHeader = [
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Machining',
                    'is_completed'  => 0,
                    'created_at'    => $now,
                ];
                if ($db->fieldExists('process_id', 'daily_schedules')) $insertHeader['process_id'] = $mcProcessId;
                if ($db->fieldExists('updated_at', 'daily_schedules')) $insertHeader['updated_at'] = $now;

                $db->table('daily_schedules')->insert($insertHeader);
                $scheduleId = (int)$db->insertID();
            } else {
                $scheduleId = (int)$schedule['id'];
            }

            foreach ($items as $it) {
                $productId = (int)($it['product_id'] ?? 0);
                $machineId = (int)($it['machine_id'] ?? 0);
                $qty       = (int)($it['qty'] ?? 0);

                if ($productId <= 0 || $machineId <= 0 || $qty <= 0) continue;

                if (!$this->validateProductHasFlow($db, $productId, $mcProcessId)) {
                    throw new \Exception("Product ID {$productId} tidak punya flow Machining aktif");
                }

                // ambil WIP WAITING incoming ke Machining untuk product tsb
                $wipRows = $db->table('production_wip')
                    ->where('production_date', $date)
                    ->where('to_process_id', $mcProcessId)
                    ->where('product_id', $productId)
                    ->where('status', 'WAITING')
                    ->orderBy('id', 'ASC')
                    ->get()->getResultArray();

                if (!$wipRows) throw new \Exception("WIP incoming habis untuk product {$productId}");

                $available = 0;
                foreach ($wipRows as $w) $available += (int)($w['qty_in'] ?? 0);
                if ($qty > $available) throw new \Exception("Qty {$qty} > available {$available} (product {$productId})");

                // upsert schedule item PER machine+product (target += qty)
                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'shift_id'          => $shiftId,
                        'machine_id'        => $machineId,
                        'product_id'        => $productId,
                    ])
                    ->get()->getRowArray();

                if ($existItem) {
                    $db->table('daily_schedule_items')
                        ->where('id', (int)$existItem['id'])
                        ->update([
                            'target_per_shift' => (int)($existItem['target_per_shift'] ?? 0) + $qty,
                            'is_selected'      => 1
                        ]);
                } else {
                    $product = $db->table('products')
                        ->select('cycle_time, cavity, efficiency_rate')
                        ->where('id', $productId)
                        ->get()->getRowArray();

                    $cycle  = (int)($product['cycle_time'] ?? 0);
                    $cavity = (int)($product['cavity'] ?? 0);
                    $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                    $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                    $targetPerHour = 0;
                    if ($cycle > 0 && $cavity > 0) {
                        $targetPerHour = (int)floor((3600 / $cycle) * $cavity * $eff);
                    }

                    $db->table('daily_schedule_items')->insert([
                        'daily_schedule_id' => $scheduleId,
                        'shift_id'          => $shiftId,
                        'machine_id'        => $machineId,
                        'product_id'        => $productId,
                        'cycle_time'        => $cycle,
                        'cavity'            => $cavity,
                        'target_per_hour'   => $targetPerHour,
                        'target_per_shift'  => $qty,
                        'is_selected'       => 1
                    ]);
                }

                // kurangi qty_in WIP (FIFO)
                $remain = $qty;
                foreach ($wipRows as $w) {
                    if ($remain <= 0) break;

                    $rowId = (int)$w['id'];
                    $saldo = (int)($w['qty_in'] ?? 0);
                    if ($saldo <= 0) continue;

                    $take = min($saldo, $remain);

                    $update = [
                        'qty_in' => $saldo - $take,
                        'status' => (($saldo - $take) <= 0) ? 'DONE' : 'WAITING',
                    ];

                    if ($hasQtyOut) $update['qty_out'] = (int)($w['qty_out'] ?? 0) + $take;

                    if ($hasStock) {
                        $update['stock'] = (int)($w['stock'] ?? 0) - $take;
                        if ($update['stock'] < 0) $update['stock'] = 0;
                    }

                    if ($hasUpdatedAt) $update['updated_at'] = $now;

                    $db->table('production_wip')->where('id', $rowId)->update($update);
                    $remain -= $take;
                }

                if ($remain > 0) throw new \Exception("Gagal kurangi WIP untuk product {$productId}");
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();
            return $this->response->setJSON(['status' => true]);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }



    /* ============================================
     * AJAX: PRODUCT + TARGET (FLOW saja)
     * ============================================ */
    public function getProductAndTarget()
    {
        $db      = db_connect();
        $shiftId = (int)$this->request->getGet('shift_id');

        if ($shiftId <= 0) return $this->response->setJSON([]);

        $processIdMC = $this->getProcessIdMachining($db);

        $totalSecond = $this->getTotalSecondShift($db, $shiftId);
        if ($totalSecond <= 0) return $this->response->setJSON([]);

        $products = $db->table('product_process_flows ppf')
            ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
            ->join('products p', 'p.id = ppf.product_id')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdMC)
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($products as &$p) {
            $cycle  = (int)($p['cycle_time'] ?? 0);
            $cavity = (int)($p['cavity'] ?? 0);

            $effRaw = (float)($p['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            if ($cycle > 0 && $cavity > 0) {
                $targetShift = floor(($totalSecond / $cycle) * $cavity * $eff);
                $targetHour  = floor((3600 / $cycle) * $cavity * $eff);

                $p['target_per_shift'] = min((int)$targetShift, 1200);
                $p['target_per_hour']  = (int)$targetHour;
            } else {
                $p['target_per_shift'] = 0;
                $p['target_per_hour']  = 0;
            }
        }
        unset($p);

        return $this->response->setJSON($products);
    }

    /* ============================================
     * STORE
     * - Simpan schedule Machining
     * - Buat/Update WIP incoming (prev -> Machining) dengan qty_in = plan
     * - JANGAN buat WIP outgoing (Machining -> next) di sini
     * ============================================ */
    public function store()
    {
        $db    = db_connect();
        $date  = trim((string)$this->request->getPost('date'));
        $items = $this->request->getPost('items');

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        // =====================================================
        // ✅ RULE ROLE + TANGGAL
        // ADMIN boleh hari ini, non-admin hanya besok dst
        // =====================================================
        $role  = session()->get('role') ?? '';
        $today = date('Y-m-d'); // pastikan App timezone = Asia/Jakarta

        if ($role !== 'ADMIN' && $date <= $today) {
            return redirect()->back()->with(
                'error',
                'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh membuat schedule mulai besok.'
            );
        }

        $db->transBegin();

        try {
            $processIdMC = $this->getProcessIdMachining($db);
            $now = date('Y-m-d H:i:s');

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                $planInput = (int)($row['plan'] ?? ($row['target_per_shift'] ?? 0));
                if ($planInput < 0) $planInput = 0;
                if ($planInput > 1200) $planInput = 1200;
                if ($planInput <= 0) continue;

                if (!$this->validateProductHasFlow($db, $productId, $processIdMC)) continue;

                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();
                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                if ($cycle <= 0 || $cavity <= 0) continue;

                $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                $targetPerHour = (int)floor((3600 / $cycle) * $cavity * $eff);

                // ===== DAILY SCHEDULE HEADER =====
                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                    ])
                    ->get()
                    ->getRowArray();

                if (!$schedule) {
                    $insertHeader = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Machining',
                        'is_completed'  => 0,
                        'created_at'    => $now
                    ];
                    if ($db->fieldExists('process_id', 'daily_schedules')) {
                        $insertHeader['process_id'] = $processIdMC;
                    }
                    if ($db->fieldExists('updated_at', 'daily_schedules')) {
                        $insertHeader['updated_at'] = $now;
                    }

                    $db->table('daily_schedules')->insert($insertHeader);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];

                    // optional: pastikan process_id terisi
                    if ($db->fieldExists('process_id', 'daily_schedules') && empty($schedule['process_id'])) {
                        $upd = ['process_id' => $processIdMC];
                        if ($db->fieldExists('updated_at', 'daily_schedules')) $upd['updated_at'] = $now;
                        $db->table('daily_schedules')->where('id', $scheduleId)->update($upd);
                    }
                }

                // ===== DAILY SCHEDULE ITEM UPSERT =====
                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'machine_id'        => $machineId
                    ])
                    ->get()
                    ->getRowArray();

                $dataItem = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $planInput,
                    'is_selected'       => 1
                ];

                if ($existItem) {
                    $db->table('daily_schedule_items')
                        ->where('id', (int)$existItem['id'])
                        ->update($dataItem);
                    $itemId = (int)$existItem['id'];
                } else {
                    $db->table('daily_schedule_items')->insert($dataItem);
                    $itemId = (int)$db->insertID();
                }

                /**
                 * WIP incoming: prev -> Machining (qty_in = plan)
                 */
                $prevProcessId = $this->resolvePrevProcessByFlow($db, $productId, $processIdMC);
                if ($prevProcessId) {
                    $this->upsertIncomingWipSchedule(
                        $db,
                        $date,
                        $productId,
                        $prevProcessId,
                        $processIdMC,
                        $itemId,
                        $planInput
                    );
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->back()->with(
                'success',
                'Schedule Machining tersimpan.'
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

}
