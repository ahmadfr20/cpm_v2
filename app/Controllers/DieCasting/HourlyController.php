<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class HourlyController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $date    = $this->request->getGet('date') ?? date('Y-m-d');
        $shiftId = $this->request->getGet('shift_id');
        $slotId  = $this->request->getGet('time_slot_id');

        // === SHIFT ===
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->get()->getResultArray();

        if (!$shiftId) {
            return view('die_casting/hourly/select_shift', [
                'date'   => $date,
                'shifts' => $shifts
            ]);
        }

        // === TIME SLOT BY SHIFT ===
        $timeSlots = $db->table('shift_time_slots sts')
            ->select('ts.*')
            ->join('time_slots ts','ts.id=sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('ts.time_start')
            ->get()->getResultArray();

        if (!$slotId) {
            return view('die_casting/hourly/select_time', [
                'date'      => $date,
                'shiftId'   => $shiftId,
                'timeSlots' => $timeSlots
            ]);
        }

        // === DAILY SCHEDULED PART ===
        $rows = $db->table('daily_schedule_items dsi')
            ->select('
                dsi.machine_id,
                dsi.product_id,
                p.part_no,
                p.part_name,
                dsi.cycle_time,
                dsi.target_per_hour,
                IFNULL(h.qty_fg,0) qty_fg,
                IFNULL(h.qty_ng,0) qty_ng,
                h.ng_category,
                h.downtime_minute
            ')
            ->join('daily_schedules ds','ds.id=dsi.daily_schedule_id')
            ->join('products p','p.id=dsi.product_id')
            ->join(
                'die_casting_hourly h',
                'h.production_date="'.$date.'"
                 AND h.shift_id='.$shiftId.'
                 AND h.time_slot_id='.$slotId.'
                 AND h.machine_id=dsi.machine_id
                 AND h.product_id=dsi.product_id',
                'left'
            )
            ->where('ds.schedule_date', $date)
            ->where('ds.shift_id', $shiftId)
            ->where('ds.section', 'Die Casting')
            ->get()->getResultArray();

        return view('die_casting/hourly/index', [
            'date'      => $date,
            'shiftId'   => $shiftId,
            'slotId'    => $slotId,
            'rows'      => $rows,
            'canEdit'   => $this->canEdit()
        ]);
    }

    public function store()
    {
        $db = db_connect();

        foreach ($this->request->getPost('items') as $row) {
            $db->table('die_casting_hourly')->replace([
                'production_date'  => $this->request->getPost('date'),
                'shift_id'         => $this->request->getPost('shift_id'),
                'time_slot_id'     => $this->request->getPost('time_slot_id'),
                'machine_id'       => $row['machine_id'],
                'product_id'       => $row['product_id'],
                'qty_fg'           => $row['qty_fg'],
                'qty_ng'           => $row['qty_ng'],
                'ng_category'      => $row['ng_category'],
                'downtime_minute'  => $row['downtime']
            ]);
        }

        return redirect()->back()->with('success','Hourly Production tersimpan');
    }

    private function canEdit()
    {
        return true; // bisa dikunci pakai jam real
    }
}
