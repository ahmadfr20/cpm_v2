<?php

namespace App\Controllers\WIP;

use App\Controllers\BaseController;

class WipFromScheduleController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // filter section = nama process (contoh: Die Casting, Machining, dll)
        $section = $this->request->getGet('section');

        /**
         * Ambil list section dari production_processes (lebih aman & universal)
         * karena WIP selalu punya from_process_id
         */
        $sections = $db->table('production_processes')
            ->select('process_name')
            ->orderBy('process_name', 'ASC')
            ->get()
            ->getResultArray();

        $sectionList = array_map(fn($r) => $r['process_name'], $sections);

        /**
         * ==========================================================
         * QUERY 1: WIP source = daily_schedule_items (kalau ada)
         * ==========================================================
         */
        $q1 = $db->table('production_wip pw')
            ->select("
                pw.production_date AS schedule_date,
                ds.section,
                ds.shift_id,
                s.shift_name,

                dsi.machine_id,
                m.machine_code,
                m.machine_name,
                m.line_position,

                dsi.product_id,
                p.part_no,
                p.part_name,

                pw.qty,
                pw.status,
                pw.from_process_id,
                pw.to_process_id,
                pp_from.process_name AS from_process_name,
                pp_to.process_name   AS to_process_name
            ")
            ->join('daily_schedule_items dsi', "dsi.id = pw.source_id", 'inner')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id', 'inner')
            ->join('shifts s', 's.id = ds.shift_id', 'left')
            ->join('machines m', 'm.id = dsi.machine_id', 'left')
            ->join('products p', 'p.id = dsi.product_id', 'left')
            ->join('production_processes pp_from', 'pp_from.id = pw.from_process_id', 'left')
            ->join('production_processes pp_to', 'pp_to.id = pw.to_process_id', 'left')
            ->where('pw.source_table', 'daily_schedule_items')
            ->where('pw.production_date', $date);

        if (!empty($section)) {
            // untuk source schedule, section ada di ds.section
            $q1->where('ds.section', $section);
        }

        $sql1 = $q1->getCompiledSelect();

        /**
         * ==========================================================
         * QUERY 2: WIP source = die_casting_production (yang kamu pakai sekarang)
         * ==========================================================
         */
        $q2 = $db->table('production_wip pw')
            ->select("
                pw.production_date AS schedule_date,
                pp_from.process_name AS section,
                dcp.shift_id,
                s.shift_name,

                dcp.machine_id,
                m.machine_code,
                m.machine_name,
                m.line_position,

                dcp.product_id,
                p.part_no,
                COALESCE(dcp.part_label, p.part_name) AS part_name,

                pw.qty,
                pw.status,
                pw.from_process_id,
                pw.to_process_id,
                pp_from.process_name AS from_process_name,
                pp_to.process_name   AS to_process_name
            ")
            ->join('die_casting_production dcp', 'dcp.id = pw.source_id', 'inner')
            ->join('shifts s', 's.id = dcp.shift_id', 'left')
            ->join('machines m', 'm.id = dcp.machine_id', 'left')
            ->join('products p', 'p.id = dcp.product_id', 'left')
            ->join('production_processes pp_from', 'pp_from.id = pw.from_process_id', 'left')
            ->join('production_processes pp_to', 'pp_to.id = pw.to_process_id', 'left')
            ->where('pw.source_table', 'die_casting_production')
            ->where('pw.production_date', $date);

        if (!empty($section)) {
            // untuk source die_casting_production, section = from_process_name
            $q2->where('pp_from.process_name', $section);
        }

        $sql2 = $q2->getCompiledSelect();

        /**
         * UNION semua sumber WIP
         */
        $unionSql = "
            ({$sql1})
            UNION ALL
            ({$sql2})
            ORDER BY shift_id ASC, line_position ASC, part_no ASC
        ";

        $rows = $db->query($unionSql)->getResultArray();

        /**
         * Group per shift supaya tampil seperti hourly
         */
        $grouped = [];
        foreach ($rows as $r) {
            $sid = (int)($r['shift_id'] ?? 0);

            if (!isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'shift_id'   => $sid,
                    'shift_name' => $r['shift_name'] ?? ('Shift ID ' . $sid),
                    'items'      => []
                ];
            }
            $grouped[$sid]['items'][] = $r;
        }

        return view('wip/from_schedule/index', [
            'date'        => $date,
            'section'     => $section,
            'sectionList' => $sectionList,
            'grouped'     => $grouped,
        ]);
    }
}
