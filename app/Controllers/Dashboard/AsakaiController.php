<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class AsakaiController extends BaseController
{
    public function index()
    {
        $db      = db_connect();
        $date    = $this->request->getGet('date') ?? date('Y-m-d');
        $section = trim((string)$this->request->getGet('section'));
        $export  = $this->request->getGet('export');

        // =================================================================
        // 1. DEFINISIKAN URUTAN PROSES (Tanpa FINISHED GOOD)
        // =================================================================
        $processOrder = [
            'RAW MATERIAL',
            'DIE CASTING',
            'BURRYTORY',
            'BARITORI',       // Tambahan antisipasi jika penulisan DB berbeda
            'SAND BLASTING',
            'SHOT BLASTING',  // Tambahan antisipasi jika penulisan DB berbeda
            'MACHINING',
            'LEAK TEST',
            'JIG PLUG',
            'ASSY BUSHING',
            'ASSY SHAFT',
            'PAINTING',
            'FINAL INSPECTION'
        ];

        // Daftar nama proses yang ingin dikecualikan / di-hide dari Asakai
        $excludedSections = ['FINISHED GOOD', 'FINISH GOOD'];

        // Ambil list semua section (process_name) dari database
        // dan kecualikan (exclude) Finished Good
        $allSections = $db->table('production_processes')
            ->select('process_name')
            ->whereNotIn('process_name', $excludedSections)
            ->groupBy('process_name')
            ->get()
            ->getResultArray();

        $sectionsList = array_column($allSections, 'process_name');

        // 2. LAKUKAN SORTING CUSTOM PADA PHP
        usort($sectionsList, function ($a, $b) use ($processOrder) {
            // Cari posisi index (Abaikan huruf besar/kecil dengan strtoupper)
            $posA = array_search(strtoupper(trim($a)), $processOrder);
            $posB = array_search(strtoupper(trim($b)), $processOrder);

            // Jika nama proses dari DB tidak ada di daftar di atas, taruh di paling belakang (999)
            $posA = ($posA === false) ? 999 : $posA;
            $posB = ($posB === false) ? 999 : $posB;

            // Bandingkan posisi
            return $posA <=> $posB;
        });

        // Ambil list Shift (aktif)
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->orderBy('shift_code', 'ASC')
            ->get()
            ->getResultArray();

        $summaryData = [];

        if ($section === '') {
            // Karena $sectionsList sudah diurutkan dan Finished Good dibuang,
            // data summary yang akan ditarik dan ditampilkan juga otomatis sesuai
            foreach ($sectionsList as $sec) {
                $summaryData[$sec] = $this->getSummaryData($db, $sec, $date, $shifts);
            }
        } else {
            // Cek jika user memaksa akses section Finished Good via URL
            if (!in_array(strtoupper($section), $excludedSections)) {
                $summaryData[$section] = $this->getSummaryData($db, $section, $date, $shifts);
            }
        }

        // =================================================================
        // LOGIKA EXPORT EXCEL
        // =================================================================
        if ($export === 'excel') {
            $filename = "Asakai_Summary_{$date}.xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            echo view('dashboard/asakai/excel', [
                'date'        => $date,
                'selectedSec' => $section,
                'shifts'      => $shifts,
                'summaryData' => $summaryData
            ]);
            exit;
        }

        return view('dashboard/asakai/index', [
            'date'         => $date,
            'selectedSec'  => $section,
            'sectionsList' => $sectionsList,
            'shifts'       => $shifts,
            'summaryData'  => $summaryData
        ]);
    }

    /**
     * Helper mengambil data dikelompokkan berdasarkan Produk per Shift
     */
    private function getSummaryData($db, $sectionName, $date, $shifts)
    {
        $process = $db->table('production_processes')
            ->where('process_name', $sectionName)
            ->get()
            ->getRowArray();

        if (!$process) return [];
        $processId = (int)$process['id'];

        $productsMap = [];

        /* ================= 1. AMBIL PLAN (Target) ================= */
        $targets = $db->table('daily_schedules ds')
            ->select('ds.shift_id, dsi.product_id, p.part_no, p.part_name, SUM(dsi.target_per_shift) as target')
            ->join('daily_schedule_items dsi', 'dsi.daily_schedule_id = ds.id')
            ->join('products p', 'p.id = dsi.product_id', 'left')
            ->where('ds.process_id', $processId)
            ->where('ds.schedule_date', $date)
            ->groupBy('ds.shift_id, dsi.product_id, p.part_no, p.part_name')
            ->get()->getResultArray();

        foreach ($targets as $t) {
            $pid = (int)$t['product_id'];
            $sid = (int)$t['shift_id'];
            if (!isset($productsMap[$pid])) {
                $productsMap[$pid] = ['part_no' => $t['part_no'], 'part_name' => $t['part_name'], 'shifts' => []];
            }
            $productsMap[$pid]['shifts'][$sid]['target'] = (int)$t['target'];
        }

        /* ================= 2. AMBIL ACTUAL (FG dari WIP) ================= */
        $wipDateCol  = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';
        $hasWipShift = $db->fieldExists('shift_id', 'production_wip'); 

        $wipQuery = $db->table('production_wip pw')
            ->select('pw.product_id, p.part_no, p.part_name, pw.qty_in')
            ->join('products p', 'p.id = pw.product_id', 'left')
            ->where("pw.{$wipDateCol}", $date)
            ->where('pw.to_process_id', $processId);

        if ($hasWipShift) {
            $wipQuery->select('pw.shift_id');
        } else {
            // Lacak shift_id dari tabel sumber
            $wipQuery->select('COALESCE(dsi.shift_id, mt.shift_id, dcp.shift_id, dch.shift_id, mh.shift_id) as shift_id')
                     ->join('daily_schedule_items dsi', "pw.source_table = 'daily_schedule_items' AND pw.source_id = dsi.id", 'left')
                     ->join('material_transactions mt', "pw.source_table = 'material_transactions' AND pw.source_id = mt.id", 'left')
                     ->join('die_casting_production dcp', "pw.source_table = 'die_casting_production' AND pw.source_id = dcp.id", 'left')
                     ->join('die_casting_hourly dch', "pw.source_table = 'die_casting_hourly' AND pw.source_id = dch.id", 'left')
                     ->join('machining_hourly mh', "pw.source_table = 'machining_hourly' AND pw.source_id = mh.id", 'left');
        }

        $actuals = $wipQuery->get()->getResultArray();

        // Agregasi manual di PHP agar menghindari error SQL ONLY_FULL_GROUP_BY
        foreach ($actuals as $a) {
            $pid = (int)$a['product_id'];
            $sid = (int)($a['shift_id'] ?? 0);
            
            if (!isset($productsMap[$pid])) {
                $productsMap[$pid] = ['part_no' => $a['part_no'], 'part_name' => $a['part_name'], 'shifts' => []];
            }
            
            if (!isset($productsMap[$pid]['shifts'][$sid]['fg'])) {
                $productsMap[$pid]['shifts'][$sid]['fg'] = 0;
            }
            $productsMap[$pid]['shifts'][$sid]['fg'] += (int)$a['qty_in'];
        }

        /* ================= 3. FORMAT HASIL & HITUNG EFF ================= */
        $result = [];
        foreach ($productsMap as $pid => $data) {
            $totalTarget = 0;
            $totalFg     = 0;
            $shiftOutput = [];

            foreach ($shifts as $shift) {
                $sid = (int)$shift['id'];
                $t = $data['shifts'][$sid]['target'] ?? 0;
                $f = $data['shifts'][$sid]['fg'] ?? 0;
                $e = $t > 0 ? round(($f / $t) * 100, 1) : 0;

                $shiftOutput[$sid] = ['target' => $t, 'fg' => $f, 'eff' => $e];
                $totalTarget += $t;
                $totalFg     += $f;
            }

            $totalEff = $totalTarget > 0 ? round(($totalFg / $totalTarget) * 100, 1) : 0;

            $result[$pid] = [
                'part_no'      => $data['part_no'],
                'part_name'    => $data['part_name'],
                'shifts'       => $shiftOutput,
                'total_target' => $totalTarget,
                'total_fg'     => $totalFg,
                'total_eff'    => $totalEff
            ];
        }

        // Urutkan berdasarkan Part Number
        usort($result, function($a, $b) {
            return strcmp($a['part_no'], $b['part_no']);
        });

        return $result;
    }
}