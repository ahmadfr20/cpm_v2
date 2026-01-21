<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyScheduleController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        $machines = $db->table('machines')
            ->where('production_line', 'Die Casting')
            ->orderBy('line_position')
            ->get()->getResultArray();

        $existing = $db->table('die_casting_production')
            ->where('production_date', $date)
            ->get()->getResultArray();

        $map = [];
        foreach ($existing as $e) {
            $map[$e['shift_id']][$e['machine_id']] = $e;
        }

        return view('die_casting/daily_schedule/index', compact(
            'date', 'shifts', 'machines', 'map'
        ));
    }

    /* =========================
     * AJAX PRODUCT & TARGET
     * ========================= */
public function getProductAndTarget()
{
    $db        = db_connect();
    $machineId = $this->request->getGet('machine_id');
    $shiftId   = $this->request->getGet('shift_id');

    if (!$machineId || !$shiftId) {
        return $this->response->setJSON([]);
    }

    /* =========================
     * HITUNG TOTAL MENIT SHIFT
     * ========================= */
    $slots = $db->table('shift_time_slots sts')
        ->select('ts.time_start, ts.time_end')
        ->join('time_slots ts', 'ts.id = sts.time_slot_id')
        ->where('sts.shift_id', $shiftId)
        ->get()->getResultArray();

    $totalMinute = 0;
    foreach ($slots as $s) {
        $start = strtotime($s['time_start']);
        $end   = strtotime($s['time_end']);
        if ($end <= $start) {
            $end += 86400;
        }
        $totalMinute += ($end - $start) / 60;
    }

    /* =========================
     * PRODUCT + PARAMETER DARI MASTER PRODUCT
     * ========================= */
    $products = $db->table('machine_products mp')
        ->select('
            p.id,
            p.part_no,
            p.part_name,
            p.weight_ascas,
            p.weight_runner,
            p.cycle_time,
            p.cavity,
            p.efficiency_rate
        ')
        ->join('products p', 'p.id = mp.product_id')
        ->where('mp.machine_id', $machineId)
        ->get()
        ->getResultArray();

    /* =========================
     * HITUNG TARGET
     * ========================= */
    foreach ($products as &$p) {

        $cycle = (int) ($p['cycle_time'] ?? 0);
        $cavity = (int) ($p['cavity'] ?? 0);
        $eff = (float) ($p['efficiency_rate'] ?? 1); // default 100%

        if ($cycle > 0 && $cavity > 0) {

            // rumus target
            $target = floor(
                ($totalMinute * 60 / $cycle) *
                $cavity *
                $eff
            );

            $p['target'] = min($target, 1200);
        } else {
            $p['target'] = 0;
        }
    }

    return $this->response->setJSON($products);
}


    /* =========================
     * STORE
     * ========================= */
public function store()
{
    $db    = db_connect();
    $items = $this->request->getPost('items');

    if (!$items || !is_array($items)) {
        return redirect()->back()->with('error', 'Tidak ada data yang disimpan');
    }

    $db->transBegin();

    try {

        /* =========================
         * AMBIL TANGGAL
         * ========================= */
        $date = null;
        foreach ($items as $row) {
            if (!empty($row['date'])) {
                $date = $row['date'];
                break;
            }
        }

        if (!$date) {
            throw new \Exception('Tanggal tidak valid');
        }

        /* =========================
         * LOOP PER ITEM (BISA 1 SAJA)
         * ========================= */
        foreach ($items as $row) {

            if (
                empty($row['shift_id']) ||
                empty($row['machine_id']) ||
                empty($row['product_id'])
            ) {
                continue;
            }

            $shiftId   = (int) $row['shift_id'];
            $machineId = (int) $row['machine_id'];
            $productId = (int) $row['product_id'];

            $qtyP  = (int) ($row['qty_p'] ?? 0);
            $qtyA  = (int) ($row['qty_a'] ?? 0);
            $qtyNG = (int) ($row['qty_ng'] ?? 0);

            // kalau semua nol → skip (tapi item lain tetap jalan)
            if ($qtyP <= 0 && $qtyA <= 0 && $qtyNG <= 0) {
                continue;
            }

            /* =========================
             * DAILY SCHEDULE (HEADER)
             * ========================= */
            $schedule = $db->table('daily_schedules')
                ->where([
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Die Casting'
                ])
                ->get()
                ->getRowArray();

            if (!$schedule) {
                $db->table('daily_schedules')->insert([
                    'schedule_date' => $date,
                    'shift_id'      => $shiftId,
                    'section'       => 'Die Casting',
                    'is_completed'  => 0,
                    'created_at'    => date('Y-m-d H:i:s')
                ]);
                $dailyScheduleId = $db->insertID();
            } else {
                $dailyScheduleId = $schedule['id'];
            }

            /* =========================
             * MASTER PRODUCT
             * ========================= */
            $product = $db->table('products')
                ->select('weight_ascas, weight_runner, cycle_time, cavity, efficiency_rate')
                ->where('id', $productId)
                ->get()
                ->getRowArray();

            if (!$product) continue;

            $wa     = (float) $product['weight_ascas'];
            $wr     = (float) $product['weight_runner'];
            $cycle  = (int) $product['cycle_time'];
            $cavity = (int) $product['cavity'];
            $eff    = ((float) $product['efficiency_rate']) / 100;

            $targetPerHour = ($cycle > 0 && $cavity > 0)
                ? floor((3600 / $cycle) * $cavity * $eff)
                : 0;

            $targetPerShift = min($targetPerHour * 8, 1200);

            /* =========================
             * DIE CASTING PRODUCTION
             * (UPSERT)
             * ========================= */
            $existProd = $db->table('die_casting_production')
                ->where([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId
                ])
                ->get()
                ->getRowArray();

            if ($existProd) {
                // UPDATE
                $db->table('die_casting_production')
                    ->where('id', $existProd['id'])
                    ->update([
                        'qty_p'     => $qtyP,
                        'qty_a'     => $qtyA,
                        'qty_ng'    => $qtyNG,
                        'weight_kg' => (($qtyP * $wa) + ($qtyA * $wr)) / 1000,
                        'status'    => $row['status'] ?? $existProd['status']
                    ]);
            } else {
                // INSERT
                $db->table('die_casting_production')->insert([
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'qty_p'           => $qtyP,
                    'qty_a'           => $qtyA,
                    'qty_ng'          => $qtyNG,
                    'weight_kg'       => (($qtyP * $wa) + ($qtyA * $wr)) / 1000,
                    'status'          => $row['status'] ?? 'Normal',
                    'created_at'      => date('Y-m-d H:i:s')
                ]);
            }

            /* =========================
             * DAILY SCHEDULE ITEM
             * (CEK DUPLIKAT)
             * ========================= */
            $existItem = $db->table('daily_schedule_items')
                ->where([
                    'daily_schedule_id' => $dailyScheduleId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId
                ])
                ->get()
                ->getRowArray();

            if (!$existItem) {
                $db->table('daily_schedule_items')->insert([
                    'daily_schedule_id' => $dailyScheduleId,
                    'shift_id'          => $shiftId,
                    'machine_id'        => $machineId,
                    'product_id'        => $productId,
                    'cycle_time'        => $cycle,
                    'cavity'            => $cavity,
                    'target_per_hour'   => $targetPerHour,
                    'target_per_shift'  => $targetPerShift,
                    'is_selected'       => 1
                ]);
            }
        }

        $db->transCommit();
        return redirect()->back()->with('success', 'Data berhasil disimpan');

    } catch (\Throwable $e) {
        $db->transRollback();
        return redirect()->back()->with('error', $e->getMessage());
    }
}




    public function view()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $rows = $db->table('die_casting_production dcp')
            ->select('
                s.shift_name,
                m.machine_code,
                m.line_position,
                p.part_no,
                p.weight_ascas,
                p.weight_runner,
                dcp.qty_p,
                dcp.qty_a,
                dcp.qty_ng,
                dcp.status
            ')
            ->join('shifts s', 's.id = dcp.shift_id')
            ->join('machines m', 'm.id = dcp.machine_id')
            ->join('products p', 'p.id = dcp.product_id', 'left')
            ->where('dcp.production_date', $date)

            // urutan shift
            ->orderBy(
                "FIELD(
                    s.shift_name,
                    'Shift 1 DC (Mon-Thu)',
                    'Shift 2 DC (Mon-Thu)',
                    'Shift 3 DC (Mon-Thu)'
                )",
                '',
                false
            )
            ->orderBy('m.line_position')
            ->get()
            ->getResultArray();

        return view('die_casting/daily_schedule/view', [
            'date' => $date,
            'rows' => $rows
        ]);
    }


}
