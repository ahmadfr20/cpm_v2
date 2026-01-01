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
    protected $productModel;
    protected $machineModel;
    protected $shiftModel;

    public function __construct()
    {
        $this->scheduleModel = new DailyScheduleModel();
        $this->itemModel    = new DailyScheduleItemModel();
        $this->productModel = new ProductModel();
        $this->machineModel = new MachineModel();
        $this->shiftModel   = new ShiftModel();
    }

    // =============================
    // FORM INPUT
    // =============================
    public function index()
    {
        return view('production/daily_schedule/index', [
            'products' => $this->productModel->findAll(),
            'shifts'   => $this->shiftModel->findAll()
        ]);
    }

    // =============================
    // AJAX: MACHINE BY SECTION
    // =============================
    public function getMachines()
    {
        return $this->response->setJSON(
            $this->machineModel
                ->where('production_line', $this->request->getGet('section'))
                ->findAll()
        );
    }

    // =============================
    // STORE SCHEDULE
    // =============================
    public function store()
    {
        $db = db_connect();
        $db->transStart();

        $scheduleId = $this->scheduleModel->insert([
            'schedule_date' => date('Y-m-d'),
            'shift_id'      => $this->request->getPost('shift_id'),
            'section'       => $this->request->getPost('section'),
            'is_completed'  => 0,
            'created_at'    => date('Y-m-d H:i:s')
        ]);

        foreach ($this->request->getPost('items') as $item) {

            if (!isset($item['is_selected'])) continue;

            $this->itemModel->insert([
                'daily_schedule_id' => $scheduleId,
                'product_id'        => $item['product_id'],
                'machine_id'        => $item['machine_id'],
                'cycle_time'        => 40,
                'cavity'            => 2,
                'target_per_hour'   => $item['target_per_hour'],
                'target_per_shift'  => $item['target_per_shift'],
                'is_selected'       => 1
            ]);
        }

        $db->transComplete();

        return redirect()->to('/production/daily-schedule/view/' . $scheduleId);
    }

    // =============================
    // VIEW RESULT
    // =============================
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
                p.part_no,
                p.part_name,
                m.machine_code,
                dsi.cycle_time,
                dsi.cavity,
                dsi.target_per_hour,
                dsi.target_per_shift
            ')
            ->join('products p', 'p.id = dsi.product_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->where('dsi.daily_schedule_id', $id)
            ->get()
            ->getResultArray();

        return view('production/daily_schedule/view', [
            'header' => $header,
            'items'  => $items
        ]);
    }

    public function list()
    {
        $db = db_connect();

        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $schedules = $db->table('daily_schedules ds')
            ->select('
                ds.id,
                ds.schedule_date,
                ds.section,
                ds.is_completed,
                s.shift_name
            ')
            ->join('shifts s', 's.id = ds.shift_id')
            ->where('ds.schedule_date', $date)
            ->orderBy('ds.section')
            ->get()
            ->getResultArray();

        return view('production/daily_schedule/list', [
            'date'      => $date,
            'schedules' => $schedules
        ]);
    }

}
