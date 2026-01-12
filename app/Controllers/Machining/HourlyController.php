<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;
use App\Models\MachiningHourlyModel;

class HourlyController extends BaseController
{
    protected $db;
    protected $hourlyModel;

    public function __construct()
    {
        $this->db = db_connect();
        $this->hourlyModel = new MachiningHourlyModel();
    }

    public function index()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        /* =====================================================
         * DETECT ACTIVE SHIFT & TIME SLOT (FIXED – SUPPORT MALAM)
         * ===================================================== */
        $now = strtotime(date('Y-m-d H:i:s'));
        $activeSlot = null;

        $slots = $this->db->table('shift_time_slots sts')
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
            ->orderBy('sts.shift_id')
            ->get()
            ->getResultArray();

        foreach ($slots as $slot) {

            $start = strtotime($date . ' ' . $slot['time_start']);
            $end   = strtotime($date . ' ' . $slot['time_end']);

            // SHIFT MALAM (lintas hari)
            if ($end <= $start) {
                $end += 86400; // +1 hari
            }

            // jam sekarang lewat tengah malam
            if ($now < $start) {
                $start -= 86400;
                $end   -= 86400;
            }

            if ($now >= $start && $now <= $end) {
                $activeSlot = $slot;
                break;
            }
        }

        if (!$activeSlot) {
            return view('machining/hourly/locked', [
                'message' => 'Tidak ada time slot aktif saat ini'
            ]);
        }

        $shiftId    = $activeSlot['shift_id'];
        $timeSlotId = $activeSlot['time_slot_id'];

        /* =====================================================
         * AMBIL DATA SHIFT & TIME SLOT
         * ===================================================== */
        $shift = $this->db->table('shifts')
            ->where('id', $shiftId)
            ->get()
            ->getRowArray();

        $timeSlot = $this->db->table('time_slots')
            ->where('id', $timeSlotId)
            ->get()
            ->getRowArray();

        /* =====================================================
         * AMBIL DATA DARI DAILY SCHEDULE (MACHINING)
         * ===================================================== */
        $items = $this->db->table('daily_schedule_items dsi')
            ->select('
                p.part_no,
                p.part_name,
                m.machine_code,
                dsi.machine_id,
                dsi.product_id,
                dsi.target_per_hour,
                ps.cycle_time_sec
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->join('products p', 'p.id = dsi.product_id')
            ->join(
                'production_standards ps',
                'ps.machine_id = dsi.machine_id AND ps.product_id = dsi.product_id',
                'left'
            )
            ->where('ds.schedule_date', $date)
            ->where('ds.shift_id', $shiftId)
            ->where('ds.section', 'Machining')
            ->orderBy('m.machine_code')
            ->get()
            ->getResultArray();

        return view('machining/hourly/index', [
            'date'       => $date,
            'shiftId'    => $shiftId,
            'shiftName'  => $shift['shift_name'],
            'timeSlotId' => $timeSlotId,
            'timeLabel'  => $timeSlot['time_start'] . ' - ' . $timeSlot['time_end'],
            'items'      => $items
        ]);
    }

    /* =====================================================
     * SIMPAN DATA HOURLY
     * ===================================================== */
    public function store()
    {
        $items = $this->request->getPost('items');

        if (!is_array($items)) {
            return redirect()->back()->with('error', 'Tidak ada data');
        }

        foreach ($items as $row) {

            if (empty($row['machine_id']) || empty($row['product_id'])) {
                continue;
            }

            $this->hourlyModel->replace([
                'production_date' => $this->request->getPost('date'),
                'shift_id'        => $this->request->getPost('shift_id'),
                'time_slot_id'    => $this->request->getPost('time_slot_id'),
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'qty_fg'          => $row['qty_fg'] ?? 0,
                'qty_ng'          => $row['qty_ng'] ?? 0,
                'ng_category'     => $row['ng_category'] ?? null,
                'downtime'        => $row['downtime'] ?? 0,
                'remark'          => $row['remark'] ?? null,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Hourly Production Machining berhasil disimpan');
    }
}
