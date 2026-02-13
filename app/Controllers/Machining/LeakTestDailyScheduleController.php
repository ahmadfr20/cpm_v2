<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class LeakTestDailyScheduleController extends BaseController
{
    /* =====================================================
     * ✅ GUARD: role & tanggal (untuk endpoint JSON)
     * - ADMIN: boleh hari ini
     * - selain ADMIN: hanya boleh besok dst (date > today)
     * ===================================================== */
    private function guardScheduleDateByRoleJson(string $date): ?\CodeIgniter\HTTP\ResponseInterface
    {
        $role = session()->get('role') ?? '';

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d) {
            return $this->response->setJSON(['status' => false, 'message' => 'Format tanggal tidak valid']);
        }
        $d->setTime(0, 0, 0);

        $today = new \DateTime(date('Y-m-d'));
        $today->setTime(0, 0, 0);

        if ($role !== 'ADMIN' && $d <= $today) {
            return $this->response->setJSON([
                'status'  => false,
                'message' => 'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh mulai besok.'
            ]);
        }

        return null;
    }

    /* =====================================================
     * ✅ GUARD: role & tanggal (untuk endpoint redirect)
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

    public function index()
    {
        $db = db_connect();

        // ✅ default besok jika date kosong / invalid
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

        // Mesin Leak Test: kamu pakai mesin machining (tetap)
        $machines = $db->table('machines m')
            ->select('m.id, m.machine_code, m.machine_name, m.line_position')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position')
            ->get()->getResultArray();

        $existing = $db->table('daily_schedule_items dsi')
            ->select('ds.shift_id, dsi.machine_id, dsi.product_id, dsi.cycle_time, dsi.target_per_shift')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Leak Test')
            ->get()->getResultArray();

        $planMap = [];
        foreach ($existing as $e) {
            $planMap[$e['shift_id'] . '_' . $e['machine_id']] = $e;
        }

        $actuals = $db->table('machining_leak_test_hourly')
            ->select('shift_id, machine_id, product_id, SUM(qty_ok) act, SUM(qty_ng) ng')
            ->where('production_date', $date)
            ->groupBy('shift_id, machine_id, product_id')
            ->get()->getResultArray();

        $actualMap = [];
        foreach ($actuals as $a) {
            $actualMap[$a['shift_id'] . '_' . $a['machine_id'] . '_' . $a['product_id']] = $a;
        }

        return view('machining/leak_test_schedule/index', [
            'date'      => $date,
            'shifts'    => $shifts,
            'machines'  => $machines,
            'planMap'   => $planMap,
            'actualMap' => $actualMap
        ]);
    }

    /**
     * Detect kolom tanggal yang dipakai di production_wip
     */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    /**
     * GET /machining/leak-test/schedule/incoming-wip?date=YYYY-MM-DD
     * Ambil WIP incoming (prev -> Leak Test) status WAITING, qty > 0
     */
    public function incomingWip()
    {
        $db   = db_connect();
        $date = (string)($this->request->getGet('date') ?? '');

        if ($date === '') {
            return $this->response->setJSON(['status' => false, 'message' => 'date kosong']);
        }

        try {
            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT')
                ?? $this->getProcessIdByNameLike($db, 'LEAK TEST');

            if (!$leakTestProcessId) {
                return $this->response->setJSON(['status' => false, 'message' => 'Process Leak Test tidak ditemukan']);
            }

            $wipDateCol = $this->detectWipDateColumn($db);

            $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
            $hasStock  = $db->fieldExists('stock', 'production_wip');
            $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');

            $rows = $db->table('production_wip pw')
                ->select('
                    pw.id,
                    pw.product_id,
                    pw.from_process_id,
                    pw.to_process_id,
                    pw.qty,
                    '.($hasQtyIn  ? 'pw.qty_in,'  : '0 AS qty_in,').'
                    '.($hasQtyOut ? 'pw.qty_out,' : '0 AS qty_out,').'
                    '.($hasStock  ? 'pw.stock,'   : '0 AS stock,').'
                    pw.status,
                    p.part_no,
                    p.part_name
                ')
                ->join('products p', 'p.id = pw.product_id')
                ->where($wipDateCol, $date)
                ->where('pw.to_process_id', $leakTestProcessId)
                ->where('pw.status', 'WAITING')
                ->orderBy('p.part_no', 'ASC')
                ->get()->getResultArray();

            $data = [];
            foreach ($rows as $r) {
                $qty     = (int)($r['qty'] ?? 0);
                $qtyIn   = (int)($r['qty_in'] ?? 0);
                $stock   = (int)($r['stock'] ?? 0);

                // available: stock > 0 ? stock : qty_in > 0 ? qty_in : qty
                $avail = 0;
                if ($hasStock && $stock > 0) $avail = $stock;
                else if ($hasQtyIn && $qtyIn > 0) $avail = $qtyIn;
                else $avail = $qty;

                if ($avail <= 0) continue;

                $data[] = [
                    'wip_id'    => (int)$r['id'],
                    'product_id'=> (int)$r['product_id'],
                    'part_no'   => (string)$r['part_no'],
                    'part_name' => (string)$r['part_name'],
                    'avail'     => $avail,
                    'from_process_id' => (int)($r['from_process_id'] ?? 0),
                ];
            }

            return $this->response->setJSON([
                'status' => true,
                'data'   => $data
            ]);

        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => false,
                'message' => 'Server error: '.$e->getMessage()
            ]);
        }
    }

    /**
     * POST /machining/leak-test/schedule/assign-incoming-wip
     * Payload: date, shift_id, machine_id, product_id, qty, wip_id
     */
    public function assignIncomingWip()
    {
        $db = db_connect();

        $date      = (string)($this->request->getPost('date') ?? '');
        $shiftId   = (int)($this->request->getPost('shift_id') ?? 0);
        $machineId = (int)($this->request->getPost('machine_id') ?? 0);
        $productId = (int)($this->request->getPost('product_id') ?? 0);
        $qty       = (int)($this->request->getPost('qty') ?? 0);
        $wipId     = (int)($this->request->getPost('wip_id') ?? 0);

        if ($date === '' || $shiftId <= 0 || $machineId <= 0 || $productId <= 0 || $qty <= 0 || $wipId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        // ✅ guard role + tanggal (JSON)
        $deny = $this->guardScheduleDateByRoleJson($date);
        if ($deny) return $deny;

        $db->transBegin();

        try {
            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT')
                ?? $this->getProcessIdByNameLike($db, 'LEAK TEST');

            if (!$leakTestProcessId) throw new \Exception('Process Leak Test tidak ditemukan');

            $wipDateCol = $this->detectWipDateColumn($db);

            $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
            $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
            $hasStock  = $db->fieldExists('stock', 'production_wip');
            $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
            $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');

            $now = date('Y-m-d H:i:s');

            // 1) ambil row incoming untuk validasi avail + prev process
            $incoming = $db->table('production_wip')->where('id', $wipId)->get()->getRowArray();
            if (!$incoming) throw new \Exception('Incoming WIP tidak ditemukan');

            $prevProcessId = (int)($incoming['from_process_id'] ?? 0);
            if ($prevProcessId <= 0) throw new \Exception('Prev process pada incoming WIP tidak valid');

            $curQty   = (int)($incoming['qty'] ?? 0);
            $curIn    = $hasQtyIn ? (int)($incoming['qty_in'] ?? 0) : 0;
            $curStock = $hasStock ? (int)($incoming['stock'] ?? 0) : 0;

            $avail = 0;
            if ($hasStock && $curStock > 0) $avail = $curStock;
            else if ($hasQtyIn && $curIn > 0) $avail = $curIn;
            else $avail = $curQty;

            if ($qty > $avail) throw new \Exception("Qty melebihi available ({$avail})");

            // 2) upsert header daily_schedules (Leak Test)
            $schedule = $db->table('daily_schedules')
                ->where([
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Leak Test',
                ])->get()->getRowArray();

            if (!$schedule) {
                $header = [
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Leak Test',
                    'is_completed'  => 0,
                    'created_at'    => $now,
                ];
                if ($db->fieldExists('process_id', 'daily_schedules')) $header['process_id'] = $leakTestProcessId;
                if ($db->fieldExists('updated_at', 'daily_schedules')) $header['updated_at'] = $now;

                $db->table('daily_schedules')->insert($header);
                $scheduleId = (int)$db->insertID();
            } else {
                $scheduleId = (int)$schedule['id'];
            }

            // 3) ambil master product untuk CT/cavity/eff → target_per_hour
            $product = $db->table('products')
                ->select('cycle_time, cavity, efficiency_rate')
                ->where('id', $productId)
                ->get()->getRowArray();
            if (!$product) throw new \Exception('Product master tidak ditemukan');

            $cycle  = (int)($product['cycle_time'] ?? 0);
            $cavity = (int)($product['cavity'] ?? 0);
            $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
            $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

            $targetPerHour = ($cycle > 0 && $cavity > 0)
                ? (int)floor((3600 / $cycle) * $cavity * $eff)
                : 0;

            // 4) upsert daily_schedule_items (per mesin)
            $existItem = $db->table('daily_schedule_items')
                ->where([
                    'daily_schedule_id' => $scheduleId,
                    'machine_id'        => $machineId,
                ])->get()->getRowArray();

            $dataItem = [
                'daily_schedule_id' => $scheduleId,
                'shift_id'          => $shiftId,
                'machine_id'        => $machineId,
                'product_id'        => $productId,
                'cycle_time'        => $cycle,
                'cavity'            => $cavity,
                'target_per_hour'   => $targetPerHour,
                'target_per_shift'  => min($qty, 1200),
            ];
            if ($db->fieldExists('is_selected', 'daily_schedule_items')) $dataItem['is_selected'] = 1;

            if ($existItem) {
                $db->table('daily_schedule_items')->where('id', (int)$existItem['id'])->update($dataItem);
                $itemId = (int)$existItem['id'];
            } else {
                $db->table('daily_schedule_items')->insert($dataItem);
                $itemId = (int)$db->insertID();
            }

            // 5) buat/update WIP inbound untuk schedule (source daily_schedule_items)
            $keyScheduleWip = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $leakTestProcessId,
                'source_table'    => 'daily_schedule_items',
                'source_id'       => $itemId,
            ];

            $existScheduleWip = $db->table('production_wip')->where($keyScheduleWip)->get()->getRowArray();

            $payloadScheduleWip = [
                'status' => 'SCHEDULED',
                'qty'    => min($qty, 1200),
            ];
            if ($hasQtyIn)  $payloadScheduleWip['qty_in']  = min($qty, 1200);
            if ($hasQtyOut) $payloadScheduleWip['qty_out'] = 0;
            if ($hasStock)  $payloadScheduleWip['stock']   = 0;
            if ($hasUpdatedAt) $payloadScheduleWip['updated_at'] = $now;

            if ($existScheduleWip) {
                if (strtoupper((string)($existScheduleWip['status'] ?? '')) !== 'DONE') {
                    $db->table('production_wip')->where('id', (int)$existScheduleWip['id'])->update($payloadScheduleWip);
                }
            } else {
                if ($hasCreatedAt) $payloadScheduleWip['created_at'] = $now;
                $db->table('production_wip')->insert($keyScheduleWip + $payloadScheduleWip);
            }

            // 6) kurangi available dari WIP incoming (wip_id)
            $updIncoming = [];
            if ($hasStock && $curStock > 0) {
                $newStock = max(0, $curStock - $qty);
                $updIncoming['stock'] = $newStock;
                if ($newStock === 0) $updIncoming['status'] = 'DONE';
            } elseif ($hasQtyIn && $curIn > 0) {
                $newIn = max(0, $curIn - $qty);
                $updIncoming['qty_in'] = $newIn;
                if ($newIn === 0) $updIncoming['status'] = 'DONE';
            } else {
                $newQty = max(0, $curQty - $qty);
                $updIncoming['qty'] = $newQty;
                if ($newQty === 0) $updIncoming['status'] = 'DONE';
            }

            if ($hasUpdatedAt) $updIncoming['updated_at'] = $now;
            $db->table('production_wip')->where('id', $wipId)->update($updIncoming);

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return $this->response->setJSON(['status' => true, 'message' => 'Assign berhasil']);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = (int) ($this->request->getGet('machine_id') ?? 0);
        $shiftId   = (int) ($this->request->getGet('shift_id') ?? 0);

        if (!$machineId || !$shiftId) {
            return $this->jsonFail('machine_id / shift_id kosong', 400);
        }

        try {
            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT');
            if (!$leakTestProcessId) {
                $leakTestProcessId = $this->getProcessIdByNameLike($db, 'LEAK TEST');
            }
            if (!$leakTestProcessId) {
                return $this->jsonFail('Process Leak Test tidak ditemukan (cek production_processes)', 404);
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

            $products = $db->table('machine_products mp')
                ->distinct()
                ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
                ->join('products p', 'p.id = mp.product_id')
                ->join('product_process_flows ppf', 'ppf.product_id = p.id', 'inner')
                ->where('mp.machine_id', $machineId)
                ->where('mp.is_active', 1)
                ->where('p.is_active', 1)
                ->where('ppf.process_id', $leakTestProcessId)
                ->orderBy('p.part_no')
                ->get()->getResultArray();

            if (empty($products)) {
                $products = $db->table('products p')
                    ->distinct()
                    ->select('p.id, p.part_no, p.part_name, p.cycle_time, p.cavity, p.efficiency_rate')
                    ->join('product_process_flows ppf', 'ppf.product_id = p.id', 'inner')
                    ->where('p.is_active', 1)
                    ->where('ppf.process_id', $leakTestProcessId)
                    ->orderBy('p.part_no')
                    ->get()->getResultArray();
            }

            foreach ($products as &$p) {
                $cycle  = (int) $p['cycle_time'];
                $cavity = (int) $p['cavity'];
                $eff    = ((float) $p['efficiency_rate']) / 100;

                $p['cycle_time'] = $cycle;
                $p['target'] = ($cycle > 0 && $cavity > 0 && $totalSecond > 0)
                    ? min((int) floor(($totalSecond / $cycle) * $cavity * $eff), 1200)
                    : 0;
            }

            return $this->response->setJSON([
                'ok'      => true,
                'items'   => $products,
                'message' => empty($products) ? 'Tidak ada produk dengan flow Leak Test' : 'OK'
            ]);

        } catch (\Throwable $e) {
            return $this->jsonFail('Server error: ' . $e->getMessage(), 500);
        }
    }

    public function store()
    {
        $db    = db_connect();
        $date  = trim((string)($this->request->getPost('date') ?? ''));
        $items = $this->request->getPost('items');

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        // ✅ guard role + tanggal (redirect)
        $deny = $this->guardScheduleDateByRoleRedirect($date);
        if ($deny) return $deny;

        $db->transBegin();

        try {
            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT')
                ?? $this->getProcessIdByNameLike($db, 'LEAK TEST');

            if (!$leakTestProcessId) {
                throw new \RuntimeException('Process Leak Test tidak ditemukan (cek production_processes)');
            }

            $now = date('Y-m-d H:i:s');

            foreach ($items as $row) {
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0) continue;

                $planQty = (int)($row['plan'] ?? $row['qty_p'] ?? $row['qtyP'] ?? $row['target_per_shift'] ?? 0);
                if ($planQty < 0) $planQty = 0;
                if ($planQty > 1200) $planQty = 1200;
                if ($planQty <= 0) continue;

                $hasFlow = $db->table('product_process_flows')
                    ->where([
                        'product_id' => $productId,
                        'process_id' => $leakTestProcessId,
                        'is_active'  => 1
                    ])
                    ->countAllResults();

                if (!$hasFlow) continue;

                $product = $db->table('products')
                    ->select('cycle_time, cavity, efficiency_rate')
                    ->where('id', $productId)
                    ->get()->getRowArray();
                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                if ($cycle <= 0 || $cavity <= 0) continue;

                $effRaw = (float)($product['efficiency_rate'] ?? 100.0);
                $eff    = $effRaw > 0 ? ($effRaw / 100.0) : 1.0;

                $targetPerHour = (int)floor((3600 / $cycle) * $cavity * $eff);

                $schedule = $db->table('daily_schedules')
                    ->where([
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Leak Test',
                    ])
                    ->get()->getRowArray();

                if (!$schedule) {
                    $header = [
                        'schedule_date' => $date,
                        'shift_id'      => $shiftId,
                        'section'       => 'Leak Test',
                        'is_completed'  => 0,
                        'created_at'    => $now,
                    ];
                    if ($db->fieldExists('process_id', 'daily_schedules')) $header['process_id'] = $leakTestProcessId;
                    if ($db->fieldExists('updated_at', 'daily_schedules')) $header['updated_at'] = $now;

                    $db->table('daily_schedules')->insert($header);
                    $scheduleId = (int)$db->insertID();
                } else {
                    $scheduleId = (int)$schedule['id'];

                    if ($db->fieldExists('process_id', 'daily_schedules') && empty($schedule['process_id'])) {
                        $upd = ['process_id' => $leakTestProcessId];
                        if ($db->fieldExists('updated_at', 'daily_schedules')) $upd['updated_at'] = $now;
                        $db->table('daily_schedules')->where('id', $scheduleId)->update($upd);
                    }
                }

                $existItem = $db->table('daily_schedule_items')
                    ->where([
                        'daily_schedule_id' => $scheduleId,
                        'machine_id'        => $machineId,
                    ])
                    ->get()->getRowArray();

                $dataItem = [
                    'daily_schedule_id' => $scheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $planQty,
                ];
                if ($db->fieldExists('is_selected', 'daily_schedule_items')) $dataItem['is_selected'] = 1;

                if ($existItem) {
                    $db->table('daily_schedule_items')->where('id', (int)$existItem['id'])->update($dataItem);
                    $itemId = (int)$existItem['id'];
                } else {
                    $db->table('daily_schedule_items')->insert($dataItem);
                    $itemId = (int)$db->insertID();
                }

                // ===== WIP INBOUND (prev -> LT) qty_in = planQty =====
                $flow = $this->getFlowPrevNext($db, $productId, $leakTestProcessId);
                if (empty($flow['sequence'])) continue;

                $prevProcessId = (int)($flow['prev'] ?? 0);
                if ($prevProcessId <= 0) continue;

                $wipDateCol = $this->detectWipDateColumn($db);

                $key = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevProcessId,
                    'to_process_id'   => $leakTestProcessId,
                    'source_table'    => 'daily_schedule_items',
                    'source_id'       => $itemId,
                ];

                $payload = [
                    'status' => 'SCHEDULED',
                    'qty'    => $planQty,
                ];
                if ($db->fieldExists('qty_in', 'production_wip'))  $payload['qty_in']  = $planQty;
                if ($db->fieldExists('qty_out', 'production_wip')) $payload['qty_out'] = 0;
                if ($db->fieldExists('stock', 'production_wip'))   $payload['stock']   = 0;
                if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

                $existWip = $db->table('production_wip')->where($key)->get()->getRowArray();
                if ($existWip) {
                    if (($existWip['status'] ?? '') !== 'DONE') {
                        $db->table('production_wip')->where('id', (int)$existWip['id'])->update($payload);
                    }
                } else {
                    if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
                    $db->table('production_wip')->insert($key + $payload);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily schedule Leak Test tersimpan. Non-admin hanya boleh mulai besok.');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ======================= HELPERS =======================

    private function jsonFail(string $message, int $status = 400)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'      => false,
            'items'   => [],
            'message' => $message
        ]);
    }

    private function getProcessIdByCode($db, string $code): ?int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_code', $code)
            ->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    private function getProcessIdByNameLike($db, string $name): ?int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->like('process_name', $name)
            ->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    private function getFlowPrevNext($db, int $productId, int $currentProcessId): array
    {
        $cur = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'process_id' => $currentProcessId])
            ->get()->getRowArray();

        if (!$cur) return ['prev' => null, 'next' => null, 'sequence' => null];

        $seq = (int) $cur['sequence'];

        $prev = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'sequence' => $seq - 1])
            ->get()->getRowArray();

        $next = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'sequence' => $seq + 1])
            ->get()->getRowArray();

        return [
            'prev'     => $prev ? (int) $prev['process_id'] : null,
            'next'     => $next ? (int) $next['process_id'] : null,
            'sequence' => $seq
        ];
    }

    private function upsertWip($db, array $key, array $data): void
    {
        $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

        if ($exist) {
            $db->table('production_wip')->where('id', $exist['id'])->update($data);
        } else {
            $db->table('production_wip')->insert($key + $data + [
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
