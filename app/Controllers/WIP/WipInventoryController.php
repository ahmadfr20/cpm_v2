<?php

namespace App\Controllers\WIP;

use App\Controllers\BaseController;

class WipInventoryController extends BaseController
{
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

    /**
     * process_id -> hourly table & qty field (OUT harian)
     */
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

    /**
     * ✅ OUT harian dari hourly (akumulasi output per jam)
     * return map: [process_id][product_id] => qty_out
     */
    private function buildHourlyOutMap($db, string $date, array $productIds): array
    {
        $hourlySources = $this->buildHourlySources($db);
        $outMap = [];

        foreach ($hourlySources as $procId => $src) {
            $table = $src['table'];
            $qtyField = $src['qty_field'];

            if (!$db->tableExists($table) || !$db->fieldExists('production_date', $table)) continue;

            $q = $db->table($table)
                ->select("product_id, SUM(COALESCE($qtyField,0)) as qty_sum")
                ->where('production_date', $date);

            if (!empty($productIds) && $db->fieldExists('product_id', $table)) {
                $q->whereIn('product_id', $productIds);
            }

            $rows = $q->groupBy('product_id')->get()->getResultArray();
            foreach ($rows as $r) {
                $pid = (int)($r['product_id'] ?? 0);
                $val = (int)($r['qty_sum'] ?? 0);
                if ($pid > 0) $outMap[(int)$procId][$pid] = ($outMap[(int)$procId][$pid] ?? 0) + $val;
            }
        }

        return $outMap;
    }

    /**
     * ✅ IN harian dari DAILY SCHEDULE (TOTAL SHIFT)
     * return map: [process_id][product_id] => qty_in_total
     */
    private function buildScheduleInMap($db, string $date): array
    {
        $map = [];

        if (!$db->tableExists('daily_schedules') || !$db->tableExists('daily_schedule_items')) {
            return $map;
        }

        $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
        if (!$dateCol) return $map;

        $targetCol = $db->fieldExists('target_per_shift', 'daily_schedule_items') ? 'target_per_shift' : null;
        if (!$targetCol) return $map;

        // process_id WAJIB ada agar mapping benar
        if (!$db->fieldExists('process_id', 'daily_schedules')) return $map;

        $rows = $db->table('daily_schedules ds')
            ->select("
                ds.process_id,
                dsi.product_id,
                SUM(COALESCE(dsi.$targetCol,0)) as qty_in
            ")
            ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id', 'inner')
            ->where("ds.$dateCol", $date)
            ->groupBy('ds.process_id, dsi.product_id')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $procId = (int)($r['process_id'] ?? 0);
            $pid    = (int)($r['product_id'] ?? 0);
            $qtyIn  = (int)($r['qty_in'] ?? 0);
            if ($procId > 0 && $pid > 0) $map[$procId][$pid] = $qtyIn;
        }

        return $map;
    }

    /**
     * ✅ ambil durasi detik time slot PERTAMA untuk tiap shift
     * return: [shift_id] => seconds_first_slot
     */
    private function getFirstSlotSecondsByShift($db): array
    {
        $map = [];
        if (!$db->tableExists('shift_time_slots') || !$db->tableExists('time_slots')) return $map;

        // Ambil slot paling awal per shift berdasarkan time_start
        // NOTE: kalau time_start format "HH:MM:SS"
        $rows = $db->table('shift_time_slots sts')
            ->select('sts.shift_id, ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id', 'inner')
            ->orderBy('sts.shift_id', 'ASC')
            ->orderBy('ts.time_start', 'ASC')
            ->get()->getResultArray();

        $picked = [];
        foreach ($rows as $r) {
            $sid = (int)($r['shift_id'] ?? 0);
            if ($sid <= 0) continue;
            if (isset($picked[$sid])) continue; // sudah ambil first slot

            $start = strtotime($r['time_start']);
            $end   = strtotime($r['time_end']);
            if ($start === false || $end === false) continue;

            if ($end <= $start) $end += 86400; // cross midnight
            $sec = (int)max(0, $end - $start);

            $picked[$sid] = true;
            $map[$sid] = $sec;
        }

        return $map;
    }

