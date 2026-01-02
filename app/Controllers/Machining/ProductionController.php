<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // ================= SHIFT =================
        $shifts = $db->table('shifts')
            ->orderBy('id')
            ->get()
            ->getResultArray();

        // ================= AGREGASI HOURLY =================
        $rows = $db->table('machining_hourly mh')
            ->select('
                mh.shift_id,
                s.shift_name,
                m.machine_code,
                p.part_no,
                p.part_name,
                SUM(mh.qty_fg) AS total_fg,
                SUM(mh.qty_ng) AS total_ng,
                SUM(mh.downtime) AS total_downtime
            ')
            ->join('shifts s', 's.id = mh.shift_id')
            ->join('machines m', 'm.id = mh.machine_id')
            ->join('products p', 'p.id = mh.product_id')
            ->where('mh.production_date', $date)
            ->groupBy('mh.shift_id, mh.machine_id, mh.product_id')
            ->orderBy('s.id, m.machine_code')
            ->get()
            ->getResultArray();

        // ================= FORMAT PER SHIFT =================
        $data = [];
        foreach ($rows as $r) {
            $data[$r['shift_id']][] = $r;
        }

        return view('machining/production/index', [
            'date'   => $date,
            'shifts' => $shifts,
            'data'   => $data
        ]);
    }
}
