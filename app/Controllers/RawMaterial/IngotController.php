<?php

namespace App\Controllers\RawMaterial;

use App\Controllers\BaseController;

class IngotController extends BaseController
{
    public function index()
    {
        $db = db_connect();
        // Fallback or selected date
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // Fetch active DC shifts (since Raw Material supplies Die Casting directly)
        $shifts = $db->table('shifts')
                     ->where('is_active', 1)
                     ->like('shift_name', 'DC')
                     ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
                     ->get()->getResultArray();

        // Fetch existing ingot records for this date
        $receives = $db->table('raw_material_ingot_receives')
                       ->where('receive_date', $date)
                       ->get()->getResultArray();
        
        $mappedReceives = [];
        foreach ($receives as $r) {
            $mappedReceives[$r['shift_id']] = [
                'qty'  => (int)$r['qty_ingot'],
                'unit' => $r['unit']
            ];
        }

        // Fetch history of receiving (so it can be displayed below the form)
        $history = $db->table('raw_material_ingot_receives r')
            ->select('r.*, s.shift_name')
            ->join('shifts s', 's.id = r.shift_id', 'left')
            ->orderBy('r.receive_date', 'DESC')
            ->orderBy('r.created_at', 'DESC')
            ->limit(100)
            ->get()->getResultArray();

        return view('raw_material/ingot/index', [
            'date'     => $date,
            'shifts'   => $shifts,
            'receives' => $mappedReceives,
            'history'  => $history
        ]);
    }

    public function store()
    {
        $db = db_connect();
        $date = $this->request->getPost('date');
        $items = $this->request->getPost('items');

        if (empty($date) || !is_array($items)) {
            return redirect()->back()->with('error', 'Data tidak valid.');
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            foreach ($items as $sId => $row) {
                $shiftId = (int)$sId;
                $qty = (int)($row['qty'] ?? 0);
                $unit = (string)($row['unit'] ?? 'Kg');
                
                if ($shiftId <= 0) continue;

                $exist = $db->table('raw_material_ingot_receives')
                    ->where('receive_date', $date)
                    ->where('shift_id', $shiftId)
                    ->get()->getRowArray();

                if ($exist) {
                    $db->table('raw_material_ingot_receives')
                        ->where('id', $exist['id'])
                        ->update([
                            'qty_ingot'  => $qty,
                            'unit'       => $unit,
                            'updated_at' => $now
                        ]);
                } else {
                    if ($qty > 0) {
                        $db->table('raw_material_ingot_receives')->insert([
                            'receive_date' => $date,
                            'shift_id'     => $shiftId,
                            'qty_ingot'    => $qty,
                            'unit'         => $unit,
                            'created_at'   => $now,
                            'updated_at'   => $now
                        ]);
                    }
                }
            }

            // Sync inventory stock for INGOT
            $db->query("INSERT INTO raw_material_stock (material_type, unit, total_qty, updated_at) 
                        SELECT 'INGOT', unit, IFNULL(SUM(qty_ingot), 0), ? 
                        FROM raw_material_ingot_receives 
                        GROUP BY unit
                        ON DUPLICATE KEY UPDATE total_qty = VALUES(total_qty), updated_at = VALUES(updated_at)", [$now]);

            if ($db->transStatus() === false) throw new \Exception('Terjadi kesalahan pada database.');

            $db->transCommit();
            return redirect()->back()->with('success', 'Data penerimaan ingot berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