    /**
     * ✅ WIP Awal = target jam pertama (first time slot) per product per shift
     * rumus: qty_first_slot = floor(target_per_hour * (slot_seconds/3600))
     * return map: [process_id][product_id] => qty_wip_awal
     */
    private function buildWipAwalFromFirstSlotMap($db, string $date): array
    {
        $map = [];

        if (
            !$db->tableExists('daily_schedules') ||
            !$db->tableExists('daily_schedule_items') ||
            !$db->fieldExists('process_id', 'daily_schedules')
        ) return $map;

        $dateCol = $db->fieldExists('schedule_date', 'daily_schedules') ? 'schedule_date' : null;
        if (!$dateCol) return $map;

        // butuh target_per_hour
        $tphCol = $db->fieldExists('target_per_hour', 'daily_schedule_items') ? 'target_per_hour' : null;
        if (!$tphCol) return $map;

        $firstSlotSec = $this->getFirstSlotSecondsByShift($db);
        if (empty($firstSlotSec)) return $map;

        // Ambil per shift-process-product (karena first slot durasi tergantung shift)
        $rows = $db->table('daily_schedules ds')
            ->select("
                ds.process_id,
                ds.shift_id,
                dsi.product_id,
                SUM(COALESCE(dsi.$tphCol,0)) as tph_sum
            ")
            ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id', 'inner')
            ->where("ds.$dateCol", $date)
            ->groupBy('ds.process_id, ds.shift_id, dsi.product_id')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $procId = (int)($r['process_id'] ?? 0);
            $shiftId = (int)($r['shift_id'] ?? 0);
            $pid    = (int)($r['product_id'] ?? 0);
            $tph    = (int)($r['tph_sum'] ?? 0);

            if ($procId <= 0 || $shiftId <= 0 || $pid <= 0) continue;
            if ($tph <= 0) continue;

            $sec = (int)($firstSlotSec[$shiftId] ?? 0);
            if ($sec <= 0) continue;

            // qty jam pertama sesuai durasi slot pertama
            $qtyFirst = (int)floor($tph * ($sec / 3600));
            if ($qtyFirst < 0) $qtyFirst = 0;

            $map[$procId][$pid] = ($map[$procId][$pid] ?? 0) + $qtyFirst;
        }

        return $map;
    }

    /**
     * ✅ Transfer map dari production_wip di tanggal terpilih
     * return map: [process_id][product_id] => transfer_sum
     */
    private function buildTransferMap($db, string $wipDateCol, string $date, array $processIds, array $productIds): array
    {
        $map = [];
        if (!$db->tableExists('production_wip')) return $map;
        if (!$db->fieldExists('transfer', 'production_wip')) return $map;

        $rows = $db->table('production_wip w')
            ->select("w.to_process_id as process_id, w.product_id, SUM(COALESCE(w.transfer,0)) as transfer_sum")
            ->where("w.$wipDateCol", $date)
            ->whereIn('w.to_process_id', $processIds)
            ->whereIn('w.product_id', $productIds)
            ->groupBy('w.to_process_id, w.product_id')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $procId = (int)($r['process_id'] ?? 0);
            $pid    = (int)($r['product_id'] ?? 0);
            $val    = (int)($r['transfer_sum'] ?? 0);
            if ($procId > 0 && $pid > 0) $map[$procId][$pid] = $val;
        }

