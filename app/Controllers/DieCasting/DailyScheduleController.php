<?php
namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;
use App\Models\ProductionStandardModel;

class DailyScheduleController extends BaseController
{
    protected $standardModel;

    public function __construct()
    {
        $this->standardModel = new ProductionStandardModel();
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $shifts = $db->table('shifts')
            ->where('is_active',1)
            ->orderBy('id')
            ->get()->getResultArray();

        $machines = $db->table('machines')
            ->where('process_id',1) // Die Casting
            ->orderBy('line_position')
            ->get()->getResultArray();

        // data existing (PLAN + ACTUAL)
        $rows = $db->table('die_casting_production')
            ->where('production_date',$date)
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['shift_id']][$r['machine_id']] = $r;
        }

        return view('die_casting/daily_schedule/index', [
            'date'     => $date,
            'shifts'   => $shifts,
            'machines' => $machines,
            'map'      => $map
        ]);
    }

    /* =========================
     * AJAX: PRODUCT + TARGET
     * ========================= */
    public function getProductAndTarget()
    {
        $db        = db_connect();
        $machineId= $this->request->getGet('machine_id');
        $shiftId  = $this->request->getGet('shift_id');

        // total menit shift
        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts','ts.id=sts.time_slot_id')
            ->where('sts.shift_id',$shiftId)
            ->get()->getResultArray();

        $totalMinute = 0;
        foreach ($slots as $s) {
            $start = strtotime($s['time_start']);
            $end   = strtotime($s['time_end']);
            if ($end < $start) $end += 86400;
            $totalMinute += ($end - $start)/60;
        }

        $products = $db->table('machine_products mp')
            ->select('
                p.id,
                p.part_no,
                p.part_name,
                p.weight,
                ps.cycle_time_sec,
                ps.cavity
            ')
            ->join('products p','p.id=mp.product_id')
            ->join('production_standards ps','ps.product_id=p.id AND ps.machine_id=mp.machine_id')
            ->where('mp.machine_id',$machineId)
            ->where('mp.is_active',1)
            ->get()->getResultArray();

        foreach ($products as &$p) {
            $target = floor(($totalMinute * 60 / $p['cycle_time_sec']) * $p['cavity']);
            $p['target'] = min($target,1200);
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

            $qtyP = min((int)$row['qty_p'],1200);

            if ($row['status']==='OFF') {
                $qtyP = 0;
            }

            $db->table('die_casting_production')->replace([
                'production_date' => $row['date'],
                'shift_id'        => $row['shift_id'],
                'machine_id'      => $row['machine_id'],
                'product_id'      => $row['product_id'],
                'qty_p'           => $qtyP,
                'qty_a'           => $row['qty_a'],
                'qty_ng'          => $row['qty_ng'],
                'weight_kg'       => $qtyP * $row['weight'],
                'created_at'      => date('Y-m-d H:i:s')
            ]);
        }

        return redirect()->back()->with('success','Daily schedule die casting tersimpan');
    }

     public function view()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $data = $db->table('die_casting_production dcp')
            ->select('
                s.shift_name,
                m.machine_code,
                p.part_no,
                dcp.qty_p,
                dcp.qty_a,
                dcp.qty_ng,
                dcp.weight_kg,
                dcp.status
            ')
            ->join('shifts s','s.id=dcp.shift_id')
            ->join('machines m','m.id=dcp.machine_id')
            ->join('products p','p.id=dcp.product_id','left')
            ->where('dcp.production_date',$date)
            ->orderBy('s.id,m.line_position')
            ->get()->getResultArray();

        return view('die_casting/daily_schedule/view', [
            'date' => $date,
            'rows' => $data
        ]);
    }
}
