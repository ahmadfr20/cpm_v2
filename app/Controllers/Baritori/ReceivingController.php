<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;

class ReceivingController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $builder = $db->table('material_transactions mt')
            ->select('
                mt.transaction_date,
                mt.shift_id,
                s.shift_name,
                mt.product_id,
                p.part_no,
                p.part_name,
                sup.id AS supplier_id,
                sup.supplier_name,
                SUM(CASE WHEN mt.transaction_type = "VENDOR_OUT" THEN mt.qty ELSE 0 END) qty_out,
                SUM(CASE WHEN mt.transaction_type = "VENDOR_IN" THEN mt.qty ELSE 0 END) qty_in
            ')
            ->join('products p', 'p.id = mt.product_id')
            ->join('shifts s', 's.id = mt.shift_id')
            ->join('suppliers sup', 'sup.id = mt.process_to', 'left')
            ->where('mt.transaction_type IN ("VENDOR_OUT","VENDOR_IN")')
            ->groupBy('mt.transaction_date, mt.shift_id, mt.product_id, sup.id')
            ->having('qty_out > qty_in')
            ->orderBy('mt.transaction_date', 'ASC');

        $perPage = 10;
        $page    = (int) ($this->request->getGet('page') ?? 1);

        $deliveries = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        $total = $builder->countAllResults(false);

        $pager = service('pager');
        $pager->makeLinks($page, $perPage, $total);

        return view('baritori/receiving/index', [
            'deliveries' => $deliveries,
            'pager'      => $pager
        ]);
    }

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items) {
            return redirect()->back()->with('error', 'Data kosong');
        }

        $db->transBegin();

        try {

            foreach ($items as $row) {

                if (empty($row['qty']) || $row['qty'] <= 0) continue;

                $db->table('material_transactions')->insert([
                    'transaction_date' => date('Y-m-d'),
                    'shift_id'         => $row['shift_id'],
                    'product_id'       => $row['product_id'],
                    'qty'              => $row['qty'],
                    'transaction_type' => 'VENDOR_IN',
                    'created_at'       => date('Y-m-d H:i:s')
                ]);
            }

            $db->transCommit();
            return redirect()->back()
                ->with('success', 'Receiving Baritori berhasil disimpan');

        } catch (\Throwable $e) {

            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
