<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\DailyScheduleModel;
use App\Models\DailyScheduleItemModel;
use App\Models\ProductModel;
use App\Models\MachineModel;
use App\Models\ShiftModel;

class DailyScheduleController extends BaseController
{
    protected $scheduleModel;
    protected $itemModel;
    protected $shiftModel;
    protected $productModel;
    protected $machineModel;

    public function __construct()
    {
        $this->scheduleModel = new DailyScheduleModel();
        $this->itemModel    = new DailyScheduleItemModel();
        $this->shiftModel   = new ShiftModel();
        $this->productModel = new ProductModel();
        $this->machineModel = new MachineModel();
    }

    public function index()
    {
        $today = date('Y-m-d');
        $db = db_connect();

        // 🔹 HEADER schedule hari ini
        $schedules = $db->table('daily_schedules ds')
            ->select('ds.*, s.shift_name')
            ->join('shifts s', 's.id = ds.shift_id')
            ->where('ds.schedule_date', $today)
            ->orderBy('ds.section')
            ->get()
            ->getResultArray();

        // 🔹 DETAIL
        foreach ($schedules as &$sch) {
            $sch['items'] = $db->table('daily_schedule_items dsi')
                ->select('
                    p.part_no,
                    p.part_name,
                    m.machine_code,
                    dsi.target_per_shift,
                    dsi.target_per_hour,
                    dsi.cycle_time
                ')
                ->join('products p', 'p.id = dsi.product_id')
                ->join('machines m', 'm.id = dsi.machine_id')
                ->where('dsi.daily_schedule_id', $sch['id'])
                ->get()
                ->getResultArray();
        }

        return view('production/daily_schedule/index', [
            'todaySchedules' => $schedules,
            'shifts'   => $this->shiftModel->findAll(),
            'products' => $this->productModel->findAll(),
            'machines' => $this->machineModel->findAll(),
        ]);
    }


    public function store()
    {
        $db = db_connect();
        $db->transStart();

        // HEADER
        $scheduleId = $this->scheduleModel->insert([
            'schedule_date' => $this->request->getPost('schedule_date'),
            'shift_id'      => $this->request->getPost('shift_id'),
            'section'       => $this->request->getPost('section'),
        ]);

        // DETAIL
        foreach ($this->request->getPost('items') as $item) {

            if (!isset($item['selected'])) continue;

            $this->itemModel->insert([
                'daily_schedule_id' => $scheduleId,
                'product_id'        => $item['product_id'],
                'machine_id'        => $item['machine_id'],
                'cycle_time'        => $item['cycle_time'],
                'target_per_hour'   => $item['target_per_hour'],
                'target_per_shift'  => $item['target_per_shift'],
            ]);
        }

        $db->transComplete();

        return redirect()->to('/production/daily-schedule')
            ->with('success','Daily Production Schedule berhasil disimpan');
    }
}