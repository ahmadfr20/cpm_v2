<?php

namespace App\Controllers\WIP;

use App\Controllers\BaseController;

class WipFromScheduleController extends BaseController
{
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip')) return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip')) return 'wip_date';

        throw new \Exception('Tabel production_wip tidak punya kolom tanggal (production_date / schedule_date / wip_date).');
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // filter section = nama process (Die Casting, Machining, dll)
        $section = $this->request->getGet('section');

        // list section dari master process
        $sections = $db->table('production_processes')
            ->select('process_name')
            ->orderBy('process_name', 'ASC')
            ->get()
            ->getResultArray();

        $sectionList = array_map(fn($r) => $r['process_name'], $sections);

        // ✅ deteksi kolom tanggal WIP yg benar
        $wipDateCol = $this->detectWipDateColumn($db);

        /**
         * ==========================================================
         * QUERY 1: WIP source = daily_schedule_items
         * ==========================================================
         * Catatan:
         * - join daily_schedule_items & daily_schedules dipakai untuk ambil machine/shift/section
         * - jika join gagal, artinya data schedule/source_id bermasalah
         */
        $q1 = $db->table('production_wip pw')
            ->select("
                pw.{$wipDateCol} AS schedule_date,
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
            ->where("pw.{$wipDateCol}", $date);

        if (!empty($section)) {
            // untuk source schedule, section ada di ds.section
            $q1->where('ds.section', $section);
        }

        $sql1 = $q1->getCompiledSelect();

        /**
         * ==========================================================
         * QUERY 2: WIP source = die_casting_production
         * ==========================================================
         */
        $q2 = $db->table('production_wip pw')
            ->select("
                pw.{$wipDateCol} AS schedule_date,
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
            ->where("pw.{$wipDateCol}", $date);

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

        // group per shift
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
