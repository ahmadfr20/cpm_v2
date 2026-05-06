<?php

namespace App\Controllers\QC;

use App\Controllers\BaseController;

class CompletedItemsController extends BaseController
{
    private function getData($db, $filterType, $filterValue, $shiftId)
    {
        $builder = $db->table('products p')
            ->select('p.id, p.part_no, p.part_name, COALESCE(SUM(qc.qty_in), 0) as qty_in, COALESCE(SUM(qc.qty_ok), 0) as qty_ok, COALESCE(SUM(qc.qty_ng), 0) as qty_ng');

        // Date condition
        $dateCond = "1=1";
        if ($filterType === 'day') {
            $dateCond = "qc.production_date = '" . $db->escapeString($filterValue) . "'";
        } elseif ($filterType === 'week') {
            if (preg_match('/^(\d{4})-W(\d{2})$/', $filterValue, $matches)) {
                $year = $matches[1];
                $week = $matches[2];
                $dto = new \DateTime();
                $dto->setISODate((int)$year, (int)$week);
                $start = $dto->format('Y-m-d');
                $dto->modify('+6 days');
                $end = $dto->format('Y-m-d');
                $dateCond = "qc.production_date BETWEEN '$start' AND '$end'";
            } else {
                $dateCond = "qc.production_date = '" . $db->escapeString($filterValue) . "'";
            }
        } elseif ($filterType === 'year') {
            $dateCond = "qc.production_date LIKE '" . $db->escapeString($filterValue) . "%'";
        } else {
            // month (default)
            $dateCond = "qc.production_date LIKE '" . $db->escapeString($filterValue) . "%'";
        }

        $joinCond = "qc.product_id = p.id AND $dateCond";
        if ($shiftId > 0) {
            $joinCond .= " AND qc.shift_id = " . (int)$shiftId;
        }

        $builder->join('qc_inspections qc', $joinCond, 'left');

        if ($db->fieldExists('is_active', 'products')) {
            $builder->where('p.is_active', 1);
        }

        return $builder->groupBy('p.id')->orderBy('p.part_no', 'ASC')->get()->getResultArray();
    }

    public function index()
    {
        $db = db_connect();
        
        $filterType = $this->request->getGet('filter_type') ?: 'month';
        
        // Default values based on type
        $defaultVal = date('Y-m');
        if ($filterType === 'day') $defaultVal = date('Y-m-d');
        if ($filterType === 'week') $defaultVal = date('Y-\WW');
        if ($filterType === 'year') $defaultVal = date('Y');
        
        $filterValue = $this->request->getGet('filter_value') ?: $defaultVal;
        $shiftId = (int)$this->request->getGet('shift_id');

        $shifts = $db->table('shifts')->where('is_active', 1)->get()->getResultArray();

        $completedItems = $this->getData($db, $filterType, $filterValue, $shiftId);

        // Calculate Summary
        $totalInspected = 0;
        $totalOk = 0;
        $totalNg = 0;
        
        foreach($completedItems as $item) {
            $totalInspected += (int)$item['qty_in'];
            $totalOk += (int)$item['qty_ok'];
            $totalNg += (int)$item['qty_ng'];
        }
        
        $ngPercentage = $totalInspected > 0 ? round(($totalNg / $totalInspected) * 100, 2) : 0;

        return view('qc/completed_items/index', [
            'filterType'     => $filterType,
            'filterValue'    => $filterValue,
            'shiftId'        => $shiftId,
            'shifts'         => $shifts,
            'completedItems' => $completedItems,
            'totalInspected' => $totalInspected,
            'totalOk'        => $totalOk,
            'totalNg'        => $totalNg,
            'ngPercentage'   => $ngPercentage
        ]);
    }

    public function exportCsv()
    {
        $db = db_connect();
        $filterType = $this->request->getGet('filter_type') ?: 'month';
        $filterValue = $this->request->getGet('filter_value') ?: date('Y-m');
        $shiftId = (int)$this->request->getGet('shift_id');

        $completedItems = $this->getData($db, $filterType, $filterValue, $shiftId);

        $filename = 'QC_Completed_Items_' . $filterType . '_' . $filterValue . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        $f = fopen('php://output', 'w');
        fputcsv($f, ['No', 'Part No', 'Part Name', 'Total Qty Inspection (Pcs)', 'Total OK (Pcs)', 'Total NG (Pcs)', 'NG Percentage (%)']);

        $no = 1;
        $totalInspected = 0;
        $totalOk = 0;
        $totalNg = 0;

        foreach ($completedItems as $row) {
            $inspected = (int)$row['qty_in'];
            $ok = (int)$row['qty_ok'];
            $ng = (int)$row['qty_ng'];
            
            $totalInspected += $inspected;
            $totalOk += $ok;
            $totalNg += $ng;

            $pct = $inspected > 0 ? round(($ng / $inspected) * 100, 2) : 0;

            fputcsv($f, [
                $no++,
                "'" . $row['part_no'],
                $row['part_name'],
                $inspected,
                $ok,
                $ng,
                $pct . '%'
            ]);
        }
        
        $totalPct = $totalInspected > 0 ? round(($totalNg / $totalInspected) * 100, 2) : 0;
        fputcsv($f, ['', '', 'TOTAL', $totalInspected, $totalOk, $totalNg, $totalPct . '%']);

        fclose($f);
        exit;
    }
}
