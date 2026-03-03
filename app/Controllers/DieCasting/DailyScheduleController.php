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
        $date = $this->request->getGet('date');
        if (empty($date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }
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
                p.part_prod,
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
     * UPSERT DAILY_SCHEDULES (HEADER)
     * ===================================================== */
    private function upsertDailyScheduleHeader($db, string $date, int $processId, int $shiftId, string $section): int
    {
        if (!$db->tableExists('daily_schedules')) return 0;

        $where = [
            'schedule_date' => $date,
            'process_id'    => $processId,
            'shift_id'      => $shiftId,
            'section'       => $section,
        ];

        $exist = $db->table('daily_schedules')->where($where)->get()->getRowArray();
        $now   = date('Y-m-d H:i:s');

        if ($exist) {
            $update = [
                'is_completed' => 0,
            ];
            if ($db->fieldExists('updated_at', 'daily_schedules')) $update['updated_at'] = $now;

            $db->table('daily_schedules')->where('id', (int)$exist['id'])->update($update);
            return (int)$exist['id'];
        }

        $payload = $where + [
            'is_completed' => 0,
        ];
        if ($db->fieldExists('created_at', 'daily_schedules')) $payload['created_at'] = $now;
        if ($db->fieldExists('updated_at', 'daily_schedules')) $payload['updated_at'] = $now;

        $db->table('daily_schedules')->insert($payload);
        return (int)$db->insertID();
    }

    /* =====================================================
     * REBUILD DAILY_SCHEDULE_ITEMS
     * ===================================================== */
    private function rebuildDailyScheduleItems($db, int $dailyScheduleId, array $itemsToInsert): void
    {
        if ($dailyScheduleId <= 0) return;
        if (!$db->tableExists('daily_schedule_items')) return;

        $db->table('daily_schedule_items')->where('daily_schedule_id', $dailyScheduleId)->delete();

        foreach ($itemsToInsert as $it) {
            $db->table('daily_schedule_items')->insert($it);
        }
    }

    /* =====================================================
     * STORE SCHEDULE (PLAN)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Tidak ada data');
        }

        $role  = session()->get('role') ?? '';
        $today = date('Y-m-d'); 

        $firstRow = reset($items);
        $formDate = isset($firstRow['date']) ? trim((string)$firstRow['date']) : '';

        if ($formDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formDate)) {
            return redirect()->back()->with('error', 'Tanggal schedule tidak valid.');
        }

        if ($role !== 'ADMIN' && $formDate <= $today) {
            return redirect()->back()->with(
                'error',
                'Hanya ADMIN yang boleh membuat schedule untuk hari ini. Selain ADMIN hanya boleh membuat schedule mulai besok.'
            );
        }

        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            $processIdDC = $this->getProcessIdDieCasting($db);
            $dailyGroup = [];

            foreach ($items as $row) {
                if (empty($row['date']) || empty($row['shift_id']) || empty($row['machine_id'])) {
                    continue;
                }

                $date      = trim((string)$row['date']);
                $shiftId   = (int)$row['shift_id'];
                $machineId = (int)$row['machine_id'];

                if ($date !== $formDate) {
                    continue;
                }

                $productId = (int)($row['product_id'] ?? 0);
                $qtyP      = (int)($row['qty_p'] ?? 0);
                $statusRow = (string)($row['status'] ?? 'Normal');

                if ($productId <= 0 || $qtyP <= 0) continue;
                if (!$this->validateProductHasFlowDC($db, $productId, $processIdDC)) continue;

                $product = $db->table('products')
                    ->select('id, part_name, cycle_time, cavity')
                    ->where('id', $productId)
                    ->get()->getRowArray();
                if (!$product) continue;

                $cycle  = (int)($product['cycle_time'] ?? 0);
                $cavity = (int)($product['cavity'] ?? 0);
                $partLabel = (($product['part_name'] ?? '') ?: '-') . ' #1';

                $exist = $db->table('die_casting_production')
                    ->where([
                        'production_date' => $date,
                        'shift_id'        => $shiftId,
                        'machine_id'      => $machineId
                    ])
                    ->get()->getRowArray();

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

                $wipStatus = ($date === $today) ? 'SCHEDULED' : 'WAITING';

                $this->upsertProductionWip(
                    $db,
                    $date,
                    $productId,
                    $processIdDC,
                    $processIdDC,
                    $qtyP,                 
                    $wipStatus,
                    'die_casting_production',
                    $sourceId,
                    $qtyP,                 
                    0,                     
                    0                      
                );

                $gKey = $date . '_' . $shiftId;
                if (!isset($dailyGroup[$gKey])) {
                    $dailyGroup[$gKey] = [
                        'date'     => $date,
                        'shift_id' => $shiftId,
                        'items'    => [],
                    ];
                }

                $totalMinute = $this->getTotalMinuteShift($db, $shiftId);
                $hours = ($totalMinute > 0) ? ($totalMinute / 60) : 0;
                $targetPerHour = ($hours > 0) ? (int)ceil($qtyP / $hours) : 0;

                $dailyGroup[$gKey]['items'][] = [
                    'daily_schedule_id' => 0,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle > 0 ? $cycle : 0,
                    'cavity'            => $cavity > 0 ? $cavity : 0,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $qtyP,
                    'is_selected'       => 1,
                ];
            }

            foreach ($dailyGroup as $g) {
                $date    = (string)$g['date'];
                $shiftId = (int)$g['shift_id'];
                $section = 'Die Casting';

                $headerId = $this->upsertDailyScheduleHeader($db, $date, $processIdDC, $shiftId, $section);
                if ($headerId <= 0) continue;

                $itemsToInsert = [];
                foreach ($g['items'] as $it) {
                    $it['daily_schedule_id'] = $headerId;
                    $itemsToInsert[] = $it;
                }

                $this->rebuildDailyScheduleItems($db, $headerId, $itemsToInsert);
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Schedule DC tersimpan + Daily Schedules & Items ter-update.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }


    /* =====================================================
     * FINISH SHIFT
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

            $actuals = [];
            if ($db->tableExists('die_casting_hourly')) {
                $actuals = $db->table('die_casting_hourly')
                    ->select('machine_id, product_id, SUM(qty_fg) AS total_fg, SUM(qty_ng) AS total_ng')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->groupBy('machine_id, product_id')
                    ->get()->getResultArray();
            }

            $actMap = [];
            foreach ($actuals as $a) {
                $key = (int)$a['machine_id'] . '_' . (int)$a['product_id'];
                $actMap[$key] = [
                    'fg' => (int)($a['total_fg'] ?? 0),
                    'ng' => (int)($a['total_ng'] ?? 0),
                ];
            }

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

                $key   = $machineId . '_' . $productId;
                $qtyA  = (int)($actMap[$key]['fg'] ?? 0);
                $qtyNG = (int)($actMap[$key]['ng'] ?? 0);

                $db->table('die_casting_production')
                    ->where('id', $sourceId)
                    ->update([
                        'qty_a'        => $qtyA,
                        'qty_ng'       => $qtyNG,
                        'is_completed' => 1,
                    ]);

                $this->upsertProductionWip(
                    $db, $date, $productId, $processIdDC, $processIdDC, $qtyA,
                    'DONE', 'die_casting_production', $sourceId, null, $qtyA, 0
                );

                $nextProcessId = $this->getNextProcessIdByFlow($db, $productId, $processIdDC);
                if ($nextProcessId && $qtyA > 0) {
                    $this->upsertProductionWip(
                        $db, $date, $productId, $processIdDC, $nextProcessId, $qtyA,
                        'WAITING', 'die_casting_production', $sourceId, $qtyA, 0, $qtyA
                    );
                }

                $processed++;
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return $this->response->setJSON([
                'status'  => true,
                'message' => 'Finish shift OK. WIP DC selesai dan qtyA masuk ke proses berikutnya sebagai IN/Stock.',
                'count'   => $processed
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /* =====================================================
     * INVENTORY STOCK (Die Casting Only)
     * ===================================================== */
    public function inventory()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        
        $role = (string)(session()->get('role') ?? '');
        $isAdmin = (strtoupper($role) === 'ADMIN');
        if (!$isAdmin) $date = date('Y-m-d');

        // Format tanggal untuk tampilan
        $ts = strtotime($date);
        $bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
        $m = (int)date('n', $ts);
        $titleDate = date('d', $ts) . ' ' . ($bulan[$m] ?? date('M',$ts)) . ' ' . date('Y', $ts);

        $processIdDC = $this->getProcessIdDieCasting($db);

        // Cari tahu nama kolom stock & date di production_wip
        $tbl = 'production_wip';
        $wipDateCol = $db->fieldExists('wip_date', $tbl) ? 'wip_date' : 
                     ($db->fieldExists('schedule_date', $tbl) ? 'schedule_date' : 'production_date');
        
        $colStock = 'stock';
        foreach (['stock', 'stock_qty', 'qty_stock'] as $col) {
            if ($db->fieldExists($col, $tbl)) {
                $colStock = $col; break;
            }
        }

        $productData = [];

        if ($db->tableExists($tbl)) {
            // Ambil max stock terakhir khusus untuk process Die Casting pada tanggal tsb
            $query = $db->table($tbl . ' w')
                ->select('w.product_id, p.part_no, p.part_name, w.'.$colStock.' as current_stock')
                ->join('products p', 'p.id = w.product_id', 'inner')
                ->where("w.$wipDateCol <=", $date)
                ->where('w.to_process_id', $processIdDC)
                // Subquery ambil row terakhir (id terbesar) per product
                ->where('w.id IN (
                    SELECT MAX(id) 
                    FROM production_wip 
                    WHERE '.$wipDateCol.' <= "'.$date.'" 
                    AND to_process_id = '.$processIdDC.'
                    GROUP BY product_id
                )', null, false)
                ->get()
                ->getResultArray();

            foreach ($query as $row) {
                $qty = (int)$row['current_stock'];
                
                // Tampilkan hanya jika stok lebih dari 0
                if($qty > 0) {
                    $productData[] = [
                        'part_no'     => $row['part_no'],
                        'part_name'   => $row['part_name'],
                        'total_stock' => $qty,
                    ];
                }
            }
        }

        // Urutkan berdasarkan Part No
        usort($productData, function($a, $b) {
            return strcmp($a['part_no'], $b['part_no']);
        });

        return view('die_casting/daily_schedule/inventory', [
            'date'        => $date,
            'titleDate'   => $titleDate,
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}