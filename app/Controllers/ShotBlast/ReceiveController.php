<?php

namespace App\Controllers\ShotBlast;

use App\Controllers\BaseController;

class ReceiveController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('shotblast/receive/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray()
        ]);
    }

    public function store()
    {
        $db = db_connect();

        $db->table('production_outputs')->insert([
            'production_date' => $this->request->getPost('date'),
            'shift_id'        => $this->request->getPost('shift_id'),
            'product_id'      => $this->request->getPost('product_id'),
            'process_id'      => 3, // Shot Blast
            'qty_ok'          => $this->request->getPost('qty_ok'),
            'qty_ng'          => $this->request->getPost('qty_ng')
        ]);

        return redirect()->back()->with('success', 'Penerimaan tercatat');
    }
}
