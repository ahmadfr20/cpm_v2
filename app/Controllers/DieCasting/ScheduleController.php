<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;
use App\Models\DailyScheduleModel;
use App\Models\DailyScheduleItemModel;
use App\Models\ProductModel;
use App\Models\MachineModel;
use App\Models\ShiftModel;

class ScheduleController extends BaseController
{
    public function index()
    {
        return view('die_casting/schedule_form', [
            'products' => model(ProductModel::class)->findAll(),
            'machines' => model(MachineModel::class)->findAll(),
            'shifts'   => model(ShiftModel::class)->findAll(),
        ]);
    }

    public function store()
    {
        $scheduleModel = new DailyScheduleModel();
        $itemModel     = new DailyScheduleItemModel();

        $db = db_connect();
        $db->transStart();

        $scheduleId = $scheduleModel->insert([
            'schedule_date' => $this->request->getPost('schedule_date'),
            'shift_id'      => $this->request->getPost('shift_id'),
            'section'       => 'Die Casting',
        ]);

        foreach ($this->request->getPost('items') as $row) {
            if (!isset($row['selected'])) continue;

            $itemModel->insert([
                'daily_schedule_id' => $scheduleId,
                'machine_id'        => $row['machine_id'],
                'product_id'        => $row['product_id'],
                'cycle_time'        => $row['cycle_time'],
                'target_per_hour'   => $row['target_hour'],
                'target_per_shift'  => $row['target_shift'],
            ]);
        }

        $db->transComplete();

        return redirect()->to('/die-casting/production');
    }
}
