<?php

namespace App\Controllers\QC;

use App\Controllers\BaseController;

class DefectOngoingController extends BaseController
{
    private function getReportData($db, $month, $productId)
    {
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $daysInMonth = (int) date('t', strtotime($startDate));

        $data = [];
        $monthTotals = [
            'shifts' => [
                1 => ['ok' => 0, 'ng' => 0],
                2 => ['ok' => 0, 'ng' => 0],
                3 => ['ok' => 0, 'ng' => 0]
            ],
            'total_inspection' => 0,
            'total_ok' => 0,
            'reject_total' => 0,
            'reject_dc' => 0,
            'reject_bt' => 0,
            'reject_mc' => 0,
            'ng_categories' => []
        ];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $dateStr = sprintf('%s-%02d', $month, $i);
            $data[$dateStr] = [
                'shifts' => [
                    1 => ['ok' => 0, 'ng' => 0],
                    2 => ['ok' => 0, 'ng' => 0],
                    3 => ['ok' => 0, 'ng' => 0]
                ],
                'total_inspection' => 0,
                'total_ok' => 0,
                'reject_total' => 0,
                'reject_dc' => 0,
                'reject_bt' => 0,
                'reject_mc' => 0,
                'ng_categories' => []
            ];
        }

        if ($productId) {
            $inspections = $db->table('qc_inspections qc')
                ->select('qc.id, qc.production_date, qc.qty_in, qc.qty_ok, qc.qty_ng, s.shift_code, pp.process_name')
                ->join('shifts s', 's.id = qc.shift_id', 'left')
                ->join('production_processes pp', 'pp.id = qc.source_process_id', 'left')
                ->where('qc.product_id', $productId)
                ->where('qc.production_date >=', $startDate)
                ->where('qc.production_date <=', $endDate)
                ->get()->getResultArray();

            $inspectionIds = array_column($inspections, 'id');
            $ngMap = [];

            if (!empty($inspectionIds)) {
                $ngs = $db->table('qc_inspection_ngs qn')
                    ->select('qn.qc_inspection_id, qn.qty, nc.id as ng_category_id')
                    ->join('ng_categories nc', 'nc.id = qn.ng_category_id', 'left')
                    ->whereIn('qn.qc_inspection_id', $inspectionIds)
                    ->get()->getResultArray();

                foreach ($ngs as $ng) {
                    $ngMap[$ng['qc_inspection_id']][] = $ng;
                }
            }

            foreach ($inspections as $ins) {
                $date = $ins['production_date'];
                if (!isset($data[$date])) continue;

                $shift = (int)$ins['shift_code'];
                if (in_array($shift, [1,2,3])) {
                    $data[$date]['shifts'][$shift]['ok'] += $ins['qty_ok'];
                    $data[$date]['shifts'][$shift]['ng'] += $ins['qty_ng'];

                    $monthTotals['shifts'][$shift]['ok'] += $ins['qty_ok'];
                    $monthTotals['shifts'][$shift]['ng'] += $ins['qty_ng'];
                }

                $data[$date]['total_inspection'] += $ins['qty_in'];
                $data[$date]['total_ok'] += $ins['qty_ok'];
                $data[$date]['reject_total'] += $ins['qty_ng'];

                $monthTotals['total_inspection'] += $ins['qty_in'];
                $monthTotals['total_ok'] += $ins['qty_ok'];
                $monthTotals['reject_total'] += $ins['qty_ng'];

                $processName = strtoupper($ins['process_name'] ?? '');
                if (str_contains($processName, 'DIE CASTING') || str_contains($processName, 'DC')) {
                    $data[$date]['reject_dc'] += $ins['qty_ng'];
                    $monthTotals['reject_dc'] += $ins['qty_ng'];
                } elseif (str_contains($processName, 'BARITORI') || str_contains($processName, 'BT')) {
                    $data[$date]['reject_bt'] += $ins['qty_ng'];
                    $monthTotals['reject_bt'] += $ins['qty_ng'];
                } elseif (str_contains($processName, 'MACHINING') || str_contains($processName, 'MC')) {
                    $data[$date]['reject_mc'] += $ins['qty_ng'];
                    $monthTotals['reject_mc'] += $ins['qty_ng'];
                }

                if (isset($ngMap[$ins['id']])) {
                    foreach ($ngMap[$ins['id']] as $n) {
                        $catId = $n['ng_category_id'];
                        if (!isset($data[$date]['ng_categories'][$catId])) {
                            $data[$date]['ng_categories'][$catId] = 0;
                        }
                        $data[$date]['ng_categories'][$catId] += $n['qty'];

                        if (!isset($monthTotals['ng_categories'][$catId])) {
                            $monthTotals['ng_categories'][$catId] = 0;
                        }
                        $monthTotals['ng_categories'][$catId] += $n['qty'];
                    }
                }
            }
        }
        
