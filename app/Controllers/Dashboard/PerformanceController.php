<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class PerformanceController extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $process = $this->request->getGet('process') ?? 'DC';
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $data = [
            'process' => $process,
            'date' => $date,
            'okAchievement' => 0,
            'ngRate' => 0,
            'downtimeRate' => 0,
            'machines' => [],
            'shifts' => []
        ];

        if ($process === 'DC') {
            $data = array_merge($data, $this->getDieCastingData($db, $date));
            $data['pageTitle'] = 'Daily Performance - Die Casting';
        } else {
            $data = array_merge($data, $this->getMachiningData($db, $date));
            $data['pageTitle'] = 'Daily Performance - Machining';
        }

        return view('dashboard/performance/index', $data);
    }

    public function getDieCastingData($db, $date)
    {
        $dcpRows = $db->table('die_casting_production dcp')
            ->select('
                dcp.machine_id,
                dcp.shift_id,
                m.machine_code,
                m.line_position,
                sh.shift_name,
                SUM(dcp.qty_p) as target,
                SUM(dcp.qty_a) as fg,
                SUM(dcp.qty_ng) as ng
            ')
            ->join('machines m', 'm.id = dcp.machine_id', 'left')
            ->join('shifts sh', 'sh.id = dcp.shift_id', 'left')
            ->where('dcp.production_date', $date)
            // ->where('dcp.qty_p >', 0) // Removed to also get production without target if exists
            ->groupBy('dcp.machine_id, dcp.shift_id, m.machine_code, m.line_position, sh.shift_name')
            ->get()->getResultArray();

        $hourlyRows = $db->table('die_casting_hourly dh')
            ->select('
                dh.machine_id,
                dh.shift_id,
                sh.shift_name,
                COUNT(dh.id) as slot_count,
                SUM(dh.downtime_minute) as total_downtime
            ')
            ->join('shifts sh', 'sh.id = dh.shift_id', 'left')
            ->where('dh.production_date', $date)
            ->groupBy('dh.machine_id, dh.shift_id, sh.shift_name')
            ->get()->getResultArray();

        $hourlyMap = [];
        foreach ($hourlyRows as $h) {
            $key = $h['machine_id'] . '_' . $h['shift_id'];
            $hourlyMap[$key] = [
                'slot_count' => (int)$h['slot_count'],
                'downtime' => (int)$h['total_downtime'],
                'shift_name' => $h['shift_name']
            ];
        }

        // NG detail per machine
        $ngDetailMap = [];
        if ($db->tableExists('die_casting_hourly_ng_details')) {
            $ngRows = $db->query("
                SELECT dh.machine_id, nc.ng_name, SUM(nd.qty) as total_qty
                FROM die_casting_hourly_ng_details nd
                JOIN die_casting_hourly dh ON dh.id = nd.hourly_id
                JOIN ng_categories nc ON nc.id = nd.ng_category_id
                WHERE dh.production_date = ?
                GROUP BY dh.machine_id, nc.ng_name
            ", [$date])->getResultArray();
            foreach ($ngRows as $r) {
                $ngDetailMap[(int)$r['machine_id']][$r['ng_name']] = (int)$r['total_qty'];
            }
        }

        // DT detail per machine (try detail table first, fallback to hourly aggregate)
        $dtDetailMap = [];
        if ($db->tableExists('die_casting_hourly_downtime_details')) {
            $dtRows = $db->query("
                SELECT dh.machine_id, COALESCE(dc.downtime_name, 'Dandori') as dt_name, SUM(dd.downtime_minute) as total_mins
                FROM die_casting_hourly_downtime_details dd
                JOIN die_casting_hourly dh ON dh.id = dd.hourly_id
                LEFT JOIN downtime_categories dc ON dc.id = dd.downtime_category_id AND dd.downtime_category_id > 0
                WHERE dh.production_date = ?
                GROUP BY dh.machine_id, dt_name
            ", [$date])->getResultArray();
            foreach ($dtRows as $r) {
                $dtDetailMap[(int)$r['machine_id']][$r['dt_name']] = (int)$r['total_mins'];
            }
        } else {
            // Fallback: aggregate from hourly table downtime_minute column
            $dtAgg = $db->query("
                SELECT machine_id, SUM(downtime_minute) as total_mins
                FROM die_casting_hourly
                WHERE production_date = ? AND downtime_minute > 0
                GROUP BY machine_id
            ", [$date])->getResultArray();
            foreach ($dtAgg as $r) {
                if ((int)$r['total_mins'] > 0) {
                    $dtDetailMap[(int)$r['machine_id']]['Downtime'] = (int)$r['total_mins'];
                }
            }
        }

        $items = [];
        foreach ($dcpRows as $s) {
            $mId = $s['machine_id'];
            $sId = $s['shift_id'];
            $key = $mId . '_' . $sId;
            $hm = $hourlyMap[$key] ?? ['slot_count' => 0, 'downtime' => 0];
            
            $items[] = [
                'machine_id' => $mId,
                'shift_id' => $sId,
                'shift_name' => $s['shift_name'] ?? 'Unknown',
                'machine_code' => $s['machine_code'],
                'line_position' => $s['line_position'],
                'target' => (int)$s['target'],
                'fg' => (int)$s['fg'],
                'ng' => (int)$s['ng'],
                'slot_count' => $hm['slot_count'],
                'downtime' => $hm['downtime']
            ];
            unset($hourlyMap[$key]);
        }

        // Add orphans
        foreach ($hourlyMap as $key => $hm) {
            list($mId, $sId) = explode('_', $key);
            $m = $db->table('machines')->select('machine_code, line_position')->where('id', $mId)->get()->getRowArray();
            $items[] = [
                'machine_id' => $mId,
                'shift_id' => $sId,
                'shift_name' => $hm['shift_name'] ?? 'Unknown',
                'machine_code' => $m['machine_code'] ?? 'Unknown',
                'line_position' => $m['line_position'] ?? 999,
                'target' => 0,
                'fg' => 0,
                'ng' => 0,
                'slot_count' => $hm['slot_count'],
                'downtime' => $hm['downtime']
            ];
        }

        return $this->computeStats($items, $ngDetailMap, $dtDetailMap);
    }

    public function getMachiningData($db, $date)
    {
        $schedRows = $db->table('daily_schedule_items dsi')
            ->select('
                dsi.machine_id,
                dsi.shift_id,
                m.machine_code,
                m.line_position,
                sh.shift_name,
                SUM(dsi.target_per_shift) as target
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('machines m', 'm.id = dsi.machine_id', 'left')
            ->join('shifts sh', 'sh.id = dsi.shift_id', 'left')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Machining')
            // ->where('dsi.target_per_shift >', 0)
            ->groupBy('dsi.machine_id, dsi.shift_id, m.machine_code, m.line_position, sh.shift_name')
            ->get()->getResultArray();

        $hourlyRows = $db->table('machining_hourly mh')
            ->select('
                mh.machine_id,
                mh.shift_id,
                sh.shift_name,
                SUM(mh.qty_fg) as fg,
                SUM(mh.qty_ng) as ng,
                COUNT(mh.id) as slot_count,
                SUM(mh.downtime) as total_downtime
            ')
            ->join('shifts sh', 'sh.id = mh.shift_id', 'left')
            ->where('mh.production_date', $date)
            ->groupBy('mh.machine_id, mh.shift_id, sh.shift_name')
            ->get()->getResultArray();

        $hourlyMap = [];
        foreach ($hourlyRows as $h) {
            $key = $h['machine_id'] . '_' . $h['shift_id'];
            $hourlyMap[$key] = [
                'fg' => (int)$h['fg'],
                'ng' => (int)$h['ng'],
                'slot_count' => (int)$h['slot_count'],
                'downtime' => (int)$h['total_downtime'],
                'shift_name' => $h['shift_name']
            ];
        }

        // NG detail per machine
        $ngDetailMap = [];
        if ($db->tableExists('machining_hourly_ng_details')) {
            $ngRows = $db->query("
                SELECT mh.machine_id, nc.ng_name, SUM(nd.qty) as total_qty
                FROM machining_hourly_ng_details nd
                JOIN machining_hourly mh ON mh.id = nd.machining_hourly_id
                JOIN ng_categories nc ON nc.id = nd.ng_category_id
                WHERE mh.production_date = ?
                GROUP BY mh.machine_id, nc.ng_name
            ", [$date])->getResultArray();
            foreach ($ngRows as $r) {
                $ngDetailMap[(int)$r['machine_id']][$r['ng_name']] = (int)$r['total_qty'];
            }
        }

        // DT detail per machine (try detail table first, fallback to hourly aggregate)
        $dtDetailMap = [];
        if ($db->tableExists('machining_hourly_downtime_details')) {
            $dtRows = $db->query("
                SELECT mh.machine_id, COALESCE(dc.downtime_name, 'Dandori') as dt_name, SUM(dd.downtime_minute) as total_mins
                FROM machining_hourly_downtime_details dd
                JOIN machining_hourly mh ON mh.id = dd.machining_hourly_id
                LEFT JOIN downtime_categories dc ON dc.id = dd.downtime_category_id AND dd.downtime_category_id > 0
                WHERE mh.production_date = ?
                GROUP BY mh.machine_id, dt_name
            ", [$date])->getResultArray();
            foreach ($dtRows as $r) {
                $dtDetailMap[(int)$r['machine_id']][$r['dt_name']] = (int)$r['total_mins'];
            }
        } elseif ($db->fieldExists('downtime', 'machining_hourly')) {
            // Fallback: aggregate from hourly table downtime column
            $dtAgg = $db->query("
                SELECT machine_id, SUM(downtime) as total_mins
                FROM machining_hourly
                WHERE production_date = ? AND downtime > 0
                GROUP BY machine_id
            ", [$date])->getResultArray();
            foreach ($dtAgg as $r) {
                if ((int)$r['total_mins'] > 0) {
                    $dtDetailMap[(int)$r['machine_id']]['Downtime'] = (int)$r['total_mins'];
                }
            }
        }

        $items = [];
        foreach ($schedRows as $s) {
            $mId = $s['machine_id'];
            $sId = $s['shift_id'];
            $key = $mId . '_' . $sId;
            $hm = $hourlyMap[$key] ?? ['fg' => 0, 'ng' => 0, 'slot_count' => 0, 'downtime' => 0];
            
            $items[] = [
                'machine_id' => $mId,
                'shift_id'   => $sId,
                'shift_name' => $s['shift_name'] ?? 'Unknown',
                'machine_code' => $s['machine_code'],
                'line_position' => $s['line_position'],
                'target' => (int)$s['target'],
                'fg' => $hm['fg'],
                'ng' => $hm['ng'],
                'slot_count' => $hm['slot_count'],
                'downtime' => $hm['downtime']
            ];
            unset($hourlyMap[$key]);
        }

        foreach ($hourlyMap as $key => $hm) {
            list($mId, $sId) = explode('_', $key);
            $m = $db->table('machines')->select('machine_code, line_position')->where('id', $mId)->get()->getRowArray();
            $items[] = [
                'machine_id' => $mId,
                'shift_id' => $sId,
                'shift_name' => $hm['shift_name'] ?? 'Unknown',
                'machine_code' => $m['machine_code'] ?? 'Unknown',
                'line_position' => $m['line_position'] ?? 999,
                'target' => 0,
                'fg' => $hm['fg'],
                'ng' => $hm['ng'],
                'slot_count' => $hm['slot_count'],
                'downtime' => $hm['downtime']
            ];
        }

        return $this->computeStats($items, $ngDetailMap, $dtDetailMap);
    }

    private function computeStats($items, $ngDetailMap = [], $dtDetailMap = [])
    {
        $machineAgg = [];
        $shiftAgg = [];
        
        $totalTarget = 0;
        $totalFG = 0;
        $totalNG = 0;
        $totalDowntime = 0;
        $totalSlots = 0;

        foreach ($items as $it) {
            // Machine aggregation
            $mId = $it['machine_id'];
            if (!isset($machineAgg[$mId])) {
                $machineAgg[$mId] = [
                    'machine_code' => $it['machine_code'],
                    'line_position' => $it['line_position'],
                    'target' => 0, 'fg' => 0, 'ng' => 0, 'downtime' => 0, 'slot_count' => 0
                ];
            }
            $machineAgg[$mId]['target'] += $it['target'];
            $machineAgg[$mId]['fg'] += $it['fg'];
            $machineAgg[$mId]['ng'] += $it['ng'];
            $machineAgg[$mId]['downtime'] += $it['downtime'];
            $machineAgg[$mId]['slot_count'] += $it['slot_count'];

            // Shift aggregation
            $sId = $it['shift_id'];
            $sName = $it['shift_name'];
            if (!isset($shiftAgg[$sName])) {
                $shiftAgg[$sName] = [
                    'shift_name' => $sName,
                    'target' => 0, 'fg' => 0, 'ng' => 0, 'downtime' => 0, 'slot_count' => 0
                ];
            }
            $shiftAgg[$sName]['target'] += $it['target'];
            $shiftAgg[$sName]['fg'] += $it['fg'];
            $shiftAgg[$sName]['ng'] += $it['ng'];
            $shiftAgg[$sName]['downtime'] += $it['downtime'];
            $shiftAgg[$sName]['slot_count'] += $it['slot_count'];

            // Overall aggregation
            $totalTarget += $it['target'];
            $totalFG += $it['fg'];
            $totalNG += $it['ng'];
            $totalDowntime += $it['downtime'];
            $totalSlots += $it['slot_count'];
        }

        // Format machines
        $machines = [];
        usort($machineAgg, function($a, $b) { return $a['line_position'] <=> $b['line_position']; });
        foreach ($machineAgg as $mId => $m) {
            $okAch = $m['target'] > 0 ? max(0, min(100, ($m['fg'] / $m['target']) * 100)) : ($m['fg'] > 0 ? 100 : 0);
            $totProd = $m['fg'] + $m['ng'];
            $ngRt = $totProd > 0 ? max(0, min(100, ($m['ng'] / $totProd) * 100)) : 0;
            $dtRt = $m['slot_count'] > 0 ? max(0, min(100, ($m['downtime'] / ($m['slot_count'] * 60)) * 100)) : 0;
            
            $machines[] = [
                'machine_id' => $mId,
                'machine_code' => $m['machine_code'],
                'target' => $m['target'],
                'fg' => $m['fg'],
                'ng' => $m['ng'],
                'ok_achievement' => round($okAch, 1),
                'ng_rate' => round($ngRt, 1),
                'dt_rate' => round($dtRt, 1),
                'ng_details' => $ngDetailMap[(int)$mId] ?? [],
                'dt_details' => $dtDetailMap[(int)$mId] ?? [],
            ];
        }

        // Format shifts
        $shifts = [];
        ksort($shiftAgg);
        foreach ($shiftAgg as $sName => $s) {
            $okAch = $s['target'] > 0 ? max(0, min(100, ($s['fg'] / $s['target']) * 100)) : ($s['fg'] > 0 ? 100 : 0);
            $totProd = $s['fg'] + $s['ng'];
            $ngRt = $totProd > 0 ? max(0, min(100, ($s['ng'] / $totProd) * 100)) : 0;
            $dtRt = $s['slot_count'] > 0 ? max(0, min(100, ($s['downtime'] / ($s['slot_count'] * 60)) * 100)) : 0;
            
            $shifts[] = [
                'shift_name' => $s['shift_name'],
                'target' => $s['target'],
                'fg' => $s['fg'],
                'ng' => $s['ng'],
                'ok_achievement' => round($okAch, 1),
                'ng_rate' => round($ngRt, 1),
                'dt_rate' => round($dtRt, 1)
            ];
        }

        $overallOk = $totalTarget > 0 ? max(0, min(100, ($totalFG / $totalTarget) * 100)) : ($totalFG > 0 ? 100 : 0);
        $overallProd = $totalFG + $totalNG;
        $overallNg = $overallProd > 0 ? max(0, min(100, ($totalNG / $overallProd) * 100)) : 0;
        $overallDt = $totalSlots > 0 ? max(0, min(100, ($totalDowntime / ($totalSlots * 60)) * 100)) : 0;

        return [
            'okAchievement' => round($overallOk, 1),
            'ngRate' => round($overallNg, 1),
            'downtimeRate' => round($overallDt, 1),
            'machines' => $machines,
            'shifts' => $shifts
        ];
    }
}