        return $map;
    }

    public function index()
    {
        $db = db_connect();

        // ✅ hanya ADMIN boleh pilih tanggal
        $role = session()->get('role') ?? '';
        $isAdmin = ($role === 'ADMIN');

        $date = $this->request->getGet('date') ?? date('Y-m-d');
        if (!$isAdmin) {
            $date = date('Y-m-d');
        }

        $tbl = 'production_wip';
        $wipDateCol = $this->detectWipDateColumn($db);

        $colNg    = $this->detectColumn($db, $tbl, ['qty_ng', 'qty_reject', 'qty_scrap', 'ng_qty']);
        $colStock = $this->detectColumn($db, $tbl, ['stock', 'stock_qty', 'qty_stock']);

        // ✅ TOTAL IN dari schedule
        $scheduleInMap = $this->buildScheduleInMap($db, $date);

        // ✅ WIP Awal dari target jam pertama (first time slot)
        $wipAwalMap = $this->buildWipAwalFromFirstSlotMap($db, $date);

        // ✅ pasangan process/product dari schedule + production_wip hari ini
        $pairs = [];
        foreach ($scheduleInMap as $procId => $pids) {
            foreach ($pids as $pid => $qtyIn) {
                $pairs[$procId.'|'.$pid] = ['process_id' => (int)$procId, 'product_id' => (int)$pid];
            }
        }

        if ($db->tableExists($tbl)) {
            $wipPairs = $db->table("$tbl w")
                ->select("w.to_process_id as process_id, w.product_id")
                ->where("w.$wipDateCol", $date)
                ->groupBy("w.to_process_id, w.product_id")
                ->get()->getResultArray();

            foreach ($wipPairs as $p) {
                $procId = (int)($p['process_id'] ?? 0);
                $pid    = (int)($p['product_id'] ?? 0);
                if ($procId > 0 && $pid > 0) {
                    $pairs[$procId.'|'.$pid] = ['process_id' => $procId, 'product_id' => $pid];
                }
            }
        }

        // ✅ juga tambahkan pasangan dari wipAwalMap (kalau scheduleInMap kosong tapi ada tph)
        foreach ($wipAwalMap as $procId => $pids) {
            foreach ($pids as $pid => $val) {
                $pairs[$procId.'|'.$pid] = ['process_id' => (int)$procId, 'product_id' => (int)$pid];
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
        foreach ($pairs as $p) {
            $processIds[(int)$p['process_id']] = true;
            $productIds[(int)$p['product_id']] = true;
        }
        $processIds = array_keys($processIds);
        $productIds = array_keys($productIds);

        // label process
        $processMap = [];
        if (!empty($processIds)) {
            $prows = $db->table('production_processes')
                ->select('id, process_name')
                ->whereIn('id', $processIds)
                ->get()->getResultArray();
            foreach ($prows as $r) $processMap[(int)$r['id']] = (string)($r['process_name'] ?? '');
        }

        // label product
        $productMap = [];
        if (!empty($productIds)) {
            $prows = $db->table('products')
                ->select('id, part_no, part_name')
                ->whereIn('id', $productIds)
                ->get()->getResultArray();
            foreach ($prows as $r) {
                $productMap[(int)$r['id']] = [
                    'part_no' => (string)($r['part_no'] ?? ''),
                    'part_name' => (string)($r['part_name'] ?? ''),
                ];
            }
        }

        // OUT hourly
        $hourlyOutMap = $this->buildHourlyOutMap($db, $date, $productIds);

        // NG + stock today (production_wip hari ini)
        $ngMap = [];
        $stockTodayMap = [];

        if ($db->tableExists($tbl)) {
            $sel = [
                "to_process_id as process_id",
                "product_id",
            ];
            if ($colNg)    $sel[] = "SUM(COALESCE($colNg,0)) as qty_ng";
            else           $sel[] = "0 as qty_ng";
            if ($colStock) $sel[] = "MAX(COALESCE($colStock,0)) as stock_today";
            else           $sel[] = "0 as stock_today";

            $rowsToday = $db->table($tbl)
                ->select(implode(",\n", $sel))
                ->where($wipDateCol, $date)
                ->whereIn('to_process_id', $processIds)
                ->whereIn('product_id', $productIds)
                ->groupBy('to_process_id, product_id')
                ->get()->getResultArray();

            foreach ($rowsToday as $r) {
                $procId = (int)($r['process_id'] ?? 0);
                $pid    = (int)($r['product_id'] ?? 0);
                $ngMap[$procId][$pid] = (int)($r['qty_ng'] ?? 0);
                $stockTodayMap[$procId][$pid] = (int)($r['stock_today'] ?? 0);
            }
        }

        // ✅ Transfer map
        $transferMap = $this->buildTransferMap($db, $wipDateCol, $date, $processIds, $productIds);

        // Final rows
        $rows = [];
        foreach ($pairs as $p) {
            $procId = (int)$p['process_id'];
            $pid    = (int)$p['product_id'];

            $station = $processMap[$procId] ?? ('PROCESS '.$procId);
            $pinfo   = $productMap[$pid] ?? ['part_no' => '', 'part_name' => ''];

            $totalScheduleIn = (int)($scheduleInMap[$procId][$pid] ?? 0); // TOTAL target per shift
            $wipAwal = (int)($wipAwalMap[$procId][$pid] ?? 0);             // target jam pertama

            // ✅ qty_in = sisa schedule setelah jam pertama
            // jaga supaya tidak minus
            if ($wipAwal > $totalScheduleIn) $wipAwal = $totalScheduleIn;
            $qtyIn = max(0, $totalScheduleIn - $wipAwal);

            $qtyOut = (int)($hourlyOutMap[$procId][$pid] ?? 0);
            $qtyNg  = (int)($ngMap[$procId][$pid] ?? 0);

            $wipAkhir = max(0, $wipAwal + $qtyIn - $qtyOut - $qtyNg);

            $stock = $colStock ? (int)($stockTodayMap[$procId][$pid] ?? $wipAkhir) : $wipAkhir;

            $transfer = (int)($transferMap[$procId][$pid] ?? 0);

            $rows[] = [
                'date' => $date,
                'station' => $station,
                'part_no' => $pinfo['part_no'],
                'part_name' => $pinfo['part_name'],
                'wip_awal' => $wipAwal,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'qty_ng' => $qtyNg,
                'wip_akhir' => $wipAkhir,
                'stock' => $stock,
                'transfer' => $transfer,
            ];
        }

        usort($rows, function($a, $b){
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

    private function formatTitleDate(string $date): string
    {
        $ts = strtotime($date);
        if (!$ts) return $date;
        return date('d M Y', $ts);
    }
}
