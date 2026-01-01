<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('machining/schedule/index', [
            'date'     => date('Y-m-d'),
            'shifts'   => $db->table('shifts')->get()->getResultArray(),
            'machines' => $db->table('machines')
                            ->where('production_line', 'Machining')
                            ->get()->getResultArray(),
            'products' => $db->table('products')->get()->getResultArray(),
        ]);
    }

    public function store()
    {
        $db = db_connect();

        // 1. Insert header schedule
        $db->table('daily_schedules')->insert([
            'schedule_date' => $this->request->getPost('date'),
            'shift_id'      => $this->request->getPost('shift_id'),
            'section'       => 'Machining',
            'created_at'    => date('Y-m-d H:i:s')
        ]);

        $scheduleId = $db->insertID();

        // 2. Insert detail schedule
        $items = $this->request->getPost('items');

        foreach ($items as $item) {
            $db->table('daily_schedule_items')->insert([
                'daily_schedule_id' => $scheduleId,
                'machine_id'        => $item['machine_id'],
                'product_id'        => $item['product_id'],
                'cycle_time'        => $item['cycle_time'],
                'cavity'            => $item['cavity'],
                'target_per_hour'   => $item['target_hour'],
                'target_per_shift'  => $item['target_shift'],
            ]);
        }

        return redirect()->to('/machining/production')
            ->with('success', 'Daily schedule machining berhasil dibuat');
    }
}
