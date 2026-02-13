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

    private function getShiftSlots($db, int $shiftId): array
    {
        return $db->table('shift_time_slots sts')
            ->select('t.id as time_slot_id, t.time_start, t.time_end')
            ->join('time_slots t', 't.id = sts.time_slot_id', 'inner')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('t.time_start', 'ASC')
            ->get()->getResultArray() ?: [];
    }

    /**
     * process_id -> hourly table & qty field (WIP per slot)
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
     * ✅ NEW: map shift completed untuk date
     * Prioritas: daily_schedules.is_completed (kalau ada)
     * Fallback: die_casting_production.is_completed (khusus DC)
     *
     * return: [process_id][shift_id] => true/false
     */
    private function buildCompletedShiftMap($db, string $date): array
    {
        $map = [];

        // 1) daily_schedules.is_completed
        if ($db->tableExists('daily_schedules') && $db->fieldExists('is_completed', 'daily_schedules')) {
            $rows = $db->table('daily_schedules')
                ->select('process_id, shift_id, is_completed')
                ->where('schedule_date', $date)
                ->get()->getResultArray();

            foreach ($rows as $r) {
                $proc = (int)($r['process_id'] ?? 0);
                $sid  = (int)($r['shift_id'] ?? 0);
                $done = (int)($r['is_completed'] ?? 0) === 1;
                if ($proc > 0 && $sid > 0 && $done) {
                    $map[$proc][$sid] = true;
                }
            }
        }

        // 2) fallback khusus DC: die_casting_production.is_completed
        $dcId = $this->getDieCastProcessId($db);
        if ($dcId && $db->tableExists('die_casting_production') && $db->fieldExists('is_completed', 'die_casting_production')) {
            $rows = $db->table('die_casting_production')
                ->select('shift_id, is_completed')
                ->where('production_date', $date)
                ->where('is_completed', 1)
                ->groupBy('shift_id, is_completed')
                ->get()->getResultArray();

            foreach ($rows as $r) {
                $sid = (int)($r['shift_id'] ?? 0);
                if ($sid > 0) {
                    $map[(int)$dcId][$sid] = true;
                }
            }
        }

        return $map;
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $nowTime = date('H:i');

        $wipDateCol = $this->detectWipDateColumn($db);

        // shifts master (buat label)
        $shiftMasterRows = $db->table('shifts')
            ->select('id, shift_code, shift_name, is_active')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $shiftMaster = [];
        foreach ($shiftMasterRows as $s) {
            $sid = (int)$s['id'];
            $shiftMaster[$sid] = [
                'shift_id'   => $sid,
                'shift_code' => (string)($s['shift_code'] ?? ''),
                'shift_name' => (string)($s['shift_name'] ?? ''),
                'slots'      => $this->getShiftSlots($db, $sid),
            ];
        }

        $dieCastId = $this->getDieCastProcessId($db);
        $hourlySources = $this->buildHourlySources($db);

        // ✅ NEW: completed map
        $completedMap = $this->buildCompletedShiftMap($db, $date);

        /**
         * Ambil schedule per PROCESS + SHIFT
         */
        $scheduleAgg = $db->table('daily_schedules ds')
            ->select('
                ds.process_id,
                ds.shift_id,
                dsi.product_id,
                p.part_no,
                p.part_name,
                SUM(dsi.target_per_hour)  as target_per_hour,
                SUM(dsi.target_per_shift) as target_per_shift
            ')
            ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id', 'inner')
            ->join('products p', 'p.id = dsi.product_id', 'inner')
            ->where('ds.schedule_date', $date)
            ->groupBy('ds.process_id, ds.shift_id, dsi.product_id, p.part_no, p.part_name')
            ->orderBy('ds.process_id', 'ASC')
            ->orderBy('ds.shift_id', 'ASC')
            ->orderBy('p.part_no', 'ASC')
            ->get()->getResultArray();

        $allProductIds = [];
        $sections = [];

        foreach ($scheduleAgg as $r) {
            $procId = (int)$r['process_id'];
            $sid    = (int)$r['shift_id'];
            $pid    = (int)$r['product_id'];

            $allProductIds[$pid] = true;

            if (!isset($sections[$procId])) $sections[$procId] = [];
            if (!isset($sections[$procId][$sid])) $sections[$procId][$sid] = [];

            $sections[$procId][$sid][$pid] = [
                'product_id' => $pid,
                'part_no'    => (string)$r['part_no'],
                'part_name'  => (string)$r['part_name'],
                'target_per_hour'  => (int)($r['target_per_hour'] ?? 0),
                'target_per_shift' => (int)($r['target_per_shift'] ?? 0),
            ];
        }

        // label process
        $processRows = $db->table('production_processes')
            ->select('id, process_name')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $processNameMap = [];
        foreach ($processRows as $p) $processNameMap[(int)$p['id']] = strtoupper((string)$p['process_name']);

        // kalau schedule kosong: buat section dummy dari hourlySources
        if (empty($sections)) {
            foreach ($hourlySources as $procId => $src) {
                $sections[(int)$procId] = [];
            }
        }

        $allProductIdList = array_keys($allProductIds);

        // build sectionsData
        $sectionsData = [];

        foreach ($sections as $procId => $shiftMap) {
            $procId = (int)$procId;
            $procLabel = $processNameMap[$procId] ?? ('PROCESS '.$procId);

            $shiftBlocks = [];

            foreach ($shiftMap as $sid => $productsMap) {
                $sid = (int)$sid;
                $shiftMeta = $shiftMaster[$sid] ?? [
                    'shift_id' => $sid,
                    'shift_name' => 'SHIFT '.$sid,
                    'slots' => [],
                ];
                $slotList = $shiftMeta['slots'] ?? [];

                $productBlocks = [];
                foreach ($productsMap as $pid => $pinfo) {
                    $pid = (int)$pid;

                    $rows = [];
                    foreach ($slotList as $ts) {
                        $tsId = (int)$ts['time_slot_id'];
                        $label = substr((string)$ts['time_start'], 0, 5) . ' - ' . substr((string)$ts['time_end'], 0, 5);

                        $rows[$tsId] = [
                            'time_slot_id' => $tsId,
                            'time_label'   => $label,
                            'current'      => 0,
                            'wip'          => 0,
                        ];
                    }

                    $productBlocks[] = [
                        'product_id' => $pid,
                        'part_no'    => (string)($pinfo['part_no'] ?? '-'),
                        'part_name'  => (string)($pinfo['part_name'] ?? ''),
                        'target_per_hour'  => (int)($pinfo['target_per_hour'] ?? 0),
                        'target_per_shift' => (int)($pinfo['target_per_shift'] ?? 0),
                        'rows'       => $rows,
                        'stock_total'=> 0,
                    ];
                }

                $shiftBlocks[] = [
                    'shift_id'   => $sid,
                    'shift_name' => (string)($shiftMeta['shift_name'] ?? ('SHIFT '.$sid)),
                    'slots'      => $slotList,
                    'products'   => $productBlocks,
                ];
            }

            if (empty($shiftBlocks)) {
                $shiftBlocks[] = [
                    'shift_id'   => 0,
                    'shift_name' => 'NO SHIFT SCHEDULE',
                    'slots'      => [],
                    'products'   => [],
                ];
            }

            $sectionsData[] = [
                'process_id'    => $procId,
                'process_label' => $procLabel,
                'shifts'        => $shiftBlocks,
            ];
        }

        /**
         * ✅ Fill WIP per slot dari hourly table
         * ✅ NEW: kalau shift completed => tidak boleh ada angka (force tetap 0)
         */
        if (!empty($sectionsData)) {
            foreach ($sectionsData as $secIdx => $sec) {
                $procId = (int)$sec['process_id'];

                if (!isset($hourlySources[$procId])) continue;

                $table = $hourlySources[$procId]['table'];
                $qtyField = $hourlySources[$procId]['qty_field'];
                if (!$db->tableExists($table)) continue;

                // shift ids yang ada di section ini
                $shiftIds = [];
                foreach (($sec['shifts'] ?? []) as $sh) {
                    $sid = (int)($sh['shift_id'] ?? 0);
                    if ($sid > 0) $shiftIds[$sid] = true;
                }
                $shiftIds = array_keys($shiftIds);
                if (empty($shiftIds)) continue;

                $rowsQ = $db->table($table)
                    ->select("shift_id, time_slot_id, product_id, SUM(COALESCE($qtyField,0)) as qty_sum")
                    ->where('production_date', $date)
                    ->whereIn('shift_id', $shiftIds);

                if (!empty($allProductIdList)) $rowsQ->whereIn('product_id', $allProductIdList);

                $rows = $rowsQ->groupBy('shift_id, time_slot_id, product_id')->get()->getResultArray();

                foreach ($rows as $r) {
                    $sid = (int)$r['shift_id'];

                    // ✅ guard: shift completed => skip (biar tetap 0)
                    if (!empty($completedMap[$procId][$sid])) {
                        continue;
                    }

                    $ts  = (int)$r['time_slot_id'];
                    $pid = (int)$r['product_id'];
                    $val = (int)$r['qty_sum'];

                    foreach ($sectionsData[$secIdx]['shifts'] as $shIdx => $sh) {
                        if ((int)$sh['shift_id'] !== $sid) continue;

                        foreach ($sh['products'] as $pIdx => $pb) {
                            if ((int)$pb['product_id'] !== $pid) continue;
                            if (!isset($pb['rows'][$ts])) continue;

                            $sectionsData[$secIdx]['shifts'][$shIdx]['products'][$pIdx]['rows'][$ts]['wip'] = $val;
                            $sectionsData[$secIdx]['shifts'][$shIdx]['products'][$pIdx]['rows'][$ts]['current'] = $val;
                        }
                    }
                }
            }
        }

        /**
         * Fill STOCK total dari production_wip.stock (per process+product)
         */
        if (!empty($sectionsData) && !empty($allProductIdList)) {
            $stockRows = $db->table('production_wip')
                ->select("to_process_id as process_id, product_id, MAX(COALESCE(stock,0)) as stock_val")
                ->where($w_attach = $wipDateCol, $date)
                ->whereIn('product_id', $allProductIdList)
                ->groupBy("to_process_id, product_id")
                ->get()->getResultArray();

            $stockMap = [];
            foreach ($stockRows as $r) {
                $prc = (int)$r['process_id'];
                $pid = (int)$r['product_id'];
                $stockMap[$prc][$pid] = (int)$r['stock_val'];
            }

            foreach ($sectionsData as $secIdx => $sec) {
                $procId = (int)$sec['process_id'];
                foreach ($sec['shifts'] as $shIdx => $sh) {
                    foreach ($sh['products'] as $pIdx => $pb) {
                        $pid = (int)$pb['product_id'];
                        $sectionsData[$secIdx]['shifts'][$shIdx]['products'][$pIdx]['stock_total'] =
                            (int)($stockMap[$procId][$pid] ?? 0);
                    }
                }
            }
        }

        /**
         * finalize row ordering per shift slot list
         */
        foreach ($sectionsData as $secIdx => $sec) {
            foreach ($sec['shifts'] as $shIdx => $sh) {
                $slotList = $sh['slots'] ?? [];
                foreach ($sh['products'] as $pIdx => $pb) {
                    $ordered = [];
                    foreach ($slotList as $ts) {
                        $tsId = (int)$ts['time_slot_id'];
                        if (isset($pb['rows'][$tsId])) $ordered[] = $pb['rows'][$tsId];
                    }
                    $sectionsData[$secIdx]['shifts'][$shIdx]['products'][$pIdx]['rows'] = $ordered;
                }
            }
        }

        // sort section
        usort($sectionsData, function($a, $b){
            return strcmp((string)$a['process_label'], (string)$b['process_label']);
        });

        return view('wip/inventory/index', [
            'date'     => $date,
            'nowTime'  => $nowTime,
            'sections' => $sectionsData,
        ]);
    }
}
