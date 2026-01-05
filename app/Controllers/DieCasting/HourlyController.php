<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class HourlyController extends BaseController
{
    public function index()
    {
        $db   = db_connect();

        // tanggal boleh dari filter, default hari ini
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $now  = date('H:i:s');

        /* =====================================================
         * DETECT ACTIVE SHIFT & TIME SLOT (SUPPORT SHIFT MALAM)
         * ===================================================== */
$activeSlot = $db->table('shift_time_slots sts')
    ->select('
        sts.shift_id,
        sts.time_slot_id,
        ts.time_start,
        ts.time_end,
        s.shift_name
    ')
    ->join('time_slots ts', 'ts.id = sts.time_slot_id')
    ->join('shifts s', 's.id = sts.shift_id')
    ->where('s.is_active', 1)
    ->groupStart()

        // SHIFT NORMAL
        ->groupStart()
            ->where('ts.time_end > ts.time_start')
            ->where('CURTIME() BETWEEN ts.time_start AND ts.time_end')
        ->groupEnd()

        // SHIFT MALAM (LEWAT TENGAH MALAM)
        ->orGroupStart()
            ->where('ts.time_end < ts.time_start')
            ->groupStart()
                ->where('CURTIME() >= ts.time_start')
                ->orWhere('CURTIME() <= ts.time_end')
            ->groupEnd()
        ->groupEnd()

    ->groupEnd()
    ->orderBy('sts.shift_id')
    ->get()
    ->getRowArray();


        // jika tidak ada slot aktif → lock
        if (!$activeSlot) {
            return view('die_casting/hourly/locked', [
                'message' => 'Tidak ada time slot aktif saat ini'
            ]);
        }

        $shiftId = $activeSlot['shift_id'];
        $slotId  = $activeSlot['time_slot_id'];

        /* =====================================================
         * DAILY SCHEDULE (SUMBER PART)
         * ===================================================== */
        $rows = $db->table('daily_schedule_items dsi')
            ->select('
                dsi.machine_id,
                m.machine_code,
                m.line_position,
                p.id product_id,
                p.part_no,
                p.part_name,
                ps.cycle_time_sec,
                dsi.target_per_hour,

                IFNULL(h.qty_fg,0) qty_fg,
                IFNULL(h.qty_ng,0) qty_ng,
                h.ng_category,
                IFNULL(h.downtime_minute,0) downtime
            ')
            ->join('daily_schedules ds','ds.id=dsi.daily_schedule_id')
            ->join('machines m','m.id=dsi.machine_id')
            ->join('products p','p.id=dsi.product_id')
            ->join(
                'production_standards ps',
                'ps.machine_id=dsi.machine_id AND ps.product_id=dsi.product_id'
            )
            ->join(
                'die_casting_hourly h',
                'h.production_date="'.$date.'"
                 AND h.shift_id='.$shiftId.'
                 AND h.time_slot_id='.$slotId.'
                 AND h.machine_id=dsi.machine_id',
                'left'
            )
            ->where('ds.schedule_date', $date)
            ->where('ds.shift_id', $shiftId)
            ->where('ds.section', 'Die Casting')
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        /* =====================================================
         * LIST PRODUCT PER MACHINE (DARI PRODUCTION STANDARD)
         * ===================================================== */
        $productsByMachine = [];
        foreach ($rows as $r) {
            if (!isset($productsByMachine[$r['machine_id']])) {
                $productsByMachine[$r['machine_id']] = $db
                    ->table('production_standards ps')
                    ->select('p.id, p.part_no, p.part_name')
                    ->join('products p','p.id=ps.product_id')
                    ->where('ps.machine_id', $r['machine_id'])
                    ->orderBy('p.part_no')
                    ->get()
                    ->getResultArray();
            }
        }

        return view('die_casting/hourly/index', [
            'date'     => $date,
            'shift'    => $activeSlot,
            'rows'     => $rows,
            'products' => $productsByMachine,
            'canEdit'  => true
        ]);
    }

    /* =====================================================
     * SIMPAN DATA HOURLY
     * ===================================================== */
    public function store()
    {
        $db = db_connect();

        foreach ($this->request->getPost('items') as $row) {
            $db->table('die_casting_hourly')->replace([
                'production_date' => $this->request->getPost('date'),
                'shift_id'        => $this->request->getPost('shift_id'),
                'time_slot_id'    => $this->request->getPost('time_slot_id'),
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'qty_fg'          => $row['qty_fg'],
                'qty_ng'          => $row['qty_ng'],
                'ng_category'     => $row['ng_category'],
                'downtime_minute' => $row['downtime']
            ]);
        }

        return redirect()->back()->with('success','Hourly production saved');
    }
}
