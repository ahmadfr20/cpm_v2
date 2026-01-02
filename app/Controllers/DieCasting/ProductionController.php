<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class ProductionController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $shiftId  = $this->request->getGet('shift_id');

        // ===== MASTER SHIFT =====
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get()->getResultArray();

        // Jika shift belum dipilih → tampilkan form pilih shift
        if (!$shiftId) {
            return view('die_casting/production/select_shift', [
                'date'   => $date,
                'shifts' => $shifts,
            ]);
        }

        // ===== VALIDASI SHIFT =====
        $shift = $db->table('shifts')->where('id', $shiftId)->get()->getRowArray();
        if (!$shift) {
            return redirect()->back()->with('error', 'Shift tidak valid');
        }

        // ===== DATA SCHEDULED PART =====
        $rows = $db->table('daily_schedule_items dsi')
            ->select('
                ds.shift_id,
                m.machine_code,
                m.id machine_id,
                p.id product_id,
                p.part_no,
                p.part_name,
                dsi.target_per_shift,

                IFNULL(SUM(h.qty_fg),0) fg,
                IFNULL(SUM(h.qty_ng),0) ng,
                IFNULL(SUM(h.downtime_minute),0) downtime
            ')
            ->join('daily_schedules ds','ds.id=dsi.daily_schedule_id')
            ->join('machines m','m.id=dsi.machine_id')
            ->join('products p','p.id=dsi.product_id')
            ->join(
                'die_casting_hourly h',
                'h.machine_id=dsi.machine_id
                AND h.product_id=dsi.product_id
                AND h.shift_id=ds.shift_id
                AND h.production_date="'.$date.'"',
                'left'
            )
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Die Casting')
            ->where('ds.shift_id', $shiftId)
            ->groupBy('dsi.id')
            ->orderBy('m.machine_code')
            ->get()->getResultArray();

        // ===== GROUP BY MACHINE =====
        $data = [];
        foreach ($rows as $r) {
            $data[$r['machine_code']][] = $r;
        }

        return view('die_casting/production/index', [
            'date'    => $date,
            'shift'   => $shift,
            'shifts'  => $shifts,
            'data'    => $data,
            'canEdit' => $this->canEdit($shift),
        ]);
    }


    // ================= AKHIR SHIFT =================
    private function canEdit()
    {
        $now = date('H:i:s');

        // contoh: edit hanya boleh jam 16:30 – 17:30
        return ($now >= '16:30:00' && $now <= '17:30:00');
    }
}
