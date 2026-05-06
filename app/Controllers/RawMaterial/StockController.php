<?php

namespace App\Controllers\RawMaterial;

use App\Controllers\BaseController;

class StockController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        // Ambil data stock
        $stocks = $db->table('raw_material_stock')
                     ->orderBy('material_type', 'ASC')
                     ->orderBy('unit', 'ASC')
                     ->get()
                     ->getResultArray();

        // 5 log Scrap terakhir
        $recentScrap = $db->table('raw_material_scrap_receives r')
             ->select('r.*, s.shift_name')
             ->join('shifts s', 's.id = r.shift_id', 'left')
             ->orderBy('r.created_at', 'DESC')
             ->limit(5)
             ->get()->getResultArray();

        // 5 log Ingot terakhir
        $recentIngot = $db->table('raw_material_ingot_receives r')
             ->select('r.*, s.shift_name')
             ->join('shifts s', 's.id = r.shift_id', 'left')
             ->orderBy('r.created_at', 'DESC')
             ->limit(5)
             ->get()->getResultArray();

        return view('raw_material/stock/index', [
            'stocks'      => $stocks,
            'recentScrap' => $recentScrap,
            'recentIngot' => $recentIngot
        ]);
    }
}
