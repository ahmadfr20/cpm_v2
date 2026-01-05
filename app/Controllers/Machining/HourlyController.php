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
        $date       = $this->request->getGet('date') ?? date('Y-m-d');
        $shiftId    = $this->request->getGet('shift_id');
        $timeSlotId = $this->request->getGet('time_slot_id');

        /* ================= STEP 1 : PILIH SHIFT ================= */
        if (!$shiftId) {
            return view('machining/hourly/select_shift', [
                'date'   => $date,
                'shifts' => $this->db->table('shifts')->get()->getResultArray()
            ]);
        }

        /* ================= STEP 2 : PILIH TIME SLOT ================= */
        if (!$timeSlotId) {
            $timeSlots = $this->db->table('time_slots ts')
                ->join('shift_time_slots sts', 'sts.time_slot_id = ts.id')
                ->where('sts.shift_id', $shiftId)
                ->orderBy('ts.time_start')
                ->get()
                ->getResultArray();

            return view('machining/hourly/select_time', [
                'date'      => $date,
                'shiftId'   => $shiftId,
                'timeSlots' => $timeSlots
            ]);
        }

        /* ================= STEP 3 : INPUT HOURLY ================= */

        $shift = $this->db->table('shifts')->where('id', $shiftId)->get()->getRowArray();
        $timeSlot = $this->db->table('time_slots')->where('id', $timeSlotId)->get()->getRowArray();

        // 🔥 INI KUNCI: AMBIL DARI DAILY SCHEDULE
        $items = $this->db->table('daily_schedule_items dsi')
            ->select('
                p.part_no,
                p.part_name,
                m.machine_code,
                dsi.machine_id,
                dsi.product_id,
                dsi.cycle_time,
                dsi.target_per_hour
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('products p', 'p.id = dsi.product_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.shift_id', $shiftId)
            ->where('ds.section', 'Machining')
            ->orderBy('m.machine_code')
            ->get()
            ->getResultArray();

        return view('machining/hourly/index', [
            'date'       => $date,
            'shiftId'    => $shiftId,
            'shiftName'  => $shift['shift_name'] ?? 'Shift '.$shiftId,
            'timeSlotId' => $timeSlotId,
            'timeLabel'  => ($timeSlot['time_start'] ?? '-') . ' - ' . ($timeSlot['time_end'] ?? '-'),
            'items'      => $items, // ✅ FIXED
        ]);
    }

    /* ================= STORE ================= */
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

            $this->hourlyModel->insert([
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

        return redirect()->back()->with('success', 'Hourly Production Machining tersimpan');
    }
}
