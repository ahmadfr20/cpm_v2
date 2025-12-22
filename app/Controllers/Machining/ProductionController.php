<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('machining/production/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray(),
            'machines' => $db->table('machines')
                            ->where('production_line', 'Machining')
                            ->get()->getResultArray()
        ]);
    }

    public function store()
    {
        $db = db_connect();

        $db->table('production_outputs')->insert([
            'production_date' => $this->request->getPost('date'),
            'shift_id'        => $this->request->getPost('shift_id'),
            'product_id'      => $this->request->getPost('product_id'),
            'machine_id'      => $this->request->getPost('machine_id'),
            'process_id'      => 4, // Machining
            'qty_ok'          => $this->request->getPost('qty_ok'),
            'qty_ng'          => $this->request->getPost('qty_ng')
        ]);

        return redirect()->back()->with('success', 'Output machining tersimpan');
    }
}
