<?php

namespace App\Controllers\RawMaterial;

use App\Controllers\BaseController;

class ScrapController extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // Fetch active DC shifts
        $shifts = $db->table('shifts')
                     ->where('is_active', 1)
                     ->like('shift_name', 'DC')
                     ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
                     ->get()->getResultArray();

        // 1. Calculate DC Runner Scrap (from die_casting_production where qty_plan used)
        $dcRunner = $db->table('die_casting_production dcp')
            ->select('dcp.shift_id, SUM((dcp.qty_p / IF(p.cavity > 0, p.cavity, 1)) * (p.weight_runner / 1000)) as scrap_runner')
            ->join('products p', 'p.id = dcp.product_id', 'left')
            ->where('dcp.production_date', $date)
            ->groupBy('dcp.shift_id')
            ->get()->getResultArray();

        // 2. Calculate DC NG Scrap (from die_casting_hourly)
        $dcNg = $db->table('die_casting_hourly h')
            ->select('h.shift_id, SUM(h.qty_ng * (p.weight_die_casting / 1000)) as scrap_ng')
            ->join('products p', 'p.id = h.product_id', 'left')
            ->where('h.production_date', $date)
            ->groupBy('h.shift_id')
            ->get()->getResultArray();

        // 3. Machining Chips and NG Scrap
        // We will align by parsing "Shift 1", "Shift 2", etc.
        // First we map machining shifts
        $macShifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->get()->getResultArray();
        
        $macShiftOrderMap = [];
        foreach ($macShifts as $ms) {
            if (preg_match('/Shift\s+(\d+)/i', $ms['shift_name'], $matches)) {
                $macShiftOrderMap[(int)$ms['id']] = (int)$matches[1]; // e.g. 1, 2, 3
            } else {
                $macShiftOrderMap[(int)$ms['id']] = 0;
            }
        }

        $macScrapData = $db->table('machining_hourly h')
            ->select('h.shift_id, SUM((h.qty_fg + h.qty_ng) * ((p.weight_die_casting / 1000) - p.weight_machining)) as chips_kg, SUM(h.qty_ng * (p.weight_die_casting / 1000)) as ng_kg')
            ->join('products p', 'p.id = h.product_id', 'left')
            ->where('h.production_date', $date)
            ->groupBy('h.shift_id')
            ->get()->getResultArray();

        $calcData = [];
        foreach ($shifts as $s) {
            $shiftCode = (int)$s['shift_code'];
            $calcData[$s['id']] = [
                'dc_runner' => 0.0,
                'dc_ng'     => 0.0,
                'mac_chips' => 0.0,
                'mac_ng'    => 0.0
            ];
        }

        foreach ($dcRunner as $r) {
            if (isset($calcData[$r['shift_id']])) {
                $calcData[$r['shift_id']]['dc_runner'] = (float)$r['scrap_runner'];
            }
        }
        foreach ($dcNg as $r) {
            if (isset($calcData[$r['shift_id']])) {
                $calcData[$r['shift_id']]['dc_ng'] = (float)$r['scrap_ng'];
            }
        }

        // Align machining data to DC shift via "Shift N" pattern
        foreach ($macScrapData as $m) {
            $macOrder = $macShiftOrderMap[(int)$m['shift_id']] ?? 0;
            // find dc shift with same order
            $dcShiftId = 0;
            foreach ($shifts as $s) {
                if (preg_match('/Shift\s+(\d+)/i', $s['shift_name'], $sMatch)) {
                    if ((int)$sMatch[1] === $macOrder && $macOrder > 0) {
                        $dcShiftId = (int)$s['id'];
                        break;
                    }
                }
            }
            if ($dcShiftId > 0 && isset($calcData[$dcShiftId])) {
                // Ensure chips are not negative if weight_dc < weight_machining mistakenly
                $calcData[$dcShiftId]['mac_chips'] += max(0, (float)$m['chips_kg']);
                $calcData[$dcShiftId]['mac_ng']    += (float)$m['ng_kg'];
            }
        }

        // Fetch existing scrap records for this date
        $receives = $db->table('raw_material_scrap_receives')
                       ->where('receive_date', $date)
                       ->get()->getResultArray();
        
        $mappedReceives = [];
        foreach ($receives as $r) {
            $mappedReceives[$r['shift_id']] = [
                'actual' => (float)$r['actual_scrap_received_kg'],
                'notes'  => $r['notes']
            ];
        }

        $history = $db->table('raw_material_scrap_receives r')
            ->select('r.*, s.shift_name')
            ->join('shifts s', 's.id = r.shift_id', 'left')
            ->orderBy('r.receive_date', 'DESC')
            ->orderBy('r.created_at', 'DESC')
            ->limit(100)
            ->get()->getResultArray();

        return view('raw_material/scrap/index', [
            'date'       => $date,
            'shifts'     => $shifts,
            'calcData'   => $calcData,
            'receives'   => $mappedReceives,
            'history'    => $history
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
                
                $dcRunner = (float)($row['dc_runner'] ?? 0);
                $dcNg     = (float)($row['dc_ng'] ?? 0);
                $macChips = (float)($row['mac_chips'] ?? 0);
                $macNg    = (float)($row['mac_ng'] ?? 0);
                $actual   = (float)($row['actual'] ?? 0);
                $notes    = trim($row['notes'] ?? '');

                if ($shiftId <= 0) continue;

                $exist = $db->table('raw_material_scrap_receives')
                    ->where('receive_date', $date)
                    ->where('shift_id', $shiftId)
                    ->get()->getRowArray();

                if ($exist) {
                    $db->table('raw_material_scrap_receives')
                        ->where('id', $exist['id'])
                        ->update([
                            'dc_runner_scrap_kg'       => $dcRunner,
                            'dc_ng_scrap_kg'           => $dcNg,
                            'machining_chips_kg'       => $macChips,
                            'machining_ng_kg'          => $macNg,
                            'actual_scrap_received_kg' => $actual,
                            'notes'                    => $notes,
                            'updated_at'               => $now
                        ]);
                } else {
                    if ($actual > 0 || $dcRunner > 0 || $dcNg > 0 || $macChips > 0 || $macNg > 0) {
                        $db->table('raw_material_scrap_receives')->insert([
                            'receive_date'             => $date,
                            'shift_id'                 => $shiftId,
                            'dc_runner_scrap_kg'       => $dcRunner,
                            'dc_ng_scrap_kg'           => $dcNg,
                            'machining_chips_kg'       => $macChips,
                            'machining_ng_kg'          => $macNg,
                            'actual_scrap_received_kg' => $actual,
                            'notes'                    => $notes,
                            'created_at'               => $now,
                            'updated_at'               => $now
                        ]);
                    }
                }
            }
            
            // Sync inventory stock for SCRAP
            $db->query("INSERT INTO raw_material_stock (material_type, unit, total_qty, updated_at) 
                        SELECT 'SCRAP', 'Kg', IFNULL(SUM(actual_scrap_received_kg), 0), ? 
                        FROM raw_material_scrap_receives 
                        ON DUPLICATE KEY UPDATE total_qty = VALUES(total_qty), updated_at = VALUES(updated_at)", [$now]);

            if ($db->transStatus() === false) throw new \Exception('Terjadi kesalahan pada database.');

            $db->transCommit();
            return redirect()->back()->with('success', 'Data penerimaan scrap berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
