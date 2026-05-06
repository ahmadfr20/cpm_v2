<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionController extends BaseController
{
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $isAdmin  = $this->isAdminSession();

        $operatorModel = new \App\Models\MasterOperatorModel();
        $operators = $operatorModel->where('section', 'Die Casting')->orderBy('operator_name', 'ASC')->findAll();

        // SHIFT DC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->where('day_group', $this->getDayGroup($date))
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // NG category (Die Casting)
        $ngCategories = $db->table('ng_categories')
            ->where('process_name', 'Die Casting')
            ->orderBy('ng_code')
            ->get()->getResultArray();

        $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');

        // Master Downtime Categories
        $downtimes = [];
        if ($db->tableExists('downtime_categories')) {
            $downtimes = $db->table('downtime_categories')
                ->where('process_id', $dcProcessId)
                ->where('is_active', 1)
                ->orderBy('downtime_name', 'ASC')
                ->get()->getResultArray();
        }

        $wipHasQtyIn  = $db->tableExists('production_wip') ? $db->fieldExists('qty_in', 'production_wip') : false;
        $wipHasQtyOut = $db->tableExists('production_wip') ? $db->fieldExists('qty_out', 'production_wip') : false;
        $wipHasStock  = $db->tableExists('production_wip') ? $db->fieldExists('stock', 'production_wip') : false;

        foreach ($shifts as &$shift) {
            
            // Dapatkan semua slot (Tanpa memotong limit berdasarkan daily_schedules global)
            $allSlots = $db->query(
                "SELECT ts.id, ts.time_start, ts.time_end, sts.is_break
                 FROM shift_time_slots sts
                 JOIN time_slots ts ON ts.id = sts.time_slot_id
                 WHERE sts.shift_id = ?
                 ORDER BY sts.id ASC",
                [(int)$shift['id']]
            )->getResultArray();

            $shift['slots']     = $allSlots;
            $shift['shift_start'] = $allSlots[0]['time_start'] ?? null;
            $lastSlot = !empty($allSlots) ? $allSlots[count($allSlots)-1] : null;
            $shift['shift_end']   = $lastSlot['time_end'] ?? null;

            // Hitung total menit aktif (exclude slot istirahat)
            $totalMinute = 0;
            foreach ($allSlots as $k => $slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;
                $mins = (int)(($end - $start) / 60);
                $isBreak = (int)($slot['is_break'] ?? 0);
                $allSlots[$k]['minute']   = $isBreak ? 0 : max(0, $mins);
                $allSlots[$k]['is_break'] = $isBreak;
                if (!$isBreak) $totalMinute += max(0, $mins);
            }
            $shift['slots'] = $allSlots;
            $shift['total_minute'] = $totalMinute;

            // dandori map: machine_id => time_slot_id => { activity, product_id }
            $dandoriRecords = $db->table('die_casting_dandori')
                ->where('dandori_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['dandori_map'] = [];
            foreach ($dandoriRecords as $d) {
                $mId = (int)$d['machine_id'];
                $tId = !empty($d['time_slot_id']) ? (int)$d['time_slot_id'] : 0;
                if ($tId > 0) {
                    $shift['dandori_map'][$mId][$tId] = [
                        'activity'       => $d['activity'] ?? 'Dandori',
                        'product_id'     => (int)$d['product_id'],
                        'dandori_minute' => (int)($d['dandori_minute'] ?? 0),
                    ];
                }
            }

            // items (plan)
            $rawItems = $db->table('die_casting_production dcp')
                ->select('
                    dcp.id AS dcp_id,
                    dcp.machine_id,
                    m.machine_code,
                    dcp.product_id,
                    p.part_no,
                    p.part_prod,
                    p.part_name,
                    IFNULL(p.weight_die_casting, 0) AS weight_dc,
                    IFNULL(p.weight_runner, 0) AS weight_runner,
                    IFNULL(p.cavity, 1) AS cavity,
                    dcp.qty_p,
                    dcp.end_time_slot_id,
                    dcp.active_slot_ids,
                    dcp.slot_custom_times
                ')
                ->join('machines m', 'm.id = dcp.machine_id')
                ->join('products p', 'p.id = dcp.product_id')
                ->where('dcp.production_date', $date)
                ->where('dcp.shift_id', $shift['id'])
                ->orderBy('m.line_position', 'ASC')
                ->get()->getResultArray();

            $shift['items'] = [];
            foreach ($rawItems as $ritem) {
                $mId = (int)$ritem['machine_id'];
                $pId = (int)$ritem['product_id'];
                // Include baris yang punya target, atau yang merupakan produk baru dari dandori
                $hasDandoriForProduct = false;
                foreach (($shift['dandori_map'][$mId] ?? []) as $dSlotId => $dInfo) {
                    if ($dInfo['product_id'] === $pId) { $hasDandoriForProduct = true; break; }
                }
                if ($ritem['qty_p'] > 0 || $hasDandoriForProduct) {
                    $shift['items'][] = $ritem;
                }
            }

            // weight_dc_map: product_id => weight_die_casting (gram)
            $shift['weight_dc_map'] = [];
            $shift['weight_run_map'] = [];
            $shift['cavity_map'] = [];
            foreach ($shift['items'] as $it) {
                $pid = (int)$it['product_id'];
                $shift['weight_dc_map'][$pid] = (float)($it['weight_dc'] ?? 0);
                $shift['weight_run_map'][$pid] = (float)($it['weight_runner'] ?? 0);
                $shift['cavity_map'][$pid] = (int)($it['cavity'] ?? 1);
                if ($shift['cavity_map'][$pid] <= 0) $shift['cavity_map'][$pid] = 1; // prevent division by zero
            }

            // hourly map
            $hourly = $db->table('die_casting_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['hourly_map'] = [];
            $hourlyIds = [];
            foreach ($hourly as $h) {
                $mid = (int)$h['machine_id'];
                $pid = (int)$h['product_id'];
                $ts  = (int)$h['time_slot_id'];
                $shift['hourly_map'][$mid][$pid][$ts] = $h;
                $hourlyIds[] = (int)$h['id'];
            }

            // NG detail map
            $shift['ng_detail_map'] = [];
            if ($db->tableExists('die_casting_hourly_ng_details') && !empty($hourlyIds)) {
                $details = $db->table('die_casting_hourly_ng_details d')
                    ->select('d.hourly_id, d.ng_category_id, d.qty, nc.ng_code, nc.ng_name')
                    ->join('ng_categories nc', 'nc.id = d.ng_category_id', 'inner')
                    ->whereIn('d.hourly_id', $hourlyIds)
                    ->orderBy('nc.ng_code', 'ASC')
                    ->get()->getResultArray();

                $hourlyIndex = [];
                foreach ($hourly as $h) {
                    $hourlyIndex[(int)$h['id']] = [
                        'machine_id' => (int)$h['machine_id'],
                        'product_id' => (int)$h['product_id'],
                        'time_slot_id' => (int)$h['time_slot_id'],
                    ];
                }

                foreach ($details as $d) {
                    $hid = (int)$d['hourly_id'];
                    if (!isset($hourlyIndex[$hid])) continue;

                    $mid = $hourlyIndex[$hid]['machine_id'];
                    $pid = $hourlyIndex[$hid]['product_id'];
                    $ts  = $hourlyIndex[$hid]['time_slot_id'];

                    $shift['ng_detail_map'][$mid][$pid][$ts][] = [
                        'ng_category_id' => (int)$d['ng_category_id'],
                        'qty' => (int)$d['qty'],
                        'ng_code' => (int)$d['ng_code'],
                        'ng_name' => (string)$d['ng_name'],
                    ];
                }
            }

            // Downtime detail map
            $shift['dt_detail_map'] = [];
            if ($db->tableExists('die_casting_hourly_downtime_details') && !empty($hourlyIds)) {
                $dtDetails = $db->table('die_casting_hourly_downtime_details d')
                    ->select('d.hourly_id, d.downtime_category_id, d.downtime_minute, dc.downtime_name')
                    ->join('downtime_categories dc', 'dc.id = d.downtime_category_id', 'left')
                    ->whereIn('d.hourly_id', $hourlyIds)
                    ->get()->getResultArray();

                foreach ($dtDetails as $d) {
                    $hid = (int)$d['hourly_id'];
                    if (!isset($hourlyIndex[$hid])) continue;

                    $mid = $hourlyIndex[$hid]['machine_id'];
                    $pid = $hourlyIndex[$hid]['product_id'];
                    $ts  = $hourlyIndex[$hid]['time_slot_id'];

                    $catId = (int)$d['downtime_category_id'];
                    if ($catId === 0) $catId = -1; // -1 represents Dandori in the UI

                    $shift['dt_detail_map'][$mid][$pid][$ts][] = [
                        'downtime_category_id' => $catId,
                        'downtime_minute'      => (int)$d['downtime_minute'],
                        'downtime_name'        => $catId === -1 ? 'Dandori' : (string)$d['downtime_name'],
                    ];
                }
            }

            // WIP MAP (0->DC)
            $shift['wip_map'] = [];
            if ($dcProcessId > 0 && $db->tableExists('production_wip')) {
                $select = 'dcp.machine_id, pw.product_id, pw.qty, pw.status';
                if ($wipHasQtyIn)  $select .= ', pw.qty_in';
                if ($wipHasQtyOut) $select .= ', pw.qty_out';
                if ($wipHasStock)  $select .= ', pw.stock';

                $wipRows = $db->table('production_wip pw')
                    ->select($select)
                    ->join('die_casting_production dcp', 'dcp.id = pw.source_id', 'left')
                    ->where('pw.source_table', 'die_casting_production')
                    ->where('pw.production_date', $date)
                    ->where('pw.from_process_id', 0)
                    ->where('pw.to_process_id', $dcProcessId)
                    ->where('dcp.shift_id', $shift['id'])
                    ->get()->getResultArray();

                foreach ($wipRows as $w) {
                    $mid = (int)($w['machine_id'] ?? 0);
                    $pid = (int)($w['product_id'] ?? 0);
                    if (!$mid || !$pid) continue;

                    $shift['wip_map'][$mid][$pid] = [
                        'qty'     => (int)($w['qty'] ?? 0),
                        'status'  => (string)($w['status'] ?? 'WAITING'),
                        'qty_in'  => $wipHasQtyIn  ? (int)($w['qty_in'] ?? 0) : null,
                        'qty_out' => $wipHasQtyOut ? (int)($w['qty_out'] ?? 0) : null,
                        'stock'   => $wipHasStock  ? (int)($w['stock'] ?? 0) : null,
                    ];
                }
            }

            $shift['finish_allowed'] = $isAdmin ? true : $this->isNearLastSlotEnd($db, (int)$shift['id'], $date, 15);
        }
        unset($shift);

        return view('die_casting/daily_production/index', [
            'date'         => $date,
            'operators'    => $operators,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories,
            'downtimes'    => $downtimes,
            'isAdmin'      => $isAdmin,
        ]);
    }

    public function store()
    {
        $db    = db_connect();
        // Support JSON-consolidated items to bypass max_input_vars limit
        $itemsJson = $this->request->getPost('items_json');
        $items = $itemsJson ? json_decode($itemsJson, true) : $this->request->getPost('items');
        $date  = $this->request->getPost('global_date'); 
        $shiftOperators = $this->request->getPost('operators') ?? [];
        $shiftLeaders   = $this->request->getPost('leaders') ?? [];

        if (!$items || !is_array($items) || empty($date)) {
            return redirect()->back()->with('error', 'Data gagal disimpan karena kosong atau terpotong oleh server (max_input_vars).');
        }

        $db->transBegin();

        try {
            $shiftIds = [];

            // Optimalisasi: Tarik semua value downtime sekali saja di luar loop
            $downtimeValues = [];
            if ($db->tableExists('downtime_categories')) {
                $dtRows = $db->table('downtime_categories')->get()->getResultArray();
                foreach ($dtRows as $dt) {
                    $downtimeValues[(int)$dt['id']] = (int)$dt['value'];
                }
            }

            foreach ($items as $key => $row) {
                $parts = explode('_', $key);
                // Key format: date_shiftId_machineId_productId_slotId (5 parts)
                // date itself may contain dashes e.g. 2026-04-16, so we take from the end
                if (count($parts) < 5) continue;

                $timeSlotId = (int)array_pop($parts);
                $productId  = (int)array_pop($parts);
                $machineId  = (int)array_pop($parts);
                $shiftId    = (int)array_pop($parts);
                // remaining is the date

                if ($shiftId <= 0 || $machineId <= 0 || $productId <= 0 || $timeSlotId <= 0) continue;

                $shiftIds[$shiftId] = true;

                $fg = (int)($row['fg'] ?? 0);
                $ng = (int)($row['ng'] ?? 0);
                $target = isset($row['qty_target']) ? (int)$row['qty_target'] : null;
                
                $dtVal = (int)($row['downtime_penalty'] ?? 0);
                $dtId  = null;

                $exist = $db->table('die_casting_hourly')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->where('machine_id', $machineId)
                    ->where('product_id', $productId)
                    ->where('time_slot_id', $timeSlotId)
                    ->get()->getRowArray();

                $opName = $shiftOperators[$shiftId][$machineId][$timeSlotId]
                          ?? $shiftOperators[$shiftId][$machineId]['all']
                          ?? (is_string($shiftOperators[$shiftId][$machineId] ?? null) ? $shiftOperators[$shiftId][$machineId] : null);
                
                $ldName = $shiftLeaders[$shiftId] ?? null;

                if ($exist) {
                    $hourlyId = (int)$exist['id'];
                    $db->table('die_casting_hourly')->where('id', $hourlyId)->update([
                        'qty_fg'               => $fg,
                        'qty_ng'               => $ng,
                        'qty_target'           => $target,
                        'ng_category_id'       => null,
                        'downtime_category_id' => $dtId > 0 ? $dtId : null,
                        'downtime_minute'      => $dtVal,
                        'operator_name'        => $opName,
                        'leader_name'          => $ldName,
                        'updated_at'           => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    if ($fg == 0 && $ng == 0 && $dtId == 0 && $dtVal == 0 && empty($opName) && empty($ldName) && $target === null) continue; 

                    $db->table('die_casting_hourly')->insert([
                        'production_date'      => $date,
                        'shift_id'             => $shiftId,
                        'machine_id'           => $machineId,
                        'product_id'           => $productId,
                        'time_slot_id'         => $timeSlotId,
                        'qty_fg'               => $fg,
                        'qty_ng'               => $ng,
                        'qty_target'           => $target,
                        'ng_category_id'       => null,
                        'downtime_category_id' => $dtId > 0 ? $dtId : null,
                        'downtime_minute'      => $dtVal,
                        'operator_name'        => $opName,
                        'leader_name'          => $ldName,
                        'created_at'           => date('Y-m-d H:i:s'),
                    ]);
                    $hourlyId = (int)$db->insertID();
                }

                $this->saveNgDetails($db, $hourlyId, $row['ng_details'] ?? []);
                $sumNg = $this->sumNgDetail($db, $hourlyId);
                $db->table('die_casting_hourly')->where('id', $hourlyId)->update(['qty_ng' => $sumNg]);

                $this->saveDowntimeDetails($db, $hourlyId, $row['dt_details'] ?? []);
            }

            // Sync updated total targets per shift to schedule
            // PERMINTAAN USER: "seharusnya target per shift tidak berubah apabila target per hournya diganti"
            // Jadi bagian ini di-comment agar qty_p (target per shift) tidak ikut berubah
            /*
            $rowTargets = $this->request->getPost('row_targets') ?? [];
            foreach ($rowTargets as $key => $sumTarget) {
                // key is ShiftId_MachineId_ProductId
                $parts = explode('_', $key);
                if (count($parts) === 3) {
                    $sId = (int)$parts[0];
                    $mId = (int)$parts[1];
                    $pId = (int)$parts[2];
                    $db->table('die_casting_production')
                        ->where('production_date', $date)
                        ->where('shift_id', $sId)
                        ->where('machine_id', $mId)
                        ->where('product_id', $pId)
                        ->update(['qty_p' => (int)$sumTarget]);
                }
            }
            */

            $isAdmin = $this->isAdminSession();

            foreach (array_keys($shiftIds) as $sid) {
                $this->syncDailyScheduleActual($db, $date, (int)$sid, $isAdmin);
            }

            if ($db->transStatus() === false) {
                $dbError = $db->error();
                throw new \Exception('DB Error: ' . ($dbError['message'] ?? 'Unknown Error'));
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Data produksi harian dan downtime berhasil disimpan.');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function saveNgDetails($db, int $hourlyId, $ngDetails): void
    {
        if (!$db->tableExists('die_casting_hourly_ng_details')) return;
        if (!is_array($ngDetails)) $ngDetails = [];

        $db->table('die_casting_hourly_ng_details')->where('hourly_id', $hourlyId)->delete();

        $grouped = [];
        foreach ($ngDetails as $d) {
            $ngId = (int)($d['ng_category_id'] ?? 0);
            $qty  = (int)($d['qty'] ?? 0);
            if ($ngId <= 0 || $qty <= 0) continue;
            
            if (!isset($grouped[$ngId])) $grouped[$ngId] = 0;
            $grouped[$ngId] += $qty;
        }

        $batch = [];
        foreach ($grouped as $ngId => $qty) {
            $batch[] = [
                'hourly_id'      => $hourlyId,
                'ng_category_id' => $ngId,
                'qty'            => $qty,
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($batch)) {
            $db->table('die_casting_hourly_ng_details')->insertBatch($batch);
        }
    }

    private function saveDowntimeDetails($db, int $hourlyId, $dtDetails): void
    {
        if (!$db->tableExists('die_casting_hourly_downtime_details')) return;
        if (!is_array($dtDetails)) $dtDetails = [];

        $db->table('die_casting_hourly_downtime_details')->where('hourly_id', $hourlyId)->delete();

        $batch = [];
        foreach ($dtDetails as $d) {
            $dtId = (int)($d['downtime_category_id'] ?? 0);
            $mins = (int)($d['downtime_minute'] ?? 0);
            if ($dtId <= 0 && $dtId !== -1) continue; // -1 for Dandori (special UI handling)
            
            $batch[] = [
                'hourly_id'            => $hourlyId,
                'downtime_category_id' => $dtId === -1 ? 0 : $dtId, // Store 0 for Dandori internally or keep it flexible
                'downtime_minute'      => $mins,
                'created_at'           => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($batch)) {
            $db->table('die_casting_hourly_downtime_details')->insertBatch($batch);
        }
    }

    private function sumNgDetail($db, int $hourlyId): int
    {
        if (!$db->tableExists('die_casting_hourly_ng_details')) return 0;
        $row = $db->table('die_casting_hourly_ng_details')->select('SUM(qty) AS s')->where('hourly_id', $hourlyId)->get()->getRowArray();
        return (int)($row['s'] ?? 0);
    }

    // ==============================================================================
    // BAGIAN FINISH SHIFT DLL
    // ==============================================================================
    public function finishShift()
    {
        $db      = db_connect();
        $date    = (string)$this->request->getPost('date');
        $shiftId = (int)$this->request->getPost('shift_id');

        if ($date === '' || $shiftId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'Data tidak lengkap']);
        }

        if (!$this->isDcShift($db, $shiftId)) {
            return $this->response->setJSON(['status' => false, 'message' => 'Finish Shift hanya untuk Die Casting']);
        }

        $isAdmin = $this->isAdminSession();

        if (!$isAdmin && !$this->isNearLastSlotEnd($db, $shiftId, $date, 15)) {
            return $this->response->setJSON(['status' => false, 'message' => 'Tombol aktif hanya mendekati jam terakhir shift']);
        }

        $db->transBegin();
        try {
            $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
            if ($dcProcessId <= 0) throw new \Exception('Process Die Casting tidak ditemukan');

            $this->syncDailyScheduleActual($db, $date, $shiftId, $isAdmin);
            $shiftCode = (int)$db->table('shifts')->select('shift_code')->where('id', $shiftId)->get()->getRow('shift_code');
            $dcShiftIds = $this->getDcShiftIds($db, $date);
            $idx = array_search($shiftId, $dcShiftIds, true);
            if ($idx === false) throw new \Exception('Shift DC tidak valid');

            $nextShiftId = (int)($dcShiftIds[$idx + 1] ?? 0);

            if ($nextShiftId > 0) {
                // Not the last shift of the day
                $moved = $this->transferDcStockToNextDcShift($db, $date, $shiftId, $nextShiftId, $dcProcessId, $isAdmin);
                $this->zeroOutDcShift($db, $date, $shiftId, $dcProcessId);
                $this->syncDailyScheduleActual($db, $date, $nextShiftId, $isAdmin);
                $this->markDcShiftCompleted($db, $date, $shiftId);

                $db->transCommit();
                return $this->response->setJSON([
                    'status' => true,
                    'message' => "Finish Shift OK. Stock moved: {$moved} & shift cleared",
                    'count' => $moved
                ]);
            } else {
                // The last shift of the day -> close completely
                foreach ($dcShiftIds as $sid) {
                    $this->syncDailyScheduleActual($db, $date, (int)$sid, $isAdmin);
                }
                $count = $this->finishShiftTransferFlowShift3Only($db, $date, $dcProcessId, $shiftId, $isAdmin);
                $this->zeroOutDcShift($db, $date, $shiftId, $dcProcessId);
                $this->markDcShiftCompleted($db, $date, $shiftId);

                $db->transCommit();
                return $this->response->setJSON([
                    'status' => true,
                    'message' => "Finish Shift Akhir Hari OK. Transferred: {$count} & shift cleared",
                    'count' => $count
                ]);
            }

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    private function zeroOutDcShift($db, string $date, int $shiftId, int $dcProcessId): void
    {
        $now = date('Y-m-d H:i:s');
        $hourlyRows = $db->table('die_casting_hourly')->select('id')->where('production_date', $date)->where('shift_id', $shiftId)->get()->getResultArray();
        $hourlyIds = array_map(fn($r) => (int)$r['id'], $hourlyRows);
        if (!empty($hourlyIds) && $db->tableExists('die_casting_hourly_ng_details')) {
            $db->table('die_casting_hourly_ng_details')->whereIn('hourly_id', $hourlyIds)->delete();
        }
        $db->table('die_casting_hourly')->where('production_date', $date)->where('shift_id', $shiftId)->update([
                'qty_fg' => 0, 'qty_ng' => 0, 'ng_category_id' => null, 'updated_at' => $now,
            ]);
        $db->table('die_casting_production')->where('production_date', $date)->where('shift_id', $shiftId)->update([
                'qty_a'  => 0, 'qty_ng' => 0,
            ]);

        if ($db->tableExists('production_wip')) {
            $hasQtyIn  = $db->fieldExists('qty_in', 'production_wip');
            $hasQtyOut = $db->fieldExists('qty_out', 'production_wip');
            $hasStock  = $db->fieldExists('stock', 'production_wip');
            $hasUpd    = $db->fieldExists('updated_at', 'production_wip');
            $dcpIds = $db->table('die_casting_production')->select('id')->where('production_date', $date)->where('shift_id', $shiftId)->get()->getResultArray();
            $ids = array_map(fn($r) => (int)$r['id'], $dcpIds);

            if (!empty($ids)) {
                $upd = ['status' => 'DONE'];
                if ($hasQtyIn)  $upd['qty_in']  = 0;
                if ($hasQtyOut) $upd['qty_out'] = 0;
                if ($hasStock)  $upd['stock']   = 0;
                if ($hasUpd)    $upd['updated_at'] = $now;

                $db->table('production_wip')->where('production_date', $date)->where('from_process_id', 0)
                    ->where('to_process_id', $dcProcessId)->where('source_table', 'die_casting_production')->whereIn('source_id', $ids)->update($upd);
            }
        }
    }

    private function transferDcStockToNextDcShift($db, string $date, int $fromShiftId, int $toShiftId, int $dcProcessId, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
        if (!$hasStock) return 0;

        $now = date('Y-m-d H:i:s');
        $fromDcp = $db->table('die_casting_production')->select('id, machine_id, product_id, qty_p')->where('production_date', $date)->where('shift_id', $fromShiftId)->where('product_id >', 0)->get()->getResultArray();
        if (!$fromDcp) return 0;

        $toDcpRows = $db->table('die_casting_production')->select('id, machine_id, product_id, qty_p')->where('production_date', $date)->where('shift_id', $toShiftId)->where('product_id >', 0)->get()->getResultArray();
        $toMap = [];
        foreach ($toDcpRows as $t) {
            $toMap[(int)$t['machine_id']][(int)$t['product_id']] = ['dcp_id' => (int)$t['id'], 'qty_p'  => (int)($t['qty_p'] ?? 0)];
        }

        $moved = 0;
        foreach ($fromDcp as $dcp) {
            $sourceId  = (int)$dcp['id'];
            $machineId = (int)$dcp['machine_id'];
            $productId = (int)$dcp['product_id'];
            if (!$sourceId || !$machineId || !$productId) continue;

            $whereA = ['production_date' => $date, 'product_id' => $productId, 'from_process_id' => 0, 'to_process_id' => $dcProcessId, 'source_table' => 'die_casting_production', 'source_id' => $sourceId];
            $existA = $db->table('production_wip')->where($whereA)->get()->getRowArray();
            if (!$existA) continue;
            if (!$forceAdmin && strtoupper((string)($existA['status'] ?? '')) === 'DONE') continue;

            $stockNow = (int)($existA['stock'] ?? 0);
            if ($stockNow <= 0) continue;

            $targetInfo = $toMap[$machineId][$productId] ?? null;
            if (!$targetInfo) { $moved += $stockNow; continue; }

            $targetDcpId = (int)$targetInfo['dcp_id'];
            $toPlan      = (int)$targetInfo['qty_p'];
            $whereB = ['production_date' => $date, 'product_id' => $productId, 'from_process_id' => 0, 'to_process_id' => $dcProcessId, 'source_table' => 'die_casting_production', 'source_id' => $targetDcpId];
            $existB = $db->table('production_wip')->where($whereB)->get()->getRowArray();

            if ($existB) {
                $newStock = (int)($existB['stock'] ?? 0) + $stockNow;
                $updB = ['stock' => $newStock, 'status' => 'WAITING'];
                if ($hasQtyIn)  $updB['qty_in']  = max(0, $toPlan - $newStock);
                if ($hasQtyOut) $updB['qty_out'] = 0;
                if ($hasUpdatedAt) $updB['updated_at'] = $now;
                $db->table('production_wip')->where('id', (int)$existB['id'])->update($updB);
            } else {
                $insB = $whereB + ['qty' => $toPlan, 'status' => 'WAITING', 'stock' => $stockNow];
                if ($hasQtyIn)  $insB['qty_in']  = max(0, $toPlan - $stockNow);
                if ($hasQtyOut) $insB['qty_out'] = 0;
                if ($hasCreatedAt) $insB['created_at'] = $now;
                if ($hasUpdatedAt) $insB['updated_at'] = $now;
                $db->table('production_wip')->insert($insB);
            }
            $moved += $stockNow;
        }
        return $moved;
    }

    private function finishShiftTransferFlowShift3Only($db, string $date, int $dcProcessId, int $shift3Id, bool $forceAdmin = false): int
    {
        if (!$db->tableExists('production_wip')) return 0;
        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');
        $now = date('Y-m-d H:i:s');

        $dcpRows = $db->table('die_casting_production')->select('id, product_id, qty_p, qty_a')->where('production_date', $date)->where('shift_id', $shift3Id)->where('product_id >', 0)->get()->getResultArray();
        if (!$dcpRows) return 0;

        $processed = 0;
        foreach ($dcpRows as $dcp) {
            $sourceId  = (int)$dcp['id'];
            $productId = (int)$dcp['product_id'];
            $qtyPlan   = (int)($dcp['qty_p'] ?? 0);
            $qtyA      = (int)($dcp['qty_a'] ?? 0);

            $whereA = ['production_date' => $date, 'product_id' => $productId, 'from_process_id' => 0, 'to_process_id' => $dcProcessId, 'source_table' => 'die_casting_production', 'source_id' => $sourceId];
            $existA = $db->table('production_wip')->where($whereA)->get()->getRowArray();
            if (!$existA) continue;
            if (!$forceAdmin && strtoupper((string)($existA['status'] ?? '')) === 'DONE') { $processed++; continue; }

            $stockNow = $hasStock ? (int)($existA['stock'] ?? 0) : 0;
            $transferQty = max($stockNow, $qtyA);
            if ($transferQty <= 0) { $processed++; continue; }

            $nextProcessId = $this->resolveNextProcessByFlow($db, $productId, $dcProcessId) ?? 0;
            $updA = ['qty' => $qtyPlan > 0 ? $qtyPlan : $transferQty, 'status' => 'DONE'];
            if ($hasQtyIn)  $updA['qty_in']  = 0;
            if ($hasQtyOut) $updA['qty_out'] = $transferQty;
            if ($hasStock)  $updA['stock']   = 0;
            if ($hasUpdatedAt) $updA['updated_at'] = $now;
            $db->table('production_wip')->where('id', (int)$existA['id'])->update($updA);

            if ($nextProcessId > 0) {
                $whereB = ['production_date' => $date, 'product_id' => $productId, 'from_process_id' => $dcProcessId, 'to_process_id' => $nextProcessId, 'source_table' => 'die_casting_production', 'source_id' => $sourceId];
                $existB = $db->table('production_wip')->where($whereB)->get()->getRowArray();
                $payloadB = $whereB + ['qty' => $transferQty, 'status' => 'WAITING'];
                if ($hasQtyIn)  $payloadB['qty_in']  = $transferQty;
                if ($hasQtyOut) $payloadB['qty_out'] = 0;
                if ($hasStock)  $payloadB['stock']   = $transferQty;
                if ($hasUpdatedAt) $payloadB['updated_at'] = $now;

                if ($existB) {
                    if ($forceAdmin || strtoupper((string)($existB['status'] ?? '')) !== 'DONE') $db->table('production_wip')->where('id', (int)$existB['id'])->update($payloadB);
                } else {
                    if ($hasCreatedAt) $payloadB['created_at'] = $now;
                    $db->table('production_wip')->insert($payloadB);
                }
            }
            $processed++;
        }
        return $processed;
    }

    private function syncDailyScheduleActual($db, string $date, int $shiftId, bool $forceAdmin = false): void
    {
        $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
        if ($dcProcessId <= 0) return;

        $dcpRows = $db->table('die_casting_production')->select('id, machine_id, product_id, qty_p')->where('production_date', $date)->where('shift_id', $shiftId)->get()->getResultArray();
        if (!$dcpRows) return;

        foreach ($dcpRows as $dcp) {
            $dcpId     = (int)$dcp['id'];
            $machineId = (int)$dcp['machine_id'];
            $productId = (int)$dcp['product_id'];
            $qtyPlan   = (int)($dcp['qty_p'] ?? 0);
            if ($dcpId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $sum = $db->table('die_casting_hourly')->select('SUM(qty_fg) AS total_fg, SUM(qty_ng) AS total_ng')->where('production_date', $date)->where('shift_id', $shiftId)->where('machine_id', $machineId)->where('product_id', $productId)->get()->getRowArray();
            $fg = (int)($sum['total_fg'] ?? 0);
            $ng = (int)($sum['total_ng'] ?? 0);

            $db->table('die_casting_production')->where('id', $dcpId)->update(['qty_a' => $fg, 'qty_ng' => $ng]);
            $this->upsertWipDcStageRealtime($db, $date, $dcpId, $productId, $qtyPlan, $fg, $dcProcessId, $forceAdmin);
        }
    }

    private function upsertWipDcStageRealtime($db, string $date, int $sourceId, int $productId, int $qtyPlan, int $totalFg, int $dcProcessId, bool $forceAdmin = false): void
    {
        if (!$db->tableExists('production_wip')) return;
        $hasQtyIn     = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut    = $db->fieldExists('qty_out', 'production_wip');
        $hasStock     = $db->fieldExists('stock', 'production_wip');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'production_wip');
        $hasCreatedAt = $db->fieldExists('created_at', 'production_wip');

        $where = ['production_date' => $date, 'product_id' => $productId, 'from_process_id' => 0, 'to_process_id' => $dcProcessId, 'source_table' => 'die_casting_production', 'source_id' => $sourceId];
        $exist = $db->table('production_wip')->where($where)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');

        if ($exist && strtoupper((string)($exist['status'] ?? '')) === 'DONE' && !$forceAdmin) return;
        $existingStock = $exist ? (int)($exist['stock'] ?? 0) : 0;
        $stockVal = $hasStock ? max($existingStock, $totalFg) : 0;
        $remaining = max(0, $qtyPlan - $stockVal);

        $payload = $where + ['qty' => $qtyPlan, 'status' => 'WAITING'];
        if ($hasQtyIn)  $payload['qty_in']  = $remaining;
        if ($hasQtyOut) $payload['qty_out'] = 0;
        if ($hasStock)  $payload['stock']   = $stockVal;
        if ($hasUpdatedAt) $payload['updated_at'] = $now;

        if ($exist) {
            $db->table('production_wip')->where('id', (int)$exist['id'])->update($payload);
        } else {
            if ($hasCreatedAt) $payload['created_at'] = $now;
            $db->table('production_wip')->insert($payload);
        }
    }

    private function markDcShiftCompleted($db, string $date, int $shiftId): void
    {
        if ($db->fieldExists('is_completed', 'die_casting_production')) {
            $db->table('die_casting_production')->where('production_date', $date)->where('shift_id', $shiftId)->update(['is_completed' => 1]);
        }
        if ($db->tableExists('daily_schedules') && $db->fieldExists('is_completed', 'daily_schedules')) {
            $dcProcessId = $this->getProcessIdByName($db, 'Die Casting');
            $db->table('daily_schedules')->where('schedule_date', $date)->where('process_id', $dcProcessId)->where('shift_id', $shiftId)->update(['is_completed' => 1]);
        }
    }

    private function isNearLastSlotEnd($db, int $shiftId, string $date, int $minutesBeforeEnd = 15): bool
    {
        date_default_timezone_set('Asia/Jakarta');
        $slots = $db->table('shift_time_slots sts')->select('ts.time_start, ts.time_end')->join('time_slots ts', 'ts.id = sts.time_slot_id')->where('sts.shift_id', $shiftId)->orderBy('ts.time_start', 'ASC')->get()->getResultArray();
        if (!$slots) return false;
        $firstStart = $slots[0]['time_start'];
        $lastEnd    = $slots[count($slots) - 1]['time_end'];

        $startDT = strtotime($date . ' ' . $firstStart);
        $endDT   = strtotime($date . ' ' . $lastEnd);
        if ($endDT <= $startDT) $endDT += 86400;

        $now = time();
        $windowStart = $endDT - ($minutesBeforeEnd * 60);
        return $now >= $windowStart && $now <= $endDT;
    }

    private function getDcShiftIds($db, string $date): array
    {
        $rows = $db->table('shifts')->select('id')
               ->where('is_active', 1)
               ->where('day_group', $this->getDayGroup($date))
               ->like('shift_name', 'DC')
               ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
               ->get()->getResultArray();
        return array_values(array_map(fn($r) => (int)$r['id'], $rows));
    }

    private function isDcShift($db, int $shiftId): bool
    {
        $shift = $db->table('shifts')->select('shift_name, is_active')->where('id', $shiftId)->get()->getRowArray();
        if (!$shift) return false;
        if ((int)($shift['is_active'] ?? 0) !== 1) return false;
        return (stripos((string)($shift['shift_name'] ?? ''), 'DC') !== false);
    }

    private function isAdminSession(): bool
    {
        $role = strtoupper((string)(session()->get('role') ?? ''));
        return $role === 'ADMIN';
    }

    private function resolveNextProcessByFlow($db, int $productId, int $fromProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;
        $flows = $db->table('product_process_flows')->select('process_id, sequence')->where('product_id', $productId)->where('is_active', 1)->orderBy('sequence', 'ASC')->get()->getResultArray();
        if (!$flows) return null;

        $idx = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === (int)$fromProcessId) { $idx = $i; break; }
        }
        if ($idx === null) return isset($flows[1]) ? (int)$flows[1]['process_id'] : null;
        return isset($flows[$idx + 1]) ? (int)$flows[$idx + 1]['process_id'] : null;
    }

    private function getProcessIdByName($db, string $processName): int
    {
        $row = $db->table('production_processes')->select('id')->where('process_name', $processName)->get()->getRowArray();
        return (int)($row['id'] ?? 0);
    }

    public function endDandori()
    {
        $db = db_connect();
        $date       = $this->request->getPost('date');
        $shiftId    = (int)$this->request->getPost('shift_id');
        $machineId  = (int)$this->request->getPost('machine_id');
        $timeSlotId = (int)$this->request->getPost('time_slot_id');

        if (!$date || !$shiftId || !$machineId || !$timeSlotId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Invalid parameters']);
        }

        $now = time();

        $dandoriRecord = $db->table('die_casting_dandori')
            ->where('dandori_date', $date)
            ->where('shift_id', $shiftId)
            ->where('machine_id', $machineId)
            ->where('time_slot_id', $timeSlotId)
            ->get()->getRowArray();

        if (!$dandoriRecord) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Dandori record not found']);
        }

        $slot = $db->table('time_slots')->where('id', $timeSlotId)->get()->getRowArray();
        if (!$slot) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Time slot not found']);
        }

        $prodDateISO = $date;
        $startStr = substr((string)($slot['time_start'] ?? ''), 0, 5);
        $startDate = date('Y-m-d H:i:s', strtotime("{$prodDateISO} {$startStr}:00"));
        
        $startHour = (int)date('H', strtotime($startDate));
        if ($startHour >= 0 && $startHour < 7) {
            $startDate = date('Y-m-d H:i:s', strtotime($startDate . ' +1 day'));
        }

        $startTs = strtotime($startDate);
        if ($now <= $startTs) {
            $diffMins = 0;
        } else {
            $diffMins = (int)floor(($now - $startTs) / 60);
        }

        // Limit the actual dandori minutes to the original scheduled minutes
        $originalMins = (int)($dandoriRecord['dandori_minute'] ?? 0);
        if ($diffMins > $originalMins) $diffMins = $originalMins;

        $db->table('die_casting_dandori')
            ->where('id', $dandoriRecord['id'])
            ->update(['dandori_minute' => $diffMins]);

        return $this->response->setJSON(['ok' => true, 'dandori_minute' => $diffMins, 'msg' => 'Dandori ended']);
    }
}