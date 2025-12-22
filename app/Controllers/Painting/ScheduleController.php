<?php

namespace App\Controllers\Painting;

use App\Controllers\BaseController;

class ScheduleController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('painting/schedule/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray(),
            'machines' => $db->table('machines')
                            ->where('production_line', 'Painting')
                            ->get()->getResultArray()
        ]);
    }

    public function store()
    {
        db_connect()->table('production_plans')->insert([
            'plan_date'    => $this->request->getPost('date'),
            'shift_id'     => $this->request->getPost('shift_id'),
            'product_id'   => $this->request->getPost('product_id'),
            'machine_id'   => $this->request->getPost('machine_id'),
            'target_shift' => $this->request->getPost('target_shift'),
            'target_hour'  => $this->request->getPost('target_hour')
        ]);

        return redirect()->back()->with('success', 'Jadwal painting tersimpan');
    }
}
