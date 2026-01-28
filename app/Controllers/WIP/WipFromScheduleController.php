<?php

namespace App\Controllers\WIP;

use App\Controllers\BaseController;

class WipFromScheduleController extends BaseController
{
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

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // ✅ deteksi kolom tanggal WIP yang benar
        $wipDateCol = $this->detectWipDateColumn($db);

        /**
         * ==========================================================
         * QUERY 1: WIP source = daily_schedule_items
         * ==========================================================
         * Tujuan: dapatkan shift_id + section dari daily_schedules
         */
        $q1 = $db->table('production_wip pw')
            ->select("
                pw.{$wipDateCol} AS wip_date,
                ds.section,
                ds.shift_id,
                s.shift_name,
                pw.qty,
                pw.status,
                pp_from.process_name AS from_process_name,
                pp_to.process_name   AS to_process_name
            ")
            ->join('daily_schedule_items dsi', "dsi.id = pw.source_id", 'inner')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id', 'inner')
            ->join('shifts s', 's.id = ds.shift_id', 'left')
            ->join('production_processes pp_from', 'pp_from.id = pw.from_process_id', 'left')
            ->join('production_processes pp_to', 'pp_to.id = pw.to_process_id', 'left')
            ->where('pw.source_table', 'daily_schedule_items')
            ->where("pw.{$wipDateCol}", $date);

        $sql1 = $q1->getCompiledSelect();

        /**
         * ==========================================================
         * QUERY 2: WIP source = die_casting_production
         * ==========================================================
         * Tujuan: dapatkan shift_id dari die_casting_production
         */
        $q2 = $db->table('production_wip pw')
            ->select("
                pw.{$wipDateCol} AS wip_date,
                pp_from.process_name AS section,
                dcp.shift_id,
                s.shift_name,
                pw.qty,
                pw.status,
                pp_from.process_name AS from_process_name,
                pp_to.process_name   AS to_process_name
            ")
            ->join('die_casting_production dcp', 'dcp.id = pw.source_id', 'inner')
            ->join('shifts s', 's.id = dcp.shift_id', 'left')
            ->join('production_processes pp_from', 'pp_from.id = pw.from_process_id', 'left')
            ->join('production_processes pp_to', 'pp_to.id = pw.to_process_id', 'left')
            ->where('pw.source_table', 'die_casting_production')
            ->where("pw.{$wipDateCol}", $date);

        $sql2 = $q2->getCompiledSelect();

        /**
         * UNION semua sumber WIP
         */
        $unionSql = "
            ({$sql1})
            UNION ALL
            ({$sql2})
            ORDER BY shift_id ASC, section ASC
        ";

        $rows = $db->query($unionSql)->getResultArray();

        /**
         * ==========================================================
         * PIVOT SUMMARY
         * ==========================================================
         * definisi:
         * - IN   : qty yang masuk ke section  (to_process_name = section)
         * - OUT  : qty yang keluar dari section (from_process_name = section)
         * - STOCK: IN - OUT (min 0)
         * - STATUS: breakdown qty by status untuk IN (yang menuju section)
         */
        $summary = []; // [shift_id => ['shift_name'=>..., 'sections'=> [section => data]]]

        foreach ($rows as $r) {
            $sid       = (int)($r['shift_id'] ?? 0);
            $shiftName = $r['shift_name'] ?? ('Shift ID ' . $sid);

            if (!isset($summary[$sid])) {
                $summary[$sid] = [
                    'shift_id'   => $sid,
                    'shift_name' => $shiftName,
                    'sections'   => []
                ];
            }

            $qty    = (int)($r['qty'] ?? 0);
            $status = strtoupper((string)($r['status'] ?? ''));

            $from = (string)($r['from_process_name'] ?? '');
            $to   = (string)($r['to_process_name'] ?? '');

            // OUT: dari section (from)
            if ($from !== '') {
                if (!isset($summary[$sid]['sections'][$from])) {
                    $summary[$sid]['sections'][$from] = [
                        'in' => 0,
                        'out' => 0,
                        'stock' => 0,
                        'status' => [
                            'WAITING' => 0,
                            'SCHEDULED' => 0,
                            'DONE' => 0,
                            'OTHER' => 0
                        ],
                    ];
                }
                $summary[$sid]['sections'][$from]['out'] += $qty;
            }

            // IN: ke section (to)
            if ($to !== '') {
                if (!isset($summary[$sid]['sections'][$to])) {
                    $summary[$sid]['sections'][$to] = [
                        'in' => 0,
                        'out' => 0,
                        'stock' => 0,
                        'status' => [
                            'WAITING' => 0,
                            'SCHEDULED' => 0,
                            'DONE' => 0,
                            'OTHER' => 0
                        ],
                    ];
                }
                $summary[$sid]['sections'][$to]['in'] += $qty;

                if (isset($summary[$sid]['sections'][$to]['status'][$status])) {
                    $summary[$sid]['sections'][$to]['status'][$status] += $qty;
                } else {
                    $summary[$sid]['sections'][$to]['status']['OTHER'] += $qty;
                }
            }
        }

        // hitung stock = in - out (min 0) & sort section
        foreach ($summary as $sid => $data) {
            foreach ($data['sections'] as $sec => $v) {
                $stock = (int)$v['in'] - (int)$v['out'];
                $summary[$sid]['sections'][$sec]['stock'] = max(0, $stock);
            }
            ksort($summary[$sid]['sections']);
        }

        return view('wip/from_schedule/index', [
            'date'    => $date,
            'summary' => $summary,
        ]);
    }
}
