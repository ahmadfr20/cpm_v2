<?php

namespace App\Controllers\Painting;

use App\Controllers\BaseController;

class SendController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('painting/send/index', [
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

        return redirect()->back()->with('success', 'Pengiriman painting tercatat');
    }
}
