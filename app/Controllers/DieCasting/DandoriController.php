<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;
use App\Models\DieCastingDandoriModel;

class DandoriController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('die_casting/dandori/index', [
            'machines' => $db->table('machines')->where('production_line','Die Casting')->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray(),
            'shifts'   => $db->table('shifts')->get()->getResultArray()
        ]);
    }

    public function store()
    {
        (new DieCastingDandoriModel())->insert([
            'dandori_date' => date('Y-m-d'),
            'shift_id' => $this->request->getPost('shift_id'),
            'machine_id' => $this->request->getPost('machine_id'),
            'product_id' => $this->request->getPost('product_id'),
            'activity' => $this->request->getPost('activity'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return redirect()->back()->with('success','Dandori saved');
    }
}
