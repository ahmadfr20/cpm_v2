<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;
use App\Models\ProductModel;

class DeliveryController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->orderBy('shift_code')
            ->get()->getResultArray();

        $vendors = $db->table('suppliers')
            ->select('id, supplier_code, supplier_name')
            ->where('is_active', 1)
            ->orderBy('supplier_name')
            ->get()->getResultArray();

        $productModel = new ProductModel();

        $products = $productModel
            ->filterProducts()
            ->paginate(10);

        return view('baritori/delivery/index', [
            'shifts'   => $shifts,
            'vendors'  => $vendors,
            'products' => $products,
            'pager'    => $productModel->pager
        ]);
    }

    public function store()
    {
        $db = db_connect();

        $shiftId  = $this->request->getPost('shift_id');
        $vendorId = $this->request->getPost('vendor_id');
        $po       = $this->request->getPost('po_number');
        $do       = $this->request->getPost('do_number');
        $items    = $this->request->getPost('items');

        if (!$shiftId || !$vendorId || !$items) {
            return redirect()->back()->with('error', 'Data tidak lengkap');
        }

        $db->transBegin();

        try {

            foreach ($items as $row) {
                if (empty($row['qty']) || $row['qty'] <= 0) continue;

                $db->table('material_transactions')->insert([
                    'transaction_date' => date('Y-m-d'),
                    'shift_id'         => $shiftId,
                    'product_id'       => $row['product_id'],
                    'qty'              => $row['qty'],
                    'transaction_type' => 'VENDOR_OUT',
                    'created_at'       => date('Y-m-d H:i:s')
                ]);
            }

            $db->transCommit();
            return redirect()->back()
                ->with('success', 'Delivery Baritori berhasil disimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
