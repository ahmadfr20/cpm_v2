<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DandoriController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('machining/dandori/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'machines' => $db->table('machines')
                            ->where('production_line', 'Machining')
                            ->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray()
        ]);
    }

    public function store()
    {
        $db = db_connect();

        $db->table('production_plans')->insert([
            'plan_date'    => $this->request->getPost('date'),
            'shift_id'     => $this->request->getPost('shift_id'),
            'machine_id'   => $this->request->getPost('machine_id'),
            'product_id'   => $this->request->getPost('product_id'),
            'target_shift' => $this->request->getPost('target_shift'),
            'target_hour'  => $this->request->getPost('target_hour')
        ]);

        return redirect()->back()->with('success', 'Dandori machining tersimpan');
    }
}