        return [$data, $monthTotals, $daysInMonth];
    }

    public function index()
    {
        $db = db_connect();

        $month = $this->request->getGet('month') ?? date('Y-m');
        $productId = $this->request->getGet('product_id');

        $products = $db->table('products')->orderBy('part_name', 'ASC')->get()->getResultArray();
        $ngCategories = $db->table('ng_categories')
                            ->orderBy('process_name')
                            ->orderBy('ng_code')
                            ->get()->getResultArray();

        list($data, $monthTotals, $daysInMonth) = $this->getReportData($db, $month, $productId);

        return view('qc/defect_ongoing', [
            'month' => $month,
            'productId' => $productId,
            'products' => $products,
            'ngCategories' => $ngCategories,
            'daysInMonth' => $daysInMonth,
            'data' => $data,
            'monthTotals' => $monthTotals
        ]);
    }

    public function exportCsv()
    {
        $db = db_connect();

        $month = $this->request->getGet('month') ?? date('Y-m');
        $productId = $this->request->getGet('product_id');

        $products = $db->table('products')->orderBy('part_name', 'ASC')->get()->getResultArray();
        $ngCategories = $db->table('ng_categories')
                            ->orderBy('process_name')
                            ->orderBy('ng_code')
                            ->get()->getResultArray();

        $partName = 'ALL';
        if ($productId) {
            foreach ($products as $p) {
                if ($p['id'] == $productId) {
                    $partName = $p['part_no'] . '_' . $p['part_name'];
                    break;
                }
            }
        }

        list($data, $monthTotals, $daysInMonth) = $this->getReportData($db, $month, $productId);

        $filename = 'QC_Defect_Ongoing_' . $partName . '_' . $month . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        $f = fopen('php://output', 'w');
        
        // Build Header 1
        $header1 = ['Date', 'Shift 1', '', 'Shift 2', '', 'Shift 3', '', 'Total Inspection', 'Total OK', 'Reject Total', 'Reject DC', 'Reject BT', 'Reject MC'];
        foreach ($ngCategories as $cat) {
            $header1[] = $cat['ng_name'];
        }
        fputcsv($f, $header1);

        // Build Header 2
        $header2 = [''];
        for ($s = 1; $s <= 3; $s++) {
            $header2[] = 'OK';
            $header2[] = 'NG';
        }
        fputcsv($f, $header2);

        // Build Rows
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $dateStr = sprintf('%s-%02d', $month, $i);
            $row = $data[$dateStr];
            
            $csvRow = [
                date('d', strtotime($dateStr)),
                $row['shifts'][1]['ok'], $row['shifts'][1]['ng'],
                $row['shifts'][2]['ok'], $row['shifts'][2]['ng'],
                $row['shifts'][3]['ok'], $row['shifts'][3]['ng'],
                $row['total_inspection'],
                $row['total_ok'],
                $row['reject_total'],
                $row['reject_dc'],
                $row['reject_bt'],
                $row['reject_mc']
            ];
            
            foreach ($ngCategories as $cat) {
                $csvRow[] = $row['ng_categories'][$cat['id']] ?? 0;
            }
            fputcsv($f, $csvRow);
        }

        // Build Totals Row
        $totalsRow = [
            'TOTAL',
            $monthTotals['shifts'][1]['ok'], $monthTotals['shifts'][1]['ng'],
            $monthTotals['shifts'][2]['ok'], $monthTotals['shifts'][2]['ng'],
            $monthTotals['shifts'][3]['ok'], $monthTotals['shifts'][3]['ng'],
            $monthTotals['total_inspection'],
            $monthTotals['total_ok'],
            $monthTotals['reject_total'],
            $monthTotals['reject_dc'],
            $monthTotals['reject_bt'],
            $monthTotals['reject_mc']
        ];
        
        foreach ($ngCategories as $cat) {
            $totalsRow[] = $monthTotals['ng_categories'][$cat['id']] ?? 0;
        }
        fputcsv($f, $totalsRow);

        fclose($f);
        exit;
    }
}
