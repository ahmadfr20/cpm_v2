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

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->get()->getResultArray();

        $totalMinute = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end <= $start) $end += 86400;
            $totalMinute += ($end - $start) / 60;
        }

        $products = $db->table('machine_products mp')
            ->select('
                p.id,
                p.part_no,
                p.part_name,
                p.weight_ascas,
                p.weight_runner,
                ps.cycle_time_sec,
                ps.cavity
            ')
            ->join('products p', 'p.id = mp.product_id')
            ->join(
                'production_standards ps',
                'ps.product_id = p.id AND ps.machine_id = mp.machine_id',
                'left'
            )
            ->where('mp.machine_id', $machineId)
            ->where('mp.is_active', 1)
            ->get()->getResultArray();

        foreach ($products as &$p) {
            if ($p['cycle_time_sec'] && $p['cavity']) {
                $target = floor(($totalMinute * 60 / $p['cycle_time_sec']) * $p['cavity']);
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
        $db = db_connect();

        foreach ($this->request->getPost('items') as $row) {

            if (empty($row['product_id'])) continue;

            $qtyP = ($row['status'] === 'OFF') ? 0 : min((int)$row['qty_p'], 1200);
            $qtyA = (int)($row['qty_a'] ?? 0);

            $weightKg = (
                ($qtyP * $row['weight_ascas']) +
                ($qtyA * $row['weight_runner'])
            ) / 1000;

            $db->table('die_casting_production')->replace([
                'production_date' => $row['date'],
                'shift_id'        => $row['shift_id'],
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'qty_p'           => $qtyP,
                'qty_a'           => $qtyA,
                'qty_ng'          => (int)($row['qty_ng'] ?? 0),
                'weight_kg'       => $weightKg,
                'status'          => $row['status'],
                'created_at'      => date('Y-m-d H:i:s')
            ]);
        }

        return redirect()->back()->with('success', 'Daily schedule tersimpan');
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
