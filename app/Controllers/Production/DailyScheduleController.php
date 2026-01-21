<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\DailyScheduleModel;
use App\Models\DailyScheduleItemModel;
use App\Models\ProductModel;
use App\Models\MachineModel;
use App\Models\ShiftModel;
use App\Models\ProductionStandardModel;

class DailyScheduleController extends BaseController
{
    protected $scheduleModel;
    protected $itemModel;
    protected $productModel;
    protected $machineModel;
    protected $shiftModel;
    protected $standardModel;

    public function __construct()
    {
        $this->scheduleModel  = new DailyScheduleModel();
        $this->itemModel     = new DailyScheduleItemModel();
        $this->productModel  = new ProductModel();
        $this->machineModel  = new MachineModel();
        $this->shiftModel    = new ShiftModel();
        $this->standardModel = new ProductionStandardModel();
    }

    /* =====================================================
     * FORM INPUT DAILY SCHEDULE
     * ===================================================== */
    public function index()
    {
        return view('production/daily_schedule/index', [
            'shifts' => $this->shiftModel->where('is_active', 1)->findAll()
        ]);
    }

    /* =====================================================
     * AJAX: GET MACHINE BY SECTION
     * ===================================================== */
    public function getMachines()
    {
        $section = $this->request->getGet('section');

        // mapping section → process
        $processMap = [
            'Die Casting' => 1,
            'Machining'  => 2,
        ];

        if (!isset($processMap[$section])) {
            return $this->response->setJSON([]);
        }

        return $this->response->setJSON(
            $this->machineModel
                ->where('process_id', $processMap[$section])
                ->orderBy('line_position')
                ->findAll()
        );
    }


    /* =====================================================
     * AJAX: GET PRODUCT BY MACHINE
     * (BASED ON PRODUCTION STANDARD)
     * ===================================================== */
    public function getProducts()
    {
        $machineId = $this->request->getGet('machine_id');
        $db = db_connect();

        $products = $db->table('machine_products mp')
            ->select('p.id, p.part_no, p.part_name')
            ->join('products p', 'p.id = mp.product_id')
            ->where('mp.machine_id', $machineId)
            ->where('mp.is_active', 1)
            ->orderBy('p.part_no')
            ->get()
            ->getResultArray();

        return $this->response->setJSON($products);
    }


    /* =====================================================
     * HITUNG TOTAL MENIT SHIFT DARI TIME SLOT
     * ===================================================== */
    private function getShiftTotalMinute($shiftId)
    {
        $db = db_connect();

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()
            ->getResultArray();

        $totalMinute = 0;

        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);

            // support shift malam
            if ($end < $start) {
                $end += 86400;
            }

            $totalMinute += ($end - $start) / 60;
        }

        return $totalMinute;
    }

    /* =====================================================
     * AJAX: HITUNG TARGET PRODUKSI
     * ===================================================== */
    public function calculateTarget()
    {
        $machineId = $this->request->getGet('machine_id');
        $productId = $this->request->getGet('product_id');
        $shiftId   = $this->request->getGet('shift_id');

        // ambil durasi shift dari time slot
        $totalMinute = $this->getShiftTotalMinute($shiftId);

        if ($totalMinute <= 0) {
            return $this->response->setJSON([
                'error' => 'Shift belum memiliki time slot'
            ]);
        }

        // ambil production standard
        $std = $this->standardModel->getStandard($machineId, $productId);
        if (!$std) {
            return $this->response->setJSON([
                'error' => 'Production standard tidak ditemukan'
            ]);
        }

        $cycle  = (int) $std['cycle_time_sec'];
        $cavity = (int) $std['cavity'];

        // RUMUS FINAL
        $targetShift = floor(
            ($totalMinute * 60 / $cycle) * $cavity
        );

        $targetHour = floor(
            $targetShift / ($totalMinute / 60)
        );

        return $this->response->setJSON([
            'target_per_shift' => $targetShift,
            'target_per_hour'  => $targetHour,
            'cycle_time'       => $cycle,
            'cavity'           => $cavity,
            'shift_minute'     => $totalMinute
        ]);
    }

    /* =====================================================
     * SIMPAN DAILY SCHEDULE
     * ===================================================== */
    public function store()
    {
        $db = db_connect();
        $db->transStart();

        $scheduleDate = $this->request->getPost('schedule_date');

        if (!$scheduleDate) {
            return redirect()->back()
                ->with('error', 'Tanggal schedule wajib diisi');
        }

        $scheduleId = $this->scheduleModel->insert([
            'schedule_date' => $scheduleDate,
            'shift_id'      => $this->request->getPost('shift_id'),
            'section'       => $this->request->getPost('section'),
            'is_completed'  => 0,
            'created_at'    => date('Y-m-d H:i:s')
        ]);

        foreach ($this->request->getPost('items') as $item) {
            if (empty($item['is_selected']) || empty($item['product_id'])) {
                continue;
            }

            $this->itemModel->insert([
                'daily_schedule_id' => $scheduleId,
                'machine_id'        => $item['machine_id'],
                'product_id'        => $item['product_id'],
                'target_per_hour'   => $item['target_per_hour'],
                'target_per_shift'  => $item['target_per_shift'],
                'is_selected'       => 1
            ]);
        }

        $db->transComplete();

        return redirect()->to('/production/daily-schedule/list?date=' . $scheduleDate)
            ->with('success', 'Daily schedule berhasil disimpan');
    }


        /* =====================================================
        * LIST DAILY SCHEDULE
        * ===================================================== */
