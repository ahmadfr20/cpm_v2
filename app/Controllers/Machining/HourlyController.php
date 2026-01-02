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
                ->select('ts.*')
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

        // 🔹 shift name (FIX error undefined variable)
        /* ================= STEP 3 : INPUT HOURLY ================= */

// 🔹 shift
$shift = $this->db->table('shifts')
    ->where('id', $shiftId)
    ->get()
    ->getRowArray();

// 🔹 time slot (INI YANG KURANG)
$timeSlot = $this->db->table('time_slots')
    ->where('id', $timeSlotId)
    ->get()
    ->getRowArray();

// 🔹 ambil item dari daily schedule machining
$rows = $this->db->table('daily_schedule_items dsi')
    ->select('
        p.part_no,
        p.part_name,
        dsi.machine_id,
        dsi.product_id,
        dsi.cycle_time,
        dsi.target_per_hour
    ')
    ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
    ->join('products p', 'p.id = dsi.product_id')
    ->where('ds.schedule_date', $date)
    ->where('ds.shift_id', $shiftId)
    ->where('ds.section', 'Machining')
    ->orderBy('p.part_no')
    ->get()
    ->getResultArray();

return view('machining/hourly/index', [
    'date'       => $date,
    'shiftId'    => $shiftId,
    'shiftName'  => $shift['shift_name'] ?? 'Shift '.$shiftId,

    // ✅ FIX ERROR
    'timeSlotId' => $timeSlotId,
    'timeLabel'  => ($timeSlot['time_start'] ?? '-') . ' - ' . ($timeSlot['time_end'] ?? '-'),

    'rows'       => $rows,
    'canEdit'    => true
]);

    }

    /* ================= STORE HOURLY ================= */
    public function store()
    {
        $items = $this->request->getPost('items');

        if (!is_array($items)) {
            return redirect()->back()
                ->with('error', 'Tidak ada data untuk disimpan');
        }

        foreach ($items as $row) {

            if (
                empty($row['machine_id']) ||
                empty($row['product_id'])
            ) {
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
                'downtime_minute' => $row['downtime'] ?? 0,
                'remark'          => $row['remark'] ?? null,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Hourly Production Machining berhasil disimpan');
    }
}
