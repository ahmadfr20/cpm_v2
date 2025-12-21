<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = date('Y-m-d');

        // 🔹 Ambil shift (WAJIB untuk dropdown)
        $shifts = $db->table('shifts')->get()->getResultArray();

        // 🔹 Ambil daily schedule DIE CASTING
        $items = $db->table('daily_schedule_items dsi')
            ->select('
                dsi.product_id,
                dsi.machine_id,
                p.part_no,
                p.part_name,
                m.machine_name
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('products p', 'p.id = dsi.product_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Die Casting')
            ->get()
            ->getResultArray();

        return view('die_casting/production/index', [
            'date'   => $date,
            'shifts' => $shifts,   // ✅ FIX UTAMA
            'items'  => $items
        ]);
    }
}
