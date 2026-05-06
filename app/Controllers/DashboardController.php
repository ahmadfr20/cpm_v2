<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        return view('dashboard/index', [
            'date'          => $date,
            'fgInventory'   => $this->getFgInventory($db),
            'asakai'        => $this->getAsakai($db, $date),
            'perfDC'        => $this->getDailyPerformance($db, $date, 'DC'),
            'perfMC'        => $this->getDailyPerformance($db, $date, 'MC'),
            'dcbData'       => $this->getDCB($db, $date),
            'scdData'       => $this->getSCD($db, $date),
            'qcChartData'   => $this->getQCCharts($db),
            'wipData'       => $this->getWip($db, $date),
        ]);
    }

    /* ═══════════════════════════════════════════════════════
       1. INVENTORY FG
    ═══════════════════════════════════════════════════════ */
    private function getFgInventory($db): array
    {
        $fgProcessId = 0;
        foreach (['FINISHED GOOD', 'Finished Good', 'FG'] as $name) {
            $row = $db->table('production_processes')->select('id')->where('process_name', $name)->get()->getRowArray();
            if ($row) { $fgProcessId = (int)$row['id']; break; }
        }
        if ($fgProcessId <= 0) return [];

        $rows = $db->table('production_wip pw')
            ->select('pw.product_id, p.part_no, p.part_name, SUM(pw.stock) as qty_available, SUM(pw.qty_out) as qty_delivered, SUM(pw.qty_in) as qty_total_in')
            ->join('products p', 'p.id = pw.product_id', 'left')
            ->where('pw.to_process_id', $fgProcessId)
            ->where('pw.stock >', 0)
            ->groupBy('pw.product_id, p.part_no, p.part_name')
            ->get()->getResultArray();

        return $rows;
    }

    /* ═══════════════════════════════════════════════════════
       2. ASAKAI SUMMARY (aggregated across all processes, per shift)
    ═══════════════════════════════════════════════════════ */
    private function getAsakai($db, $date): array
    {
        $processNames = ['Die Casting' => 'DC', 'Machining' => 'MC'];
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->where('day_group', $this->getDayGroup($date))
            ->orderBy('shift_code', 'ASC')
            ->get()->getResultArray();

        $result = ['shifts' => $shifts, 'sections' => []];

        foreach ($processNames as $procName => $code) {
            $process = $db->table('production_processes')->where('process_name', $procName)->get()->getRowArray();
            if (!$process) continue;
            $processId = (int)$process['id'];

            // Get targets from daily_schedules
            $targets = $db->table('daily_schedules ds')
                ->select('ds.shift_id, dsi.product_id, p.part_no, p.part_name, SUM(dsi.target_per_shift) as target')
                ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id')
                ->join('products p', 'p.id = dsi.product_id', 'left')
                ->where('ds.process_id', $processId)
                ->where('ds.schedule_date', $date)
                ->groupBy('ds.shift_id, dsi.product_id')
                ->get()->getResultArray();

            $productsMap = [];
            foreach ($targets as $t) {
                $pid = (int)$t['product_id'];
                $sid = (int)$t['shift_id'];
                if (!isset($productsMap[$pid])) {
                    $productsMap[$pid] = ['part_no' => $t['part_no'], 'part_name' => $t['part_name'], 'shifts' => []];
                }
                $productsMap[$pid]['shifts'][$sid]['target'] = (int)$t['target'];
            }

            // Get actuals from production_wip (shift_id may not exist on all setups)
            $wipDateCol  = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';
            $hasWipShift = $db->fieldExists('shift_id', 'production_wip');

            $wipQuery = $db->table('production_wip pw')
                ->select('pw.product_id, pw.qty_in')
                ->where("pw.{$wipDateCol}", $date)
                ->where('pw.to_process_id', $processId);

            if ($hasWipShift) {
                $wipQuery->select('pw.shift_id');
            } else {
                // Derive shift_id from source tables (same approach as AsakaiController)
                $wipQuery->select(
                    'COALESCE(dsi.shift_id, mt.shift_id, dcp.shift_id, dch.shift_id, mh.shift_id) as shift_id'
                )
                ->join('daily_schedule_items dsi', "pw.source_table = 'daily_schedule_items' AND pw.source_id = dsi.id", 'left')
                ->join('material_transactions mt',  "pw.source_table = 'material_transactions'  AND pw.source_id = mt.id",  'left')
                ->join('die_casting_production dcp', "pw.source_table = 'die_casting_production' AND pw.source_id = dcp.id", 'left')
                ->join('die_casting_hourly dch',     "pw.source_table = 'die_casting_hourly'     AND pw.source_id = dch.id", 'left')
                ->join('machining_hourly mh',        "pw.source_table = 'machining_hourly'       AND pw.source_id = mh.id",  'left');
            }

            $actuals = $wipQuery->get()->getResultArray();

            foreach ($actuals as $a) {
                $pid = (int)$a['product_id'];
                $sid = (int)($a['shift_id'] ?? 0);
                if (!isset($productsMap[$pid])) {
                    $productsMap[$pid] = ['part_no' => '', 'part_name' => '', 'shifts' => []];
                }
                if (!isset($productsMap[$pid]['shifts'][$sid]['fg'])) {
                    $productsMap[$pid]['shifts'][$sid]['fg'] = 0;
                }
                $productsMap[$pid]['shifts'][$sid]['fg'] += (int)$a['qty_in'];
            }

            // Fetch operator and remarks specifically for this process
            $operatorsAndRemarks = [];
            $ngDtMap = [];
            $hourlyTbl = ($procName === 'Die Casting' && $db->tableExists('die_casting_hourly')) ? 'die_casting_hourly' : null;
            if (!$hourlyTbl && $procName === 'Machining' && $db->tableExists('machining_hourly')) $hourlyTbl = 'machining_hourly';
            
            if ($hourlyTbl) {
                $hasNg = $db->fieldExists('qty_ng', $hourlyTbl);
                $hasDt1 = $db->fieldExists('downtime_minute', $hourlyTbl);
                $hasDt2 = $db->fieldExists('downtime', $hourlyTbl);
                $dtCol = $hasDt1 ? 'downtime_minute' : ($hasDt2 ? 'downtime' : false);

                $hasOp = $db->fieldExists('operator_name', $hourlyTbl);
                $hasRem = $db->fieldExists('remark', $hourlyTbl);

                $selCols = 'product_id, shift_id';
                if ($hasOp) $selCols .= ', operator_name';
                if ($hasRem) $selCols .= ', remark';
                if ($hasNg) $selCols .= ', qty_ng';
                if ($dtCol) $selCols .= ", $dtCol as dt_min";

                $hrm = $db->table($hourlyTbl)
                         ->select($selCols)
                         ->where('production_date', $date)
                         ->get()->getResultArray();
                foreach ($hrm as $h) {
                    $pid_loop = (int)$h['product_id'];
                    $sid_loop = (int)$h['shift_id'];
                    if (!isset($operatorsAndRemarks[$pid_loop][$sid_loop])) {
                        $operatorsAndRemarks[$pid_loop][$sid_loop] = ['operators' => [], 'remarks' => []];
                    }
                    if ($hasOp && !empty(trim((string)$h['operator_name']))) {
                        $operatorsAndRemarks[$pid_loop][$sid_loop]['operators'][] = trim((string)$h['operator_name']);
                    }
                    if ($hasRem && !empty(trim((string)$h['remark']))) {
                        $operatorsAndRemarks[$pid_loop][$sid_loop]['remarks'][] = trim((string)$h['remark']);
                    }
                    
                    if (!isset($ngDtMap[$pid_loop][$sid_loop])) {
                        $ngDtMap[$pid_loop][$sid_loop] = ['ng' => 0, 'dt' => 0];
                    }
                    if ($hasNg) {
                        $ngDtMap[$pid_loop][$sid_loop]['ng'] += (int)($h['qty_ng'] ?? 0);
                    }
                    if ($dtCol) {
                        $ngDtMap[$pid_loop][$sid_loop]['dt'] += (int)($h['dt_min'] ?? 0);
                    }
                }
            }

            // Compute totals
            $sectionTotal = ['target' => 0, 'fg' => 0, 'ng' => 0, 'dt' => 0];
            $products = [];
            foreach ($productsMap as $pid => $data) {
                $totalTarget = 0; $totalFg = 0; $totalNg = 0; $totalDt = 0;
                $shiftData = [];
                foreach ($shifts as $sh) {
                    $sid = (int)$sh['id'];
                    $t = $data['shifts'][$sid]['target'] ?? 0;
                    $f = $data['shifts'][$sid]['fg'] ?? 0;
                    $eff = $t > 0 ? round(($f / $t) * 100, 1) : 0;
                    
                    $n = $ngDtMap[$pid][$sid]['ng'] ?? 0;
                    $d = $ngDtMap[$pid][$sid]['dt'] ?? 0;
                    $n_pct = ($f + $n) > 0 ? round(($n / ($f + $n)) * 100, 1) : 0;
                    
                    $ops = []; $rems = [];
                    if (isset($operatorsAndRemarks[$pid][$sid])) {
                        $ops = array_unique($operatorsAndRemarks[$pid][$sid]['operators']);
                        $rems = array_unique($operatorsAndRemarks[$pid][$sid]['remarks']);
                    }

                    $shiftData[$sid] = [
                        'target' => $t, 
                        'fg' => $f, 
                        'ng' => $n,
                        'ng_pct' => $n_pct,
                        'dt' => $d,
                        'eff' => $eff,
                        'operator' => implode(', ', $ops),
                        'remark' => implode(' | ', $rems)
                    ];
                    $totalTarget += $t; $totalFg += $f;
                    $totalNg += $n; $totalDt += $d;
                }
                $totalEff = $totalTarget > 0 ? round(($totalFg / $totalTarget) * 100, 1) : 0;
                $totalNgPct = ($totalFg + $totalNg) > 0 ? round(($totalNg / ($totalFg + $totalNg)) * 100, 1) : 0;
                $products[] = [
                    'part_no' => $data['part_no'], 'part_name' => $data['part_name'],
                    'shifts' => $shiftData,
                    'total_target' => $totalTarget, 'total_fg' => $totalFg, 
                    'total_ng' => $totalNg, 'total_ng_pct' => $totalNgPct, 'total_dt' => $totalDt,
                    'total_eff' => $totalEff
                ];
                $sectionTotal['target'] += $totalTarget;
                $sectionTotal['fg'] += $totalFg;
                $sectionTotal['ng'] += $totalNg;
                $sectionTotal['dt'] += $totalDt;
            }

            usort($products, fn($a, $b) => strcmp($a['part_no'], $b['part_no']));
            $sectionTotal['eff'] = $sectionTotal['target'] > 0
                ? round(($sectionTotal['fg'] / $sectionTotal['target']) * 100, 1) : 0;
            $sectionTotal['ng_pct'] = ($sectionTotal['fg'] + $sectionTotal['ng']) > 0
                ? round(($sectionTotal['ng'] / ($sectionTotal['fg'] + $sectionTotal['ng'])) * 100, 1) : 0;

            $result['sections'][$procName] = ['code' => $code, 'products' => $products, 'total' => $sectionTotal];
        }

        // ─── Operator & Leader per section per shift ───────────────────────
        $tableMap = ['Die Casting' => 'die_casting_hourly', 'Machining' => 'machining_hourly'];
        $operatorData = [];
        foreach ($tableMap as $secName => $tbl) {
            $operatorData[$secName] = [];
            if (!$db->tableExists($tbl)) continue;
            foreach ($shifts as $sh) {
                $op = $db->table($tbl)
                    ->select('operator_name, leader_name')
                    ->where('production_date', $date)
                    ->where('shift_id', $sh['id'])
                    ->orderBy('id', 'DESC')
                    ->get()->getRowArray();
                $operatorData[$secName][$sh['id']] = [
                    'operator' => trim($op['operator_name'] ?? '') ?: '-',
                    'leader'   => trim($op['leader_name']   ?? '') ?: '-',
                ];
            }
        }
        $result['operatorData'] = $operatorData;

        return $result;
    }

    /* ═══════════════════════════════════════════════════════
       3. DAILY PERFORMANCE (DC & MC)
    ═══════════════════════════════════════════════════════ */
    private function getDailyPerformance($db, $date, $process): array
    {
        $perfCtrl = new \App\Controllers\Dashboard\PerformanceController();
        if ($process === 'DC') {
            $data = $perfCtrl->getDieCastingData($db, $date);
        } else {
            $data = $perfCtrl->getMachiningData($db, $date);
        }

        return [
            'ok_achievement' => $data['okAchievement'] ?? 0,
            'ng_rate'        => $data['ngRate'] ?? 0,
            'downtime_rate'  => $data['downtimeRate'] ?? 0,
            'machines'       => $data['machines'] ?? [],
            'shifts'         => $data['shifts'] ?? []
        ];
    }

    /* ═══════════════════════════════════════════════════════
       4. DELIVERY CONTROL BOARD (simplified)
    ═══════════════════════════════════════════════════════ */
    private function getDCB($db, $date): array
    {
        // ── Fixed template (same as DeliveryControlBoardController) ──────────
        $fixedTemplate = [
            [
                'customer_name' => 'DENSO MANUFACTURING INDONESIA, PT',
                'parts' => [
                    'HOLDER-7100','HOLDER-7110','HOLDER-7690','HOLDER-7700','HOLDER-7710',
                    'HOLDER-7720','HOLDER-7791','HOLDER-7590','HOLDER-7600','HOLDER-7610',
                    'HOLDER-7620','HOLDER-9690','HOLDER-9700','HOLDER-9710','HOLDER-9720',
                    'HOLDER-9730','HOLDER-9740','HOLDER-9750','HOLDER-9760','HOLDER-0580',
                    'HOLDER-0590','HOLDER-0600','HOLDER-0610','HOLDER-1220','HOLDER-1230',
                    'HOLDER-1240','HOLDER-1250','HOLDER-1320','HOLDER-1330','HOLDER-1340',
                    'HOLDER-1350','HOLDER-1470','HOLDER-1481','HOLDER-1490','HOLDER-1501',
                    'HOLDER-1650','HOLDER-1660','HOLDER-1670','HOLDER-1680','HOLDER-1750',
                    'HOLDER-1760','HOLDER-1910','HOLDER-1920','HOLDER-2090','HOLDER-2100',
                    'HOLDER-2110','HOLDER-2120','Housing-0160','Housing-9710 C',
                ],
            ],
            [
                'customer_name' => 'SUZUKI INDOMOBIL MOTOR, PT',
                'parts' => [
                    'Case Comp Thermostat','CGSL APV','CWO','Bracket YR9','Cov.Gear Case 4JA',
                ],
            ],
            [
                'customer_name' => 'MESIN ISUZU INDONESIA, PT',
                'parts' => [
                    'Duct Thermostat','Duct Asm Water','Bracket Asm Generator','Plate MSG',
                ],
            ],
        ];

        // ── Load products ────────────────────────────────────────────────────
        $products = $db->tableExists('products')
            ? $db->table('products')->where('is_active', 1)->get()->getResultArray() : [];
        $productByName = []; $productByNo = []; $prodNameMap = [];
        foreach ($products as $p) {
            $nameKey = preg_replace('/[^a-z0-9]/', '', strtolower($p['part_name']));
            $productByName[$nameKey] = (int)$p['id'];
            if (!empty($p['part_no'])) {
                $noKey = preg_replace('/[^a-z0-9]/', '', strtolower($p['part_no']));
                $productByNo[$noKey] = (int)$p['id'];
            }
            $prodNameMap[(int)$p['id']] = $p['part_name'];
        }

        // ── Load schedules (mapped by customerName::partName) ────────────────
        $scheduleMap = [];
        if ($db->tableExists('fg_delivery_schedules')) {
            $customers = $db->tableExists('customers') ? $db->table('customers')->get()->getResultArray() : [];
            $custMap = [];
            foreach ($customers as $c) $custMap[(int)$c['id']] = $c['customer_name'];

            $schedules = $db->table('fg_delivery_schedules')->where('schedule_date', $date)->get()->getResultArray();
            foreach ($schedules as $s) {
                $cName = $custMap[(int)$s['customer_id']] ?? '';
                foreach ($products as $p) {
                    if ((int)$p['id'] === (int)$s['product_id']) {
                        $k = strtolower(trim($cName)) . '::' . strtolower(trim($p['part_name']));
                        $scheduleMap[$k] = $s;
                        break;
                    }
                }
            }
        }

        // ── Actual delivery per product_id per RIT ───────────────────────────
        $actualMap = [];
        if ($db->tableExists('fg_delivery_items') && $db->tableExists('fg_deliveries')) {
            $ritCol = $db->fieldExists('rit', 'fg_delivery_items') ? 'di.rit' : "'RIT-1'";
            $rows = $db->query("
                SELECT di.product_id, {$ritCol} AS rit, SUM(di.qty) AS total_qty
                FROM fg_delivery_items di
                JOIN fg_deliveries d ON d.id = di.fg_delivery_id
                WHERE d.delivery_date = ?
                GROUP BY di.product_id, {$ritCol}
            ", [$date])->getResultArray();
            foreach ($rows as $r) {
                $pid = (int)$r['product_id'];
                $rit = str_replace(' ', '', strtoupper($r['rit'])) ?: 'RIT-1';
                $actualMap[$pid][$rit] = (int)$r['total_qty'];
            }
        }

        // ── Build display groups from fixed template ──────────────────────────
        $groups      = [];
        $grandTarget = 0;
        $grandActual = 0;

        foreach ($fixedTemplate as $grp) {
            $custName = $grp['customer_name'];
            $partRows = [];

            foreach ($grp['parts'] as $partName) {
                $k   = strtolower(trim($custName)) . '::' . strtolower(trim($partName));
                $sch = $scheduleMap[$k] ?? null;

                // Resolve product_id
                $pid = 0;
                if ($sch) {
                    $pid = (int)$sch['product_id'];
                } else {
                    $sk  = preg_replace('/[^a-z0-9]/', '', strtolower($partName));
                    $pid = $productByName[$sk] ?? $productByNo[$sk] ?? 0;
                }

                $t1   = (int)($sch['rit_1'] ?? 0);
                $t2   = (int)($sch['rit_2'] ?? 0);
                $tQty = $t1 + $t2;
                $a1   = $pid > 0 ? (int)($actualMap[$pid]['RIT-1'] ?? 0) : 0;
                $a2   = $pid > 0 ? (int)($actualMap[$pid]['RIT-2'] ?? 0) : 0;
                $aQty = $a1 + $a2;

                $grandTarget += $tQty;
                $grandActual += $aQty;

                $partRows[] = [
                    'part_name' => $partName,
                    'product_id'=> $pid,
                    'target'    => $tQty,
                    'actual'    => $aQty,
                    'rit1_t'    => $t1, 'rit1_a' => $a1,
                    'rit2_t'    => $t2, 'rit2_a' => $a2,
                    'has_data'  => ($tQty > 0 || $aQty > 0),
                ];
            }

            $groups[] = ['customer_name' => $custName, 'parts' => $partRows];
        }

        return [
            'groups'       => $groups,
            'grand_target' => $grandTarget,
            'grand_actual' => $grandActual,
        ];
    }

    /* ═══════════════════════════════════════════════════════
       5. SPECIAL CONTROL DELIVERY
    ═══════════════════════════════════════════════════════ */
    private function getSCD($db, $date): array
    {
        if (!$db->tableExists('scd_rows')) return ['rows' => [], 'grand_plan' => 0, 'grand_actual' => 0];

        $rows = $db->table('scd_rows')
            ->where('board_date', $date)
            ->orderBy('row_order', 'ASC')
            ->get()->getResultArray();

        $grandPlan = 0; $grandActual = 0;
        foreach ($rows as &$r) {
            $total = 0;
            for ($i = 1; $i <= 5; $i++) $total += (int)$r["rit{$i}_qty"];
            $r['total_actual'] = $total;
            $grandPlan += (int)$r['plan_qty'];
            $grandActual += $total;
        }

        return ['rows' => $rows, 'grand_plan' => $grandPlan, 'grand_actual' => $grandActual];
    }

    /* ═══════════════════════════════════════════════════════
       6. QC CHARTS (Monthly & Yearly)
    ═══════════════════════════════════════════════════════ */
    private function getQCCharts($db): array
    {
        if (!$db->tableExists('qc_inspections')) {
            return ['monthly' => [], 'yearly' => []];
        }

        $currentYear  = date('Y');
        $currentMonth = date('m');

        // Monthly: daily data for current month
        $monthlyRows = $db->query("
            SELECT DAY(production_date) as day_num,
                   SUM(qty_ok) as total_ok,
                   SUM(qty_ng) as total_ng
            FROM qc_inspections
            WHERE YEAR(production_date) = ? AND MONTH(production_date) = ?
            GROUP BY DAY(production_date)
            ORDER BY day_num ASC
        ", [$currentYear, $currentMonth])->getResultArray();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$currentMonth, (int)$currentYear);
        $monthlyData = ['labels' => [], 'ok' => [], 'ng' => []];
        $monthlyMap = [];
        foreach ($monthlyRows as $r) $monthlyMap[(int)$r['day_num']] = $r;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $monthlyData['labels'][] = sprintf('%02d', $d);
            $monthlyData['ok'][]     = (int)($monthlyMap[$d]['total_ok'] ?? 0);
            $monthlyData['ng'][]     = (int)($monthlyMap[$d]['total_ng'] ?? 0);
        }

        // Yearly: monthly data for current year
        $yearlyRows = $db->query("
            SELECT MONTH(production_date) as month_num,
                   SUM(qty_ok) as total_ok,
                   SUM(qty_ng) as total_ng
            FROM qc_inspections
            WHERE YEAR(production_date) = ?
            GROUP BY MONTH(production_date)
            ORDER BY month_num ASC
        ", [$currentYear])->getResultArray();

        $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $yearlyData = ['labels' => $monthNames, 'ok' => array_fill(0, 12, 0), 'ng' => array_fill(0, 12, 0)];
        foreach ($yearlyRows as $r) {
            $idx = (int)$r['month_num'] - 1;
            $yearlyData['ok'][$idx] = (int)$r['total_ok'];
            $yearlyData['ng'][$idx] = (int)$r['total_ng'];
        }

        return ['monthly' => $monthlyData, 'yearly' => $yearlyData];
    }

    /* ═══════════════════════════════════════════════════════
       7. WIP INVENTORY SUMMARY
    ═══════════════════════════════════════════════════════ */
    private function getWip($db, $date): array
    {
        if (!$db->tableExists('production_wip')) return ['rows' => [], 'total_wip' => 0];

        // Detect date column
        $wipDateCol = 'production_date';
        foreach (['production_date', 'schedule_date', 'wip_date'] as $col) {
            if ($db->fieldExists($col, 'production_wip')) { $wipDateCol = $col; break; }
        }

        // Latest stock per (process, product) up to $date
        $rows = $db->query("
            SELECT w.to_process_id, w.product_id,
                   p.part_no, p.part_name,
                   pr.process_name,
                   w.stock, COALESCE(w.transfer,0) as transfer
            FROM production_wip w
            INNER JOIN products p   ON p.id  = w.product_id
            LEFT  JOIN production_processes pr ON pr.id = w.to_process_id
            WHERE w.{$wipDateCol} <= ?
              AND w.id IN (
                SELECT MAX(id) FROM production_wip
                WHERE {$wipDateCol} <= ?
                GROUP BY to_process_id, product_id
              )
            ORDER BY pr.process_name, p.part_no
        ", [$date, $date])->getResultArray();

        // Exclude FG process
        $fgId = 0;
        foreach (['FINISHED GOOD', 'Finished Good', 'FG'] as $nm) {
            $r = $db->table('production_processes')->select('id')->where('process_name', $nm)->get()->getRowArray();
            if ($r) { $fgId = (int)$r['id']; break; }
        }

        $result = []; $totalWip = 0;
        foreach ($rows as $r) {
            if ($fgId > 0 && (int)$r['to_process_id'] === $fgId) continue;
            $stock = (int)$r['stock'];
            $transfer = (int)$r['transfer'];
            $wip = $stock + $transfer;
            if ($wip <= 0) continue;
            $result[] = [
                'process_name' => $r['process_name'] ?? 'Unknown',
                'part_no'      => $r['part_no'],
                'part_name'    => $r['part_name'],
                'stock'        => $stock,
                'transfer'     => $transfer,
                'wip_total'    => $wip,
            ];
            $totalWip += $wip;
        }

        return ['rows' => $result, 'total_wip' => $totalWip];
    }
}
