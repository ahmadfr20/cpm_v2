<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;
use App\Models\DieCastingProductionModel;

class ProductionController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('die_casting/production/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'machines' => $db->table('machines')
                            ->select('id, machine_name')
                            ->orderBy('machine_name')
                            ->get()
                            ->getResultArray(),
            'products' => $db->table('products')
                            ->select('id, part_no, part_name')
                            ->orderBy('part_name')
                            ->get()
                            ->getResultArray()
        ]);
    }

    public function store()
    {
        $model = new DieCastingProductionModel();
        $items = $this->request->getPost('items');

        foreach ($items as $row) {

            if (empty($row['machine_id']) || empty($row['product_id'])) {
                continue;
            }

            $model->insert([
                'production_date' => date('Y-m-d'),
                'shift_id'        => $this->request->getPost('shift_id'),
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'qty_p'           => $row['qty_p'] ?? 0,
                'qty_a'           => $row['qty_a'] ?? 0,
                'qty_ng'          => $row['qty_ng'] ?? 0,
                'weight_kg'       => $row['weight_kg'] ?? 0,
            ]);
        }

        return redirect()->back()->with('success', 'Data produksi berhasil disimpan');
    }
}
