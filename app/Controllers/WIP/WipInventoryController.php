<?php

namespace App\Controllers\WIP;

use App\Controllers\BaseController;

class WipInventoryController extends BaseController
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

    /**
     * Ambil ID process dari process_name (pakai beberapa kandidat nama karena sering beda penulisan)
     */
    private function findProcessIdByCandidates($db, array $candidates): ?int
    {
        foreach ($candidates as $name) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_name', $name)
                ->get()
                ->getRowArray();

            if ($row && !empty($row['id'])) return (int)$row['id'];
        }

        // fallback: LIKE
        foreach ($candidates as $name) {
            $row = $db->table('production_processes')
                ->select('id')
                ->like('process_name', $name)
                ->get()
                ->getRowArray();

            if ($row && !empty($row['id'])) return (int)$row['id'];
        }

        return null;
    }

    /**
     * Urutan proses fixed seperti gambar excel
     */
    private function getFixedProcessOrder($db): array
    {
        $defs = [
            'DIE CASTING'     => ['Die Casting', 'DIE CASTING', 'DC'],
            'MACHINING'       => ['Machining', 'MACHINING', 'MC'],
            'RAW MATERIAL'    => ['Raw Material', 'RAW MATERIAL', 'R/M', 'RM'],
            'BURRYTORY'       => ['Burrytory', 'BURRYTORY', 'Burritory', 'BURRITORY', 'BURRY TORY'],
            'SAND BLASTING'   => ['Sand Blasting', 'SAND BLASTING', 'Sandblast', 'SHOTBLAST', 'Shot Blast'],
            'LEAK TEST'       => ['Leak Test', 'LEAK TEST', 'LEAKTEST'],
            'JIG PLUG'        => ['Jig Plug', 'JIG PLUG', 'JIG'],
            'ASSY BUSHING'    => ['Assy Bushing', 'ASSY BUSHING'],
            'ASSY SHAFT'      => ['Assy Shaft', 'ASSY SHAFT'],
            'PAINTING'        => ['Painting', 'PAINTING'],
            'FINAL INSPECTION'        => ['Final Inspection', 'FINAL INSPECTION'],
            'FINISHED GOOD'        => ['Finished Good', 'FINISHED GOOD'],
        ];

        $out = [];
        foreach ($defs as $label => $candidates) {
            $pid = $this->findProcessIdByCandidates($db, $candidates);
            $out[] = [
                'label' => $label,
                'id'    => $pid, // bisa null kalau belum ada di master
            ];
        }

        return $out;
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $wipDateCol = $this->detectWipDateColumn($db);
        $processes  = $this->getFixedProcessOrder($db);

        // process_id valid (tidak null)
        $validProcessIds = array_values(array_filter(array_map(
            fn($p) => (int)($p['id'] ?? 0),
            $processes
        )));
        $validProcessIds = array_values(array_filter($validProcessIds, fn($v) => $v > 0));

        // kolom opsional
        $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
        $hasStock  = $db->fieldExists('stock', 'production_wip');

        /**
         * 1) Ambil semua product_id yang muncul di WIP tanggal itu
         * FIX: jangan pakai select('DISTINCT ...'), harus distinct()
         */
        $productRows = $db->table('production_wip')
            ->distinct()
            ->select('product_id')
            ->where($wipDateCol, $date)
            ->get()
            ->getResultArray();

        $productIds = array_values(array_filter(array_map(
            fn($r) => (int)($r['product_id'] ?? 0),
            $productRows
        )));

        if (!$productIds) {
            return view('wip/inventory/index', [
                'date'      => $date,
                'processes' => $processes,
                'rows'      => [],
            ]);
        }

        /**
         * 2) Ambil master product info
         */
        $products = $db->table('products')
            ->select('id, part_no, part_name')
            ->whereIn('id', $productIds)
            ->orderBy('part_no', 'ASC')
            ->get()
            ->getResultArray();

        $productMap = [];
        foreach ($products as $p) {
            $pid = (int)$p['id'];
            $productMap[$pid] = $p;
        }

        /**
         * 3) Ambil flow map (agar yang tidak lewat proses => 0)
         * Jika tabel flow tidak ada, kita anggap "enabled" semua (supaya tidak mematikan semua kolom).
         */
        $flowMap = []; // [product_id][process_id] = true
        $hasFlowTable = $db->tableExists('product_process_flows');

        if ($hasFlowTable) {
            $flows = $db->table('product_process_flows')
                ->select('product_id, process_id')
                ->whereIn('product_id', $productIds)
                ->where('is_active', 1)
                ->get()
                ->getResultArray();

            foreach ($flows as $f) {
                $flowMap[(int)$f['product_id']][(int)$f['process_id']] = true;
            }
        }

        /**
         * 4) Aggregate IN per (product_id, to_process_id)
         * IN: qty_in (kalau ada) else qty
         * STOCK: stock (kalau ada) else 0 (akan fallback in-out di bawah)
         */
        $inSelectQty = $hasQtyIn
            ? 'SUM(COALESCE(qty_in, 0)) AS qty_in_sum'
            : 'SUM(COALESCE(qty, 0)) AS qty_in_sum';

        $stockSelect = $hasStock
            ? 'SUM(COALESCE(stock, 0)) AS stock_sum'
            : '0 AS stock_sum';

        $inBuilder = $db->table('production_wip')
            ->select("product_id, to_process_id AS process_id, {$inSelectQty}, {$stockSelect}")
            ->where($wipDateCol, $date);

        if (!empty($validProcessIds)) {
            $inBuilder->whereIn('to_process_id', $validProcessIds);
        }

        $inAgg = $inBuilder
            ->groupBy('product_id, to_process_id')
            ->get()
            ->getResultArray();

        $inMap = [];    // [product_id][process_id] = in
        $stockMap = []; // [product_id][process_id] = stock(sum)
        foreach ($inAgg as $r) {
            $pid = (int)$r['product_id'];
            $prc = (int)$r['process_id'];
            $inMap[$pid][$prc] = (int)($r['qty_in_sum'] ?? 0);
            $stockMap[$pid][$prc] = (int)($r['stock_sum'] ?? 0);
        }

        /**
         * 5) Aggregate OUT per (product_id, from_process_id)
         * OUT: qty_out (kalau ada) else qty
         */
        $outSelectQty = $hasQtyOut
            ? 'SUM(COALESCE(qty_out, 0)) AS qty_out_sum'
            : 'SUM(COALESCE(qty, 0)) AS qty_out_sum';

        $outBuilder = $db->table('production_wip')
            ->select("product_id, from_process_id AS process_id, {$outSelectQty}")
            ->where($wipDateCol, $date);

        if (!empty($validProcessIds)) {
            $outBuilder->whereIn('from_process_id', $validProcessIds);
        }

        $outAgg = $outBuilder
            ->groupBy('product_id, from_process_id')
            ->get()
            ->getResultArray();

        $outMap = []; // [product_id][process_id] = out
        foreach ($outAgg as $r) {
            $pid = (int)$r['product_id'];
            $prc = (int)$r['process_id'];
            $outMap[$pid][$prc] = (int)($r['qty_out_sum'] ?? 0);
        }

        /**
         * 6) Build rows untuk view (per product, per process => in/out/stock)
         * Jika produk tidak punya flow di process itu => 0 semua (kalau flow table ada).
         */
        $rows = [];
        $no = 1;

        foreach ($productIds as $productId) {
            if (!isset($productMap[$productId])) continue;

            $row = [
                'no'         => $no++,
                'product_id' => $productId,
                'part_no'    => (string)($productMap[$productId]['part_no'] ?? '-'),
                'part_name'  => (string)($productMap[$productId]['part_name'] ?? '-'),
                'cells'      => [], // [label] => ['in'=>, 'out'=>, 'stock'=>, 'enabled'=>]
            ];

            foreach ($processes as $proc) {
                $label = $proc['label'];
                $pid   = (int)($proc['id'] ?? 0);

                // process belum ada di master
                if ($pid <= 0) {
                    $row['cells'][$label] = ['in'=>0, 'out'=>0, 'stock'=>0, 'enabled'=>false];
                    continue;
                }

                // enabled ditentukan oleh flow jika flow table ada, kalau tidak ada => enabled true
                $enabled = $hasFlowTable ? !empty($flowMap[$productId][$pid]) : true;

                $in  = $enabled ? (int)($inMap[$productId][$pid] ?? 0) : 0;
                $out = $enabled ? (int)($outMap[$productId][$pid] ?? 0) : 0;

                // stock: jika ada kolom stock, pakai sum(stock). Jika tidak, fallback in-out
                if ($enabled) {
                    $stock = $hasStock
                        ? (int)($stockMap[$productId][$pid] ?? 0)
                        : max(0, $in - $out);
                } else {
                    $stock = 0;
                }

                $row['cells'][$label] = [
                    'in'      => $in,
                    'out'     => $out,
                    'stock'   => $stock,
                    'enabled' => $enabled,
                ];
            }

            $rows[] = $row;
        }

        return view('wip/inventory/index', [
            'date'      => $date,
            'processes' => $processes,
            'rows'      => $rows,
        ]);
    }
}
