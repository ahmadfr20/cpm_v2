<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class SubAssyController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('machining/sub_assy/index', [
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
            'process_id'      => 5, // Sub Assy
            'qty_ok'          => $this->request->getPost('qty_ok'),
            'qty_ng'          => $this->request->getPost('qty_ng')
        ]);

        return redirect()->back()->with('success', 'Output sub assy tersimpan');
    }
}
