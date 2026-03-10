<?php

namespace App\Controllers\WIP;

use App\Controllers\BaseController;

class WipInventoryController extends BaseController
{
    /* =====================================================
     * HELPERS
     * ===================================================== */

    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    private function detectColumn($db, string $table, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if ($db->fieldExists($col, $table)) return $col;
        }
        return null;
    }

    private function findProcessIdByCandidates($db, array $candidates): ?int
    {
        if (!$db->tableExists('production_processes')) return null;

        foreach ($candidates as $name) {
            $row = $db->table('production_processes')->select('id')
                ->where('process_name', $name)->get()->getRowArray();
            if ($row && !empty($row['id'])) return (int)$row['id'];
        }
        foreach ($candidates as $name) {
            $row = $db->table('production_processes')->select('id')
                ->like('process_name', $name)->get()->getRowArray();
            if ($row && !empty($row['id'])) return (int)$row['id'];
        }
        return null;
    }

    private function getDieCastProcessId($db): ?int
    {
        return $this->findProcessIdByCandidates($db, ['Die Casting', 'DIE CASTING', 'DIE CAST', 'DC']);
    }

    private function formatTitleDate(string $date): string
    {
        $ts = strtotime($date);
        if (!$ts) return $date;

        $bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
        $m = (int)date('n', $ts);
        return date('d', $ts) . ' ' . ($bulan[$m] ?? date('M',$ts)) . ' ' . date('Y', $ts);
    }

    /* =====================================================
     * OUT & NG SOURCES
     * ===================================================== */

    private function buildHourlySources($db): array
    {
        $map = [];

        $dieCastId = $this->getDieCastProcessId($db);
        if ($dieCastId) $map[$dieCastId] = ['table' => 'die_casting_hourly', 'qty_field' => 'qty_fg'];

        $machiningId   = $this->findProcessIdByCandidates($db, ['Machining', 'MACHINING', 'MC']);
        $leakTestId    = $this->findProcessIdByCandidates($db, ['Leak Test', 'LEAK TEST', 'LEAKTEST']);
        $assyShaftId   = $this->findProcessIdByCandidates($db, ['Assy Shaft', 'ASSY SHAFT']);
        $assyBushingId = $this->findProcessIdByCandidates($db, ['Assy Bushing', 'ASSY BUSHING']);

        if ($machiningId)   $map[$machiningId]   = ['table' => 'machining_hourly', 'qty_field' => 'qty_fg'];
        if ($leakTestId)    $map[$leakTestId]    = ['table' => 'machining_leak_test_hourly', 'qty_field' => 'qty_ok'];
        if ($assyShaftId)   $map[$assyShaftId]   = ['table' => 'machining_assy_shaft_hourly', 'qty_field' => 'qty_fg'];
        if ($assyBushingId) $map[$assyBushingId] = ['table' => 'machining_assy_bushing_hourly', 'qty_field' => 'qty_fg'];

        return $map;
    }

    private function buildNgDetailSources($db): array
    {
        $map = [];

        $dieCastId = $this->getDieCastProcessId($db);
        if ($dieCastId) $map[$dieCastId] = ['table' => 'die_casting_hourly_ng_details', 'qty_field' => 'qty_ng'];

        $machiningId   = $this->findProcessIdByCandidates($db, ['Machining', 'MACHINING', 'MC']);
        $assyShaftId   = $this->findProcessIdByCandidates($db, ['Assy Shaft', 'ASSY SHAFT']);
        $assyBushingId = $this->findProcessIdByCandidates($db, ['Assy Bushing', 'ASSY BUSHING']);

        if ($machiningId)   $map[$machiningId]   = ['table' => 'machining_hourly_ng_details', 'qty_field' => 'qty_ng'];
        if ($assyShaftId)   $map[$assyShaftId]   = ['table' => 'machining_assy_shaft_hourly_ng_details', 'qty_field' => 'qty_ng'];
        if ($assyBushingId) $map[$assyBushingId] = ['table' => 'machining_assy_bushing_hourly_ng_details', 'qty_field' => 'qty_ng'];

        return $map;
    }

    private function buildHourlyOutMap($db, string $date, array $productIds): array
    {
        $hourlySources = $this->buildHourlySources($db);
        $outMap = [];

        foreach ($hourlySources as $procId => $src) {
            $table = $src['table'];
            $qtyField = $src['qty_field'];

            if (!$db->tableExists($table)) continue;
            if (!$db->fieldExists('production_date', $table)) continue;
            if (!$db->fieldExists('shift_id', $table)) continue;
            if (!$db->fieldExists('product_id', $table)) continue;
            if (!$db->fieldExists($qtyField, $table)) continue;

            $q = $db->table($table)
                ->select("shift_id, product_id, SUM(COALESCE($qtyField,0)) as qty_sum")
                ->where('production_date', $date);

            if (!empty($productIds)) $q->whereIn('product_id', $productIds);

            $rows = $q->groupBy('shift_id, product_id')->get()->getResultArray();
            foreach ($rows as $r) {
                $sid = (int)($r['shift_id'] ?? 0);
                $pid = (int)($r['product_id'] ?? 0);
                $val = (int)($r['qty_sum'] ?? 0);
                if ($sid > 0 && $pid > 0) {
                    $outMap[(int)$procId][$sid][$pid] = ($outMap[(int)$procId][$sid][$pid] ?? 0) + $val;
                }
            }
        }

        return $outMap;
    }

    private function buildHourlyNgMap($db, string $date, array $productIds): array
    {
        $ngMap = [];

        $ngSources = $this->buildNgDetailSources($db);
        foreach ($ngSources as $procId => $src) {
            $table = $src['table'];
            $qtyField = $src['qty_field'] ?? 'qty_ng';

            if (!$db->tableExists($table)) continue;
            if (!$db->fieldExists('production_date', $table)) continue;
            if (!$db->fieldExists('shift_id', $table)) continue;
            if (!$db->fieldExists('product_id', $table)) continue;
            if (!$db->fieldExists($qtyField, $table)) continue;

            $q = $db->table($table)
                ->select("shift_id, product_id, SUM(COALESCE($qtyField,0)) as ng_sum")
                ->where('production_date', $date);

            if (!empty($productIds)) $q->whereIn('product_id', $productIds);

            $rows = $q->groupBy('shift_id, product_id')->get()->getResultArray();
            foreach ($rows as $r) {
                $sid = (int)($r['shift_id'] ?? 0);
                $pid = (int)($r['product_id'] ?? 0);
                $val = (int)($r['ng_sum'] ?? 0);
                if ($sid > 0 && $pid > 0) {
                    $ngMap[(int)$procId][$sid][$pid] = ($ngMap[(int)$procId][$sid][$pid] ?? 0) + $val;
                }
            }
        }

        $hourlySources = $this->buildHourlySources($db);
        foreach ($hourlySources as $procId => $src) {
            $procId = (int)$procId;
            $table = $src['table'];

            if (!$db->tableExists($table)) continue;
            if (!$db->fieldExists('production_date', $table)) continue;
            if (!$db->fieldExists('shift_id', $table)) continue;
            if (!$db->fieldExists('product_id', $table)) continue;

            $ngCol = $this->detectColumn($db, $table, ['qty_ng', 'ng', 'qty_reject', 'reject_qty', 'scrap_qty']);
            if (!$ngCol) continue;

            $q = $db->table($table)
                ->select("shift_id, product_id, SUM(COALESCE($ngCol,0)) as ng_sum")
                ->where('production_date', $date);

            if (!empty($productIds)) $q->whereIn('product_id', $productIds);

            $rows = $q->groupBy('shift_id, product_id')->get()->getResultArray();
            foreach ($rows as $r) {
                $sid = (int)($r['shift_id'] ?? 0);
                $pid = (int)($r['product_id'] ?? 0);
                $val = (int)($r['ng_sum'] ?? 0);
                if ($sid > 0 && $pid > 0) {
                    $ngMap[$procId][$sid][$pid] = ($ngMap[$procId][$sid][$pid] ?? 0) + $val;
                }
            }
        }

        return $ngMap;
    }

    /* =====================================================
     * SCHEDULE TOTAL
     * ===================================================== */

    private function buildScheduleInMap($db, string $date): array
    {
        $map = [];

        if (!$db->tableExists('daily_schedules') || !$db->tableExists('daily_schedule_items')) return $map;

        $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
        if (!$dateCol) return $map;

        $targetCol = $db->fieldExists('target_per_shift', 'daily_schedule_items') ? 'target_per_shift' : null;
        if (!$targetCol) return $map;

        if (!$db->fieldExists('process_id', 'daily_schedules')) return $map;
        if (!$db->fieldExists('shift_id', 'daily_schedules')) return $map;

        $rows = $db->table('daily_schedules ds')
            ->select("ds.process_id, ds.shift_id, dsi.product_id, SUM(COALESCE(dsi.$targetCol,0)) as qty_in")
            ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id', 'inner')
            ->where("ds.$dateCol", $date)
            ->groupBy('ds.process_id, ds.shift_id, dsi.product_id')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $procId  = (int)($r['process_id'] ?? 0);
            $shiftId = (int)($r['shift_id'] ?? 0);
            $pid     = (int)($r['product_id'] ?? 0);
            $val     = (int)($r['qty_in'] ?? 0);
            if ($procId > 0 && $shiftId > 0 && $pid > 0) {
                $map[$procId][$shiftId][$pid] = $val;
            }
        }

        return $map;
    }

    /* =====================================================
     * PREV PROCESS MAP (UNTUK WIP AWAL = TRANSFER PREV)
     * ===================================================== */

    private function buildPrevProcessMap($db, array $productIds): array
    {
        $map = [];
        if (empty($productIds)) return $map;
        if (!$db->tableExists('product_process_flows')) return $map;

        $rows = $db->table('product_process_flows')
            ->select('product_id, process_id, sequence')
            ->whereIn('product_id', $productIds)
            ->where('is_active', 1)
            ->orderBy('product_id', 'ASC')
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();

        $byProduct = [];
        foreach ($rows as $r) {
            $pid = (int)($r['product_id'] ?? 0);
            $proc = (int)($r['process_id'] ?? 0);
            $seq = (int)($r['sequence'] ?? 0);
            if ($pid > 0 && $proc > 0) {
                $byProduct[$pid][] = ['process_id' => $proc, 'sequence' => $seq];
            }
        }

        foreach ($byProduct as $pid => $list) {
            $procs = array_map(fn($x) => (int)$x['process_id'], $list);
            $n = count($procs);
            for ($i = 0; $i < $n; $i++) {
                $cur = $procs[$i];
                $prev = $procs[$i - 1] ?? null;
                if ($prev) {
                    $map[$pid][$cur] = (int)$prev;
                }
            }
        }

        return $map;
    }

    private function buildTransferMap($db, string $wipDateCol, string $date, array $processIds, array $productIds): array
    {
        $map = [];
        if (!$db->tableExists('production_wip')) return $map;

        $transferCol = $this->detectColumn($db, 'production_wip', ['transfer', 'qty_transfer', 'transfer_qty', 'buffer', 'buffer_qty']);
        if (!$transferCol) return $map;

        $q = $db->table('production_wip w')
            ->select("w.to_process_id as process_id, w.product_id, SUM(COALESCE(w.$transferCol,0)) as transfer_sum")
            ->where("w.$wipDateCol", $date);

        if (!empty($processIds)) $q->whereIn('w.to_process_id', $processIds);
        if (!empty($productIds)) $q->whereIn('w.product_id', $productIds);

        $rows = $q->groupBy('w.to_process_id, w.product_id')->get()->getResultArray();
        foreach ($rows as $r) {
            $procId = (int)($r['process_id'] ?? 0);
            $pid    = (int)($r['product_id'] ?? 0);
            $val    = (int)($r['transfer_sum'] ?? 0);
            if ($procId > 0 && $pid > 0) $map[$procId][$pid] = $val;
        }

        return $map;
    }

    /* =====================================================
     * INDEX
     * ===================================================== */

    public function index()
    {
        $db = db_connect();

        $role = (string)(session()->get('role') ?? '');
        $isAdmin = (strtoupper($role) === 'ADMIN');

        $date = $this->request->getGet('date') ?? date('Y-m-d');
        if (!$isAdmin) $date = date('Y-m-d');

        $tbl = 'production_wip';
        $wipDateCol = $this->detectWipDateColumn($db);

        $scheduleInMap = $this->buildScheduleInMap($db, $date);

        $pairs = [];
        if (!empty($scheduleInMap)) {
            foreach ($scheduleInMap as $procId => $shifts) {
                foreach ($shifts as $shiftId => $pids) {
                    foreach ($pids as $pid => $v) {
                        $pairs[$procId.'|'.$shiftId.'|'.$pid] = [
                            'process_id' => (int)$procId,
                            'shift_id'   => (int)$shiftId,
                            'product_id' => (int)$pid
                        ];
                    }
                }
            }
        }

        $pairs = array_values($pairs);

        if (empty($pairs)) {
            return view('wip/inventory/index', [
                'date' => $date,
                'rows' => [],
                'titleDate' => $this->formatTitleDate($date),
                'isAdmin' => $isAdmin,
            ]);
        }

        $processIds = [];
        $productIds = [];
        $shiftIds   = [];
        foreach ($pairs as $p) {
            $processIds[(int)$p['process_id']] = true;
            $productIds[(int)$p['product_id']] = true;
            $sid = (int)$p['shift_id'];
            if ($sid > 0) $shiftIds[$sid] = true;
        }
        $processIds = array_keys($processIds);
        $productIds = array_keys($productIds);
        $shiftIds   = array_keys($shiftIds);

        $processMap = [];
        $prows = $db->table('production_processes')
            ->select('id, process_name')
            ->whereIn('id', $processIds)
            ->get()->getResultArray();
        foreach ($prows as $r) $processMap[(int)$r['id']] = (string)($r['process_name'] ?? '');

        $shiftMap = [];
        if (!empty($shiftIds) && $db->tableExists('shifts')) {
            $srows = $db->table('shifts')
                ->select('id, shift_code, shift_name')
                ->whereIn('id', $shiftIds)
                ->get()->getResultArray();

            foreach ($srows as $r) {
                $id = (int)($r['id'] ?? 0);
                $name = (string)($r['shift_name'] ?? '');
                $code = (string)($r['shift_code'] ?? '');
                $shiftMap[$id] = $name !== '' ? $name : ($code !== '' ? $code : ('Shift '.$id));
            }
        }

        $productMap = [];
        $pRows = $db->table('products')
            ->select('id, part_no, part_name')
            ->whereIn('id', $productIds)
            ->get()->getResultArray();
        foreach ($pRows as $r) {
            $productMap[(int)$r['id']] = [
                'part_no' => (string)($r['part_no'] ?? ''),
                'part_name' => (string)($r['part_name'] ?? ''),
            ];
        }

        $hourlyOutMap = $this->buildHourlyOutMap($db, $date, $productIds);
        $hourlyNgMap = $this->buildHourlyNgMap($db, $date, $productIds);
        $transferMap = $this->buildTransferMap($db, $wipDateCol, $date, $processIds, $productIds);
        $prevMap = $this->buildPrevProcessMap($db, $productIds);

        // AMBIL STOCK & TRANSFER TERAKHIR UNTUK MENGHINDARI DOUBLE COUNTING (AKURASI TOTAL STOCK)
        $latestWipMap = [];
        if ($db->tableExists($tbl)) {
            $query = $db->table($tbl . ' w')
                ->select("w.to_process_id, w.product_id, w.stock, w.transfer")
                ->where("w.$wipDateCol <=", $date)
                ->where('w.id IN (
                    SELECT MAX(id) 
                    FROM production_wip 
                    WHERE '.$wipDateCol.' <= "'.$date.'" 
                    GROUP BY to_process_id, product_id
                )', null, false)
                ->get()
                ->getResultArray();

            foreach ($query as $r) {
                $procId = (int)$r['to_process_id'];
                $pid    = (int)$r['product_id'];
                $latestWipMap[$procId][$pid] = [
                    'stock'    => (int)$r['stock'],
                    'transfer' => (int)$r['transfer'],
                ];
            }
        }

        $rows = [];
        foreach ($pairs as $p) {
            $procId  = (int)$p['process_id'];
            $shiftId = (int)$p['shift_id'];
            $pid     = (int)$p['product_id'];

            $station = $processMap[$procId] ?? ('PROCESS '.$procId);
            $shiftLabel = $shiftMap[$shiftId] ?? ('Shift '.$shiftId);
            $pinfo   = $productMap[$pid] ?? ['part_no' => '', 'part_name' => ''];

            $prevProcId = (int)($prevMap[$pid][$procId] ?? 0);
            $wipAwal = ($prevProcId > 0) ? (int)($transferMap[$prevProcId][$pid] ?? 0) : 0;

            $qtyIn = (int)($scheduleInMap[$procId][$shiftId][$pid] ?? 0);
            $qtyOut = (int)($hourlyOutMap[$procId][$shiftId][$pid] ?? 0);
            $qtyNg  = (int)($hourlyNgMap[$procId][$shiftId][$pid] ?? 0);

            // MENGGUNAKAN PENGUKURAN ABSOLUT DARI DB ROW TERAKHIR
            $stock    = (int)($latestWipMap[$procId][$pid]['stock'] ?? 0);
            $transfer = (int)($latestWipMap[$procId][$pid]['transfer'] ?? 0);
            
            // Buffer/WIP Akhir adalah Stock Bebas + Stock Terjadwal
            $wipAkhir = $stock + $transfer;

            $rows[] = [
                'date' => $date,
                'shift' => $shiftLabel,
                'station' => $station,
                'part_no' => $pinfo['part_no'],
                'part_name' => $pinfo['part_name'],

                // KEYS YANG DIBUTUHKAN VIEW (.php HTML)
                'wip_awal' => $wipAwal,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'qty_ng' => $qtyNg,
                'wip_akhir' => $wipAkhir,
                'stock' => $stock,
                'transfer' => $transfer,
                'qty_in_schedule' => $qtyIn,
                'qty_in_transfer' => $wipAwal,
            ];
        }

        usort($rows, function($a, $b){
            $s1 = strcmp($a['shift'], $b['shift']);
            if ($s1 !== 0) return $s1;

            $s = strcmp($a['station'], $b['station']);
            if ($s !== 0) return $s;

            return strcmp($a['part_no'], $b['part_no']);
        });

        return view('wip/inventory/index', [
            'date' => $date,
            'rows' => $rows,
            'titleDate' => $this->formatTitleDate($date),
            'isAdmin' => $isAdmin,
        ]);
    }

    /* =====================================================
     * TOTAL STOCK ALL PRODUCTS
     * ===================================================== */
    public function totalStock()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        
        $role = (string)(session()->get('role') ?? '');
        $isAdmin = (strtoupper($role) === 'ADMIN');
        if (!$isAdmin) $date = date('Y-m-d');

        $wipDateCol = $this->detectWipDateColumn($db);
        $tbl = 'production_wip';

        $productData = [];

        if ($db->tableExists($tbl)) {
            $query = $db->table($tbl . ' w')
                ->select('w.product_id, p.part_no, p.part_name, pr.process_name, w.stock, w.transfer')
                ->join('products p', 'p.id = w.product_id', 'inner')
                ->join('production_processes pr', 'pr.id = w.to_process_id', 'left')
                ->where("w.$wipDateCol <=", $date)
                ->where('w.id IN (
                    SELECT MAX(id) 
                    FROM production_wip 
                    WHERE '.$wipDateCol.' <= "'.$date.'" 
                    GROUP BY to_process_id, product_id
                )', null, false)
                ->get()
                ->getResultArray();

            foreach ($query as $row) {
                $pid = $row['product_id'];
                
                if (!isset($productData[$pid])) {
                    $productData[$pid] = [
                        'part_no' => $row['part_no'],
                        'part_name' => $row['part_name'],
                        'total_stock' => 0,
                        'details' => []
                    ];
                }

                $stock = (int)$row['stock'];
                $transfer = (int)$row['transfer'];
                $totalQty = $stock + $transfer;
                
                if($totalQty > 0) {
                    $productData[$pid]['total_stock'] += $totalQty;
                    $productData[$pid]['details'][] = [
                        'process' => $row['process_name'] ?? 'Unknown Process',
                        'qty' => $totalQty
                    ];
                }
            }
        }

        $productData = array_filter($productData, function($item) {
            return $item['total_stock'] > 0;
        });

        usort($productData, function($a, $b) {
            return strcmp($a['part_no'], $b['part_no']);
        });

        return view('wip/inventory/total_stock', [
            'date'        => $date,
            'titleDate'   => $this->formatTitleDate($date),
            'isAdmin'     => $isAdmin,
            'productData' => $productData
        ]);
    }
}