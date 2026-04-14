<?php

namespace App\Controllers\FinishedGood;

use App\Controllers\BaseController;

class FgInventoryController extends BaseController
{
    private function getFgProcessId($db): int
    {
        $names = ['FINISHED GOOD', 'Finished Good', 'FG'];
        foreach ($names as $name) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_name', $name)
                ->get()->getRowArray();
            if ($row) return (int)$row['id'];
        }
        return 0;
    }

    private function getInventoryData($db)
    {
        $fgProcessId = $this->getFgProcessId($db);
        if ($fgProcessId <= 0) return [];

        // Sum up stock where to_process_id = Finished Good
        $query = $db->table('production_wip pw')
            ->select('pw.product_id, p.part_no, p.part_name, SUM(pw.stock) as qty_available, SUM(pw.qty_out) as qty_delivered, SUM(pw.qty_in) as qty_total_in')
            ->join('products p', 'p.id = pw.product_id', 'left')
            ->where('pw.to_process_id', $fgProcessId)
            ->where('pw.stock >', 0)
            ->groupBy('pw.product_id');

        return $query->get()->getResultArray();
    }

    public function index()
    {
        $db = db_connect();
        $inventory = $this->getInventoryData($db);

        $totalQty = 0;
        foreach($inventory as $item) {
            $totalQty += (int)$item['qty_available'];
        }

        return view('finished_good/inventory/index', [
            'inventory' => $inventory,
            'totalVarian' => count($inventory),
            'totalQty' => $totalQty
        ]);
    }

    public function exportCsv()
    {
        $db = db_connect();
        $inventory = $this->getInventoryData($db);

        $filename = 'FG_Inventory_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        // Output to php://output
        $f = fopen('php://output', 'w');

        // headers
        fputcsv($f, ['No', 'Part No', 'Part Name', 'Total Masuk (Pcs)', 'Total Keluar/Delivery (Pcs)', 'Stok Tersedia (Pcs)']);

        $no = 1;
        $totalStock = 0;
        foreach ($inventory as $row) {
            fputcsv($f, [
                $no++,
                "'" . $row['part_no'], // prepend quote to prevent Excel treating it as number
                $row['part_name'],
                $row['qty_total_in'],
                $row['qty_delivered'],
                $row['qty_available']
            ]);
            $totalStock += (int)$row['qty_available'];
        }
        
        // Footer line
        fputcsv($f, ['', '', 'TOTAL', '', '', $totalStock]);

        fclose($f);
        exit;
    }
}