public function list()
{
    $date    = $this->request->getGet('date') ?? date('Y-m-d');
    $type    = $this->request->getGet('type'); // DC / MC (optional)
    $db      = db_connect();

    // =========================
    // FILTER SHIFT NAME
    // =========================
    $shiftQuery = $db->table('shifts')
        ->where('is_active', 1);

    if ($type === 'DC') {
        $shiftQuery->like('shift_name', 'DC');
    } elseif ($type === 'MC') {
        $shiftQuery->like('shift_name', 'MC');
    }

    // =========================
    // URUTKAN SHIFT:
    // DC dulu, MC setelahnya
    // lalu nomor shift
    // =========================
    $shiftQuery->orderBy("
        CASE
            WHEN shift_name LIKE '%DC%' THEN 1
            WHEN shift_name LIKE '%MC%' THEN 2
            ELSE 3
        END
    ", '', false);

    $shiftQuery->orderBy("
        CAST(
            REGEXP_SUBSTR(shift_name, '[0-9]+')
            AS UNSIGNED
        )
    ", '', false);

    $shifts = $shiftQuery->get()->getResultArray();

    // =========================
    // AMBIL DAILY SCHEDULE
    // =========================
    $rows = $db->table('daily_schedules ds')
        ->select('
            ds.id,
            ds.schedule_date,
            ds.section,
            ds.is_completed,
            ds.shift_id,
            s.shift_name
        ')
        ->join('shifts s', 's.id = ds.shift_id')
        ->where('ds.schedule_date', $date);

    if ($type === 'DC') {
        $rows->like('s.shift_name', 'DC');
    } elseif ($type === 'MC') {
        $rows->like('s.shift_name', 'MC');
    }

    $rows = $rows->get()->getResultArray();

    // =========================
    // GROUP BY SHIFT
    // =========================
    $grouped = [];

    foreach ($shifts as $shift) {
        $grouped[$shift['id']] = [
            'shift'     => $shift,
            'schedules' => []
        ];
    }

    foreach ($rows as $row) {
        if (isset($grouped[$row['shift_id']])) {
            $grouped[$row['shift_id']]['schedules'][] = $row;
        }
    }

    return view('production/daily_schedule/list', [
        'date'    => $date,
        'grouped' => $grouped,
        'type'    => $type
    ]);
}


    /* =====================================================
     * DETAIL DAILY SCHEDULE
     * ===================================================== */
    public function view($id)
    {
        $db = db_connect();

        $header = $db->table('daily_schedules ds')
            ->select('ds.*, s.shift_name')
            ->join('shifts s', 's.id = ds.shift_id')
            ->where('ds.id', $id)
            ->get()
            ->getRowArray();

        if (!$header) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException();
        }

        $items = $db->table('daily_schedule_items dsi')
        ->select('
            m.line_position,
            m.machine_code,
            p.part_no,
            p.part_name,
            dsi.target_per_hour,
            dsi.target_per_shift
        ')
        ->join('machines m', 'm.id = dsi.machine_id')
        ->join('products p', 'p.id = dsi.product_id')
        ->where('dsi.daily_schedule_id', $id)
        ->orderBy('m.line_position')
        ->get()
        ->getResultArray();


        return view('production/daily_schedule/view', [
            'header' => $header,
            'items'  => $items
        ]);
    }

    
}
