<?php

namespace App\Controllers\QC;

use App\Controllers\BaseController;

class SummaryDefectOngoingController extends BaseController
{
    private function getReportData($db, $year, $productId)
    {
        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        $data = [];
        $yearTotals = [
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

        for ($i = 1; $i <= 12; $i++) {
            $data[$i] = [
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
                // Determine which month this production_date belongs to
                $monthObj = (int)date('n', strtotime($ins['production_date'])); // 1 through 12
                if (!isset($data[$monthObj])) continue;

                $shift = (int)$ins['shift_code'];
                if (in_array($shift, [1,2,3])) {
                    $data[$monthObj]['shifts'][$shift]['ok'] += $ins['qty_ok'];
                    $data[$monthObj]['shifts'][$shift]['ng'] += $ins['qty_ng'];

                    $yearTotals['shifts'][$shift]['ok'] += $ins['qty_ok'];
                    $yearTotals['shifts'][$shift]['ng'] += $ins['qty_ng'];
                }

                $data[$monthObj]['total_inspection'] += $ins['qty_in'];
                $data[$monthObj]['total_ok'] += $ins['qty_ok'];
                $data[$monthObj]['reject_total'] += $ins['qty_ng'];

                $yearTotals['total_inspection'] += $ins['qty_in'];
                $yearTotals['total_ok'] += $ins['qty_ok'];
                $yearTotals['reject_total'] += $ins['qty_ng'];

                $processName = strtoupper($ins['process_name'] ?? '');
                if (str_contains($processName, 'DIE CASTING') || str_contains($processName, 'DC')) {
                    $data[$monthObj]['reject_dc'] += $ins['qty_ng'];
                    $yearTotals['reject_dc'] += $ins['qty_ng'];
                } elseif (str_contains($processName, 'BARITORI') || str_contains($processName, 'BT')) {
                    $data[$monthObj]['reject_bt'] += $ins['qty_ng'];
                    $yearTotals['reject_bt'] += $ins['qty_ng'];
                } elseif (str_contains($processName, 'MACHINING') || str_contains($processName, 'MC')) {
                    $data[$monthObj]['reject_mc'] += $ins['qty_ng'];
                    $yearTotals['reject_mc'] += $ins['qty_ng'];
                }

                if (isset($ngMap[$ins['id']])) {
                    foreach ($ngMap[$ins['id']] as $n) {
                        $catId = $n['ng_category_id'];
                        if (!isset($data[$monthObj]['ng_categories'][$catId])) {
                            $data[$monthObj]['ng_categories'][$catId] = 0;
                        }
                        $data[$monthObj]['ng_categories'][$catId] += $n['qty'];

                        if (!isset($yearTotals['ng_categories'][$catId])) {
                            $yearTotals['ng_categories'][$catId] = 0;
                        }
                        $yearTotals['ng_categories'][$catId] += $n['qty'];
                    }
                }
            }
        }
        
        return [$data, $yearTotals];
    }

    public function index()
    {
        $db = db_connect();

        $year = $this->request->getGet('year') ?? date('Y');
        $productId = $this->request->getGet('product_id');

        $products = $db->table('products')->orderBy('part_name', 'ASC')->get()->getResultArray();
        $ngCategories = $db->table('ng_categories')
                            ->orderBy('process_name')
                            ->orderBy('ng_code')
                            ->get()->getResultArray();

        list($data, $yearTotals) = $this->getReportData($db, $year, $productId);

        $monthsLabels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        return view('qc/summary_defect_ongoing', [
            'year' => $year,
            'productId' => $productId,
            'products' => $products,
            'ngCategories' => $ngCategories,
            'data' => $data,
            'yearTotals' => $yearTotals,
            'monthsLabels' => $monthsLabels
        ]);
    }

    public function exportCsv()
    {
        $db = db_connect();

        $year = $this->request->getGet('year') ?? date('Y');
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

        list($data, $yearTotals) = $this->getReportData($db, $year, $productId);

        $monthsLabels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        $filename = 'QC_Summary_Defect_Yearly_' . $partName . '_' . $year . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        $f = fopen('php://output', 'w');
        
        // Build Header 1
        $header1 = ['Month', 'Shift 1', '', 'Shift 2', '', 'Shift 3', '', 'Total Inspection', 'Total OK', 'Reject Total', 'Reject DC', 'Reject BT', 'Reject MC'];
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
        for ($i = 1; $i <= 12; $i++) {
            $row = $data[$i];
            
            $csvRow = [
                $monthsLabels[$i],
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
            $yearTotals['shifts'][1]['ok'], $yearTotals['shifts'][1]['ng'],
            $yearTotals['shifts'][2]['ok'], $yearTotals['shifts'][2]['ng'],
            $yearTotals['shifts'][3]['ok'], $yearTotals['shifts'][3]['ng'],
            $yearTotals['total_inspection'],
            $yearTotals['total_ok'],
            $yearTotals['reject_total'],
            $yearTotals['reject_dc'],
            $yearTotals['reject_bt'],
            $yearTotals['reject_mc']
        ];
        
        foreach ($ngCategories as $cat) {
            $totalsRow[] = $yearTotals['ng_categories'][$cat['id']] ?? 0;
        }
        fputcsv($f, $totalsRow);

        fclose($f);
        exit;
    }
}
