<?php

namespace App\Controllers\Baritori;

use App\Controllers\BaseController;

class ReceiveExternalController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('baritori/receive_external/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray()
        ]);
    }

    public function store()
    {
        db_connect()->table('production_outputs')->insert([
            'production_date' => $this->request->getPost('date'),
            'shift_id'        => $this->request->getPost('shift_id'),
            'product_id'      => $this->request->getPost('product_id'),
            'process_id'      => 4, // Baritori
            'qty_ok'          => $this->request->getPost('qty_ok'),
            'qty_ng'          => $this->request->getPost('qty_ng')
        ]);

        return redirect()->back()->with('success', 'Penerimaan Baritori tercatat');
    }
}
