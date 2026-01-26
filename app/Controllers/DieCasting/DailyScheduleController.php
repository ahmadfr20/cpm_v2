<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // SHIFT Die Casting
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        // Total menit per shift
        foreach ($shifts as &$shift) {
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->get()
                ->getResultArray();

            $totalMinute = 0;
            foreach ($slots as $s) {
                $start = strtotime($s['time_start']);
                $end   = strtotime($s['time_end']);
                if ($end <= $start) $end += 86400; // lintas hari
                $totalMinute += ($end - $start) / 60;
            }
            $shift['total_minute'] = (int)$totalMinute;
        }
        unset($shift);

        // Mesin Die Casting
        $machines = $db->table('machines')
            ->where('production_line', 'Die Casting')
            ->orderBy('line_position')
            ->get()
            ->getResultArray();

        // Existing schedule (die_casting_production) untuk date tsb
        $existing = $db->table('die_casting_production')
            ->where('production_date', $date)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($existing as $e) {
            $map[$e['shift_id']][$e['machine_id']] = $e;
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
        $q = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Die Casting')
            ->get();

        if ($q === false) {
            throw new \Exception('Query production_processes gagal: ' . $db->error()['message']);
        }

        $row = $q->getRowArray();
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
            $totalMinute += ($end - $start) / 60;
        }

        return (int)$totalMinute;
    }

    /* =====================================================
     * AJAX: Produk + target (berdasarkan flow)
     * ===================================================== */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId = (int) $this->request->getGet('machine_id');
        $shiftId   = (int) $this->request->getGet('shift_id');

        if ($machineId <= 0 || $shiftId <= 0) {
            return $this->response->setJSON([]);
        }

        $processIdDC = $this->getProcessIdDieCasting($db);
        $totalMinute = $this->getTotalMinuteShift($db, $shiftId);

        // Produk dari production flow (aktif) + filter machine_products
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
            ->join('machine_products mp', 'mp.product_id = p.id AND mp.machine_id = ' . (int)$machineId . ' AND mp.is_active = 1', 'inner')
            ->where('ppf.is_active', 1)
            ->where('p.is_active', 1)
            ->where('ppf.process_id', $processIdDC)
            ->where('ppf.sequence', 1) // kalau DC bukan proses pertama, hapus baris ini
            ->groupBy('p.id')
            ->orderBy('p.part_no', 'ASC')
            ->get()
            ->getResultArray();

        // Hitung target default (untuk auto-fill plan pertama kali)
        foreach ($products as &$p) {
            $cycle  = (int) ($p['cycle_time'] ?? 0);
            $cavity = (int) ($p['cavity'] ?? 0);

            $effRaw = (float) ($p['efficiency_rate'] ?? 100.0);
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
     * UPSERT daily_schedules (HEADER)
     * key: schedule_date + process_id + shift_id + section
     * ===================================================== */
    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        $builder = $db->table('daily_schedules');

        $q = $builder->where([
                'schedule_date' => $date,
                'process_id'    => $processId,
                'shift_id'      => $shiftId,
                'section'       => $section,
            ])
            ->get();

        if ($q === false) {
            throw new \Exception('Query daily_schedules gagal: ' . $db->error()['message']);
        }

        $exist = $q->getRowArray();

        $now = date('Y-m-d H:i:s');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'daily_schedules');

        if ($exist) {
            $data = [
                'is_completed' => 1,
            ];
            if ($hasUpdatedAt) $data['updated_at'] = $now;

            $builder->where('id', (int)$exist['id'])->update($data);
            return (int)$exist['id'];
        }

        $insert = [
            'schedule_date' => $date,
            'process_id'    => $processId,
            'shift_id'      => $shiftId,
            'section'       => $section,
            'is_completed'  => 1,
            'created_at'    => $now,
        ];
        if ($hasUpdatedAt) $insert['updated_at'] = $now;

        $builder->insert($insert);
        return (int)$db->insertID();
    }

    /* =====================================================
     * UPSERT daily_schedule_items (DETAIL)
     * 1 item "selected" per mesin per daily_schedule_id
     *
     * NOTE: signature = 10 args (biar tidak error "too few arguments")
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
        int $targetPerShift,
        int $isSelected
    ): int
    {
        // non-aktifkan semua item mesin ini dulu (biar cuma 1 yg selected)
        $db->table('daily_schedule_items')
            ->where([
                'daily_schedule_id' => $dailyScheduleId,
                'machine_id'        => $machineId,
            ])
            ->set('is_selected', 0)
            ->update();

        $q = $db->table('daily_schedule_items')
            ->where([
                'daily_schedule_id' => $dailyScheduleId,
                'machine_id'        => $machineId,
                'product_id'        => $productId,
            ])
            ->get();

        if ($q === false) {
            throw new \Exception('Query daily_schedule_items gagal: ' . $db->error()['message']);
        }

        $exist = $q->getRowArray();

        $payload = [
            'daily_schedule_id' => $dailyScheduleId,
            'shift_id'          => $shiftId,
            'machine_id'        => $machineId,
            'product_id'        => $productId,
            'cycle_time'        => $cycleTime,
            'cavity'            => $cavity,
            'target_per_hour'   => $targetPerHour,
            'target_per_shift'  => $targetPerShift,
            'is_selected'       => $isSelected,
        ];

        if ($exist) {
            $db->table('daily_schedule_items')->where('id', (int)$exist['id'])->update($payload);
            return (int)$exist['id'];
        }

        $db->table('daily_schedule_items')->insert($payload);
        return (int)$db->insertID();
    }

    /* =====================================================
     * STORE (Schedule -> die_casting_production + daily_schedules + daily_schedule_items)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Tidak ada data');
        }

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
                $qtyA      = (int)($row['qty_a'] ?? 0);
                $qtyNG     = (int)($row['qty_ng'] ?? 0);
                $status    = (string)($row['status'] ?? 'Normal');

                if ($productId <= 0) continue;
                if ($qtyP <= 0) continue; // plan wajib

                // master product
                $product = $db->table('products')->where('id', $productId)->get()->getRowArray();
                if (!$product) continue;

                $partLabel = (($product['part_name'] ?? '') ?: '-') . ' #1';

                // ===== UPSERT die_casting_production (1 row per date+shift+machine) =====
                $exist = $db->table('die_casting_production')
                    ->where([
                        'production_date' => $date,
                        'shift_id'        => $shiftId,
                        'machine_id'      => $machineId,
                    ])
                    ->get()
                    ->getRowArray();

                if ($exist) {
                    $db->table('die_casting_production')
                        ->where('id', (int)$exist['id'])
                        ->update([
                            'product_id'   => $productId,
                            'qty_p'        => $qtyP,
                            'qty_a'        => $qtyA,
                            'qty_ng'       => $qtyNG,
                            'status'       => $status,
                            'part_label'   => $partLabel,
                            'process_id'   => $processIdDC,
                            'is_completed' => 1,
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
                        'qty_a'           => $qtyA,
                        'qty_ng'          => $qtyNG,
                        'status'          => $status,
                        'process_id'      => $processIdDC,
                        'is_completed'    => 1,
                        'created_at'      => date('Y-m-d H:i:s'),
                    ]);
                    $sourceId = (int)$db->insertID();
                }

                // ===== INSERT/UPDATE ke tabel global schedule =====
                // daily_schedules header (tanpa machine_id!)
                $shiftRow = $db->table('shifts')->select('shift_name')->where('id', $shiftId)->get()->getRowArray();
                $section  = (string)($shiftRow['shift_name'] ?? 'Die Casting');

                $dailyScheduleId = $this->upsertDailyScheduleHeader(
                    $db,
                    $date,
                    $processIdDC,
                    $shiftId,
                    $section
                );

                // daily_schedule_items detail per mesin
                $totalMinute = $this->getTotalMinuteShift($db, $shiftId);
                $hours = $totalMinute > 0 ? max(1, (int)ceil($totalMinute / 60)) : 1;

                $cycleTime = (int)($product['cycle_time'] ?? 0);
                $cavity    = (int)($product['cavity'] ?? 0);

                // karena user input PLAN = qty_p, simpan sebagai target_per_shift
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
                    $targetPerShift,
                    1
                );

                // CREATE NEXT PROCESS WIP (mengikuti flow)
                $this->createNextProcessWIP($date, $productId, $qtyP, $processIdDC, $sourceId);
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Schedule DC tersimpan + masuk daily_schedules');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * CREATE NEXT PROCESS (WIP) - mengikuti flow aktif
     * ===================================================== */
    private function createNextProcessWIP($date, $productId, $qty, $currentProcessId, $sourceId)
    {
        $db = db_connect();

        $flows = $db->table('product_process_flows')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getResultArray();

        if (!$flows) return;

        $currentIndex = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === (int)$currentProcessId) {
                $currentIndex = $i;
                break;
            }
        }

        if ($currentIndex === null || !isset($flows[$currentIndex + 1])) return;

        $nextProcessId = (int)$flows[$currentIndex + 1]['process_id'];

        $exist = $db->table('production_wip')
            ->where([
                'production_date' => $date,
                'product_id'      => $productId,
                'from_process_id' => $currentProcessId,
                'to_process_id'   => $nextProcessId,
                'source_table'    => 'die_casting_production',
                'source_id'       => $sourceId,
            ])
            ->get()
            ->getRowArray();

        if ($exist) return;

        $db->table('production_wip')->insert([
            'production_date' => $date,
            'product_id'      => $productId,
            'from_process_id' => $currentProcessId,
            'to_process_id'   => $nextProcessId,
            'qty'             => $qty,
            'source_table'    => 'die_casting_production',
            'source_id'       => $sourceId,
            'status'          => 'WAITING',
            'created_at'      => date('Y-m-d H:i:s')
        ]);
    }

    public function view()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $rows = $db->table('die_casting_production dcp')
            ->select('
                s.shift_name,
                m.machine_code,
                m.line_position,
                p.part_no,
                COALESCE(dcp.part_label, p.part_name) AS part_name,
                p.weight_ascas,
                p.weight_runner,
                dcp.qty_p,
                dcp.qty_a,
                dcp.qty_ng,
                dcp.status
            ')
            ->join('shifts s', 's.id = dcp.shift_id')
            ->join('machines m', 'm.id = dcp.machine_id')
            ->join('products p', 'p.id = dcp.product_id')
            ->where('dcp.production_date', $date)
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        return view('die_casting/daily_schedule/view', [
            'date' => $date,
            'rows' => $rows
        ]);
    }
}
