<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;

class SendInternalController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('baritori/send_internal/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray()
        ]);
    }

    public function store()
    {
        db_connect()->table('material_transactions')->insert([
            'transaction_date' => $this->request->getPost('date'),
            'shift_id'         => $this->request->getPost('shift_id'),
            'product_id'       => $this->request->getPost('product_id'),
            'qty'              => $this->request->getPost('qty_ok'),
            'transaction_type' => 'TRANSFER'
        ]);

        return redirect()->back()->with('success', 'Pengiriman Baritori ke Internal tercatat');
    }
}
