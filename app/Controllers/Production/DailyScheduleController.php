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

    public function __construct()
    {
        $this->scheduleModel = new DailyScheduleModel();
        $this->itemModel    = new DailyScheduleItemModel();
    }

    public function index()
    {
        return view('production/daily_schedule/index', [
            'shifts'   => model(ShiftModel::class)->findAll(),
            'products' => model(ProductModel::class)->findAll(),
            'machines' => model(MachineModel::class)->findAll(),
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
