<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;
use App\Models\ShiftModel;
use App\Models\DieCastingProductionModel;

class ProductionController extends BaseController
{
    public function index()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $db   = db_connect();

        $shiftModel = new ShiftModel();
        $shifts = $shiftModel->findAll() ?? [];

        $rows = $db->table('daily_schedule_items dsi')
            ->select('
                ds.shift_id,
                s.shift_name,
                m.machine_code,
                m.id AS machine_id,
                p.id AS product_id,
                p.part_name,
                dsi.target_per_shift,
                IFNULL(SUM(pr.qty_p),0)  AS qty_p,
                IFNULL(SUM(pr.qty_a),0)  AS qty_a,
                IFNULL(SUM(pr.qty_ng),0) AS qty_ng,
                IFNULL(SUM(pr.weight_kg),0) AS weight_kg
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->join('shifts s', 's.id = ds.shift_id')
            ->join('machines m', 'm.id = dsi.machine_id')
            ->join('products p', 'p.id = dsi.product_id')
            ->join(
                'die_casting_production pr',
                'pr.machine_id = dsi.machine_id
                 AND pr.product_id = dsi.product_id
                 AND pr.shift_id = ds.shift_id
                 AND pr.production_date = "'.$date.'"',
                'left'
            )
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Die Casting')
            ->groupBy('ds.shift_id, dsi.machine_id, dsi.product_id')
            ->orderBy('m.machine_code')
            ->get()
            ->getResultArray();

        // 🔒 WAJIB INIT
        $data = [];
        $totalKg = [];

        if (!empty($rows)) {
            foreach ($rows as $r) {
                $data[$r['machine_code']][$r['shift_id']] = $r;

                if (!isset($totalKg[$r['shift_id']])) {
                    $totalKg[$r['shift_id']] = 0;
                }
                $totalKg[$r['shift_id']] += $r['weight_kg'];
            }
        }

        return view('die_casting/production/index', [
            'date'    => $date,
            'shifts'  => $shifts,
            'data'    => $data,
            'totalKg' => $totalKg,
        ]);
    }

    // ================= STORE =================
    public function store()
    {
        $model = new DieCastingProductionModel();

        // 🔴 AMBIL DULU, JANGAN LANGSUNG FOREACH
        $items = $this->request->getPost('items');

        // 🔒 GUARD UTAMA
        if (!is_array($items)) {
            return redirect()->back()
                ->with('error', 'Tidak ada data produksi untuk disimpan');
        }

        foreach ($items as $row) {

            // 🔒 VALIDASI WAJIB
            if (
                empty($row['shift_id']) ||
                empty($row['machine_id']) ||
                empty($row['product_id'])
            ) {
                continue;
            }

            $model->insert([
                'production_date' => $this->request->getPost('production_date'),
                'shift_id'        => $row['shift_id'],
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'qty_p'           => $row['qty_p'] ?? 0,
                'qty_a'           => $row['qty_a'] ?? 0,
                'qty_ng'          => $row['qty_ng'] ?? 0,
                'weight_kg'       => $row['weight_kg'] ?? 0,
            ]);
        }

        return redirect()->back()->with('success', 'Production berhasil disimpan');
    }
}
