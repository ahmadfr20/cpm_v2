<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionAchievementController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $now  = date('H:i:s');

        /**
         * 🔥 FIX UTAMA
         * HANYA AMBIL SHIFT DIE CASTING
         */
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC') // ⬅️ FILTER DC SAJA
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        foreach ($shifts as &$shift) {

            /**
             * CEK AKHIR SHIFT
             */
            $lastSlot = $db->table('shift_time_slots sts')
                ->select('ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_end', 'DESC')
                ->get()->getRowArray();

            $shift['isEditable'] = false;
            if ($lastSlot && isset($lastSlot['time_end'])) {
                $shift['isEditable'] = ($now >= $lastSlot['time_end']);
            }

            /**
             * PART & TARGET DARI DAILY SCHEDULE DC
             */
            $shift['items'] = $db->table('die_casting_production dcp')
                ->select('
                    dcp.machine_id,
                    dcp.product_id,
                    dcp.qty_p AS target,
                    p.part_no,
                    COALESCE(dcp.part_label, p.part_name) AS part_name,
                    IFNULL(SUM(dh.qty_fg),0) AS total_fg,
                    IFNULL(SUM(dh.qty_ng),0) AS total_ng,
                    MAX(dh.ng_category) AS ng_category,
                    IFNULL(SUM(dh.downtime_minute),0) AS downtime
                ')
                ->join('products p', 'p.id = dcp.product_id')
                ->join(
                    'die_casting_hourly dh',
                    'dh.production_date = dcp.production_date
                     AND dh.shift_id = dcp.shift_id
                     AND dh.machine_id = dcp.machine_id
                     AND dh.product_id = dcp.product_id',
                    'left'
                )
                ->where('dcp.production_date', $date)
                ->where('dcp.shift_id', $shift['id'])
                ->groupBy('dcp.machine_id, dcp.product_id, dcp.qty_p')
                ->orderBy('p.part_no')
                ->get()->getResultArray();
        }

        return view('die_casting/daily_production_achievement/index', [
            'date'   => $date,
            'shifts' => $shifts
        ]);
    }

    public function store()
    {
        $db = db_connect();

        foreach ($this->request->getPost('items') as $row) {
            $db->table('die_casting_hourly')->update([
                'qty_fg'          => (int)$row['fg'],
                'qty_ng'          => (int)$row['ng'],
                'ng_category'     => $row['ng_category'] ?? null,
                'downtime_minute' => (int)$row['downtime']
            ], [
                'production_date' => $row['date'],
                'shift_id'        => $row['shift_id'],
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id']
            ]);
        }

        return redirect()->back()
            ->with('success', 'Daily Production Achievement berhasil disimpan');
    }
}
