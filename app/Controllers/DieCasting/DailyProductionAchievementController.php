<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DailyProductionAchievementController extends BaseController
{
    /* =========================
     * Helper: get process id DC
     * ========================= */
    private function getDcProcessId($db): int
    {
        // prioritas: process_code = DC
        if ($db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_code', 'DC')
                ->get()->getRowArray();
            if ($row) return (int)$row['id'];
        }

        // fallback: process_name = Die Casting
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Die Casting')
            ->get()->getRowArray();

        return (int)($row['id'] ?? 0);
    }

    /* =========================
     * Helper: resolve next process by flow
     * ========================= */
    private function resolveNextProcessByFlow($db, int $productId, int $fromProcessId): ?int
    {
        if (!$db->tableExists('product_process_flows')) return null;

        $flows = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();

        if (!$flows) return null;

        $idx = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === (int)$fromProcessId) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) return null;

        return isset($flows[$idx + 1]) ? (int)$flows[$idx + 1]['process_id'] : null;
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $tz  = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);

        $dcProcessId = $this->getDcProcessId($db);
        if ($dcProcessId <= 0) $dcProcessId = 1;

        // ng categories DC
        $ngCategories = $db->table('ng_categories')
            ->where('process_name', 'Die Casting')
            ->orderBy('ng_code')
            ->get()->getResultArray();

        // shifts DC
        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        $allProductIds = [];
        $allDcpIds     = [];

        foreach ($shifts as &$shift) {

            // slots shift
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id AS time_slot_id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start', 'ASC')
                ->get()->getResultArray();

            $shift['slots'] = $slots;

            $shiftStart = $slots[0]['time_start'] ?? null;
            $shiftEnd   = null;

            if (!empty($slots)) {
                $last = end($slots);
                $shiftEnd = $last['time_end'] ?? null;
                reset($slots);
            }

            // ===== rule: editable hanya 1 jam setelah shift berakhir =====
            $shift['isEditable']   = false;
            $shift['editDeadline'] = null;

            if ($shiftStart && $shiftEnd) {
                $startDt = new \DateTime($date . ' ' . $shiftStart, $tz);
                $endDt   = new \DateTime($date . ' ' . $shiftEnd, $tz);

                // shift lewat midnight
                if ($endDt <= $startDt) {
                    $endDt->modify('+1 day');
                }

                $deadline = (clone $endDt)->modify('+1 hour');

                $shift['editDeadline'] = $deadline->format('Y-m-d H:i:s');
                $shift['isEditable']   = ($now >= $endDt && $now <= $deadline);
            }

            // data header + sum hourly
            $items = $db->table('die_casting_production dcp')
                ->select("
                    dcp.id AS production_id,
                    dcp.machine_id,
                    m.machine_code,
                    dcp.product_id,
                    p.part_no,
                    COALESCE(dcp.part_label, p.part_name) AS part_name,
                    dcp.qty_p AS target,
                    dcp.qty_a AS qty_a,
                    dcp.qty_ng AS qty_ng,
                    dcp.qty_ng_blank AS qty_ng_blank,
                    IFNULL(SUM(dh.qty_fg),0) AS sum_hourly_fg,
                    IFNULL(SUM(dh.qty_ng),0) AS sum_hourly_ng,
                    MAX(dh.ng_category_id) AS ng_category_id,
                    MAX(dh.downtime_category_id) AS downtime_category_id,
                    IFNULL(SUM(dh.downtime_minute),0) AS downtime
                ")
                ->join('machines m', 'm.id = dcp.machine_id', 'left')
                ->join('products p', 'p.id = dcp.product_id')
                ->join(
                    'die_casting_hourly dh',
                    'dh.production_date = dcp.production_date
                     AND dh.shift_id = dcp.shift_id
                     AND dh.machine_id = dcp.machine_id
                     AND dh.product_id = dcp.product_id',
                    'left'
                )
                ->where('dcp.production_date', $date)
                ->where('dcp.shift_id', $shift['id'])
                ->where('dcp.qty_p >', 0)
                ->groupBy('dcp.id, dcp.machine_id, dcp.product_id, dcp.qty_p, dcp.qty_a, dcp.qty_ng, dcp.qty_ng_blank, m.machine_code, p.part_no, part_name')
                ->orderBy('m.line_position', 'ASC')
                ->get()->getResultArray();

            foreach ($items as &$it) {
                // tampilkan actual: pakai header kalau sudah ada, fallback sum hourly
                $it['fg_display'] = ((int)$it['qty_a'] > 0) ? (int)$it['qty_a'] : (int)$it['sum_hourly_fg'];
                $it['ng_display'] = ((int)$it['qty_ng'] > 0) ? (int)$it['qty_ng'] : (int)$it['sum_hourly_ng'];
                $it['ng_blank_display'] = (int)($it['qty_ng_blank'] ?? 0);

                $allProductIds[] = (int)$it['product_id'];
                $allDcpIds[]     = (int)$it['production_id'];
            }
            unset($it);

            $shift['items'] = $items;

            // NG detail map untuk shift ini
            $shift['ng_detail_map'] = [];
            $shift['dt_detail_map'] = [];
            if ($db->tableExists('die_casting_hourly_ng_details') && !empty($items)) {
                $hourlyRows = $db->table('die_casting_hourly')
                                 ->select('id, machine_id, product_id, time_slot_id, remark, downtime_category_id, downtime_minute')
                                 ->where('production_date', $date)
                                 ->where('shift_id', $shift['id'])
                                 ->get()->getResultArray();
                
                $hourlyIds = array_column($hourlyRows, 'id');
                
                // Remarks Map
                $shift['remark_map'] = [];
                foreach ($hourlyRows as $h) {
                    $m = (int)$h['machine_id'];
                    $p = (int)$h['product_id'];
                    $t = (int)$h['time_slot_id'];
                    $shift['remark_map'][$m][$p][$t] = $h['remark'];

                    // DT map: accumulate per machine/product/category
                    $dtCatId = (int)($h['downtime_category_id'] ?? 0);
                    $dtMins  = (int)($h['downtime_minute'] ?? 0);
                    if ($dtCatId > 0 && $dtMins > 0) {
                        if (!isset($dtSumsShift[$m][$p][$dtCatId])) $dtSumsShift[$m][$p][$dtCatId] = 0;
                        $dtSumsShift[$m][$p][$dtCatId] += $dtMins;
                    }
                }

                // Build dt_detail_map
                $dtSumsShift = $dtSumsShift ?? [];
                foreach ($dtSumsShift as $m => $prodArr) {
                    foreach ($prodArr as $p => $catArr) {
                        foreach ($catArr as $catId => $mins) {
                            $shift['dt_detail_map'][$m][$p][] = ['downtime_category_id' => $catId, 'downtime_minute' => $mins];
                        }
                    }
                }
                $dtSumsShift = [];
                
                if (!empty($hourlyIds)) {
                    $details = $db->table('die_casting_hourly_ng_details d')
                        ->select('d.hourly_id, d.ng_category_id, d.qty')
                        ->whereIn('d.hourly_id', $hourlyIds)
                        ->get()->getResultArray();

                    $hourlyIndex = [];
                    foreach ($hourlyRows as $h) {
                        $hourlyIndex[(int)$h['id']] = [
                            'm' => (int)$h['machine_id'],
                            'p' => (int)$h['product_id']
                        ];
                    }

                    $shiftNgSums = [];
                    foreach ($details as $d) {
                        $hid = (int)$d['hourly_id'];
                        $idx = $hourlyIndex[$hid] ?? null;
                        if (!$idx) continue;
                        $m = $idx['m'];
                        $p = $idx['p'];
                        $c = (int)$d['ng_category_id'];
                        
                        if (!isset($shiftNgSums[$m][$p][$c])) {
                            $shiftNgSums[$m][$p][$c] = 0;
                        }
                        $shiftNgSums[$m][$p][$c] += (int)$d['qty'];
                    }

                    foreach ($shiftNgSums as $m => $prodArr) {
                        foreach ($prodArr as $p => $catArr) {
                            foreach ($catArr as $c => $qty) {
                                $shift['ng_detail_map'][$m][$p][] = [
                                    'ng_category_id' => $c,
                                    'qty' => $qty
                                ];
                            }
                        }
                    }
                }
            }
        }
        unset($shift);


        $allProductIds = array_values(array_unique($allProductIds));
        $allDcpIds     = array_values(array_unique($allDcpIds));

        // ===== NEXT PROCESS MAP =====
        $nextProcessMap = [];
        if (!empty($allProductIds)) {
            $flows = $db->table('product_process_flows ppf')
                ->select('ppf.product_id, ppf.process_id, ppf.sequence, pp.process_name')
                ->join('production_processes pp', 'pp.id = ppf.process_id')
                ->whereIn('ppf.product_id', $allProductIds)
                ->where('ppf.is_active', 1)
                ->orderBy('ppf.product_id', 'ASC')
                ->orderBy('ppf.sequence', 'ASC')
                ->get()->getResultArray();

            $byProduct = [];
            foreach ($flows as $f) {
                $pid = (int)$f['product_id'];
                $byProduct[$pid][] = [
                    'process_id'   => (int)$f['process_id'],
                    'process_name' => $f['process_name'],
                    'sequence'     => (int)$f['sequence'],
                ];
            }

            foreach ($byProduct as $pid => $list) {
                $dcSeq = null;
                foreach ($list as $row) {
                    if ((int)$row['process_id'] === $dcProcessId) {
                        $dcSeq = (int)$row['sequence'];
                        break;
                    }
                }

                $next = null;
                if ($dcSeq !== null) {
                    foreach ($list as $row) {
                        if ((int)$row['sequence'] === ($dcSeq + 1)) {
                            $next = $row;
                            break;
                        }
                    }
                }

                $nextProcessMap[$pid] = [
                    'to_process_id'   => $next['process_id'] ?? null,
                    'to_process_name' => $next['process_name'] ?? '-'
                ];
            }
        }

        // ===== WIP MAP =====
        $wipMap = [];
        if (!empty($allDcpIds)) {
            $wips = $db->table('production_wip')
                ->select('source_id, qty, status')
                ->where('source_table', 'die_casting_production')
                ->whereIn('source_id', $allDcpIds)
                ->get()->getResultArray();

            foreach ($wips as $w) {
                $wipMap[(int)$w['source_id']] = [
                    'qty'    => (int)($w['qty'] ?? 0),
                    'status' => $w['status'] ?? 'WAITING'
                ];
            }
        }

        // tempelkan flow+wip ke items
        foreach ($shifts as &$shift) {
            foreach ($shift['items'] as &$it) {
                $pid   = (int)$it['product_id'];
                $dcpId = (int)$it['production_id'];

                $it['next_process_name'] = $nextProcessMap[$pid]['to_process_name'] ?? '-';
                $it['next_process_id']   = $nextProcessMap[$pid]['to_process_id'] ?? null;

                $it['wip_qty']    = $wipMap[$dcpId]['qty'] ?? 0;
                $it['wip_status'] = $wipMap[$dcpId]['status'] ?? 'WAITING';
            }
            unset($it);
        }
        unset($shift);

        $role = (string)(session()->get('role') ?? '');
        $isAdmin = (strtoupper($role) === 'ADMIN');

        $downtimes = [];
        if ($db->tableExists('downtime_categories')) {
            $downtimes = $db->table('downtime_categories')
                ->where('process_id', $dcProcessId)
                ->where('is_active', 1)
                ->orderBy('downtime_name', 'ASC')
                ->get()->getResultArray();
        }

        return view('die_casting/daily_production_achievement/index', [
            'date'         => $date,
            'shifts'       => $shifts,
            'ngCategories' => $ngCategories,
            'downtimes'    => $downtimes,
            'isAdmin'      => $isAdmin
        ]);
    }

    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data kosong / terpotong');
        }

        $tz  = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);

        $dcProcessId = $this->getDcProcessId($db);
        if ($dcProcessId <= 0) $dcProcessId = 1;

        // optional columns on production_wip
        $hasQtyIn    = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut   = $db->fieldExists('qty_out', 'production_wip');
        $hasStock    = $db->fieldExists('stock', 'production_wip');
        $hasUpdated  = $db->fieldExists('updated_at', 'production_wip');

        // validasi window koreksi 1 jam (berdasarkan shift_id dari item pertama)
        $first   = reset($items);
        $date    = $first['date'] ?? null;
        $shiftId = (int)($first['shift_id'] ?? 0);

        if (!$date || !$shiftId) {
            return redirect()->back()->with('error', 'Data shift/tanggal tidak valid');
        }

        $slots = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('ts.time_start', 'ASC')
            ->get()->getResultArray();

        $shiftStart = $slots[0]['time_start'] ?? null;
        $shiftEnd   = null;
        if (!empty($slots)) {
            $last = end($slots);
            $shiftEnd = $last['time_end'] ?? null;
        }

        if ($shiftStart && $shiftEnd) {
            $startDt = new \DateTime($date.' '.$shiftStart, $tz);
            $endDt   = new \DateTime($date.' '.$shiftEnd, $tz);
            if ($endDt <= $startDt) $endDt->modify('+1 day');

            $deadline = (clone $endDt)->modify('+1 hour');

            if (!($now >= $endDt && $now <= $deadline)) {
                return redirect()->back()->with(
                    'error',
                    'Waktu koreksi sudah habis. Koreksi hanya dapat dilakukan maksimal 1 jam setelah shift berakhir.'
                );
            }
        }

        $downtimeValues = [];
        if ($db->tableExists('downtime_categories')) {
            $dtRows = $db->table('downtime_categories')->get()->getResultArray();
            foreach ($dtRows as $dt) {
                $downtimeValues[(int)$dt['id']] = (int)$dt['value'];
            }
        }

        $db->transBegin();

        try {
            foreach ($items as $row) {
                $productionId = (int)($row['production_id'] ?? 0);
                $date         = $row['date'] ?? null;
                $shiftId      = (int)($row['shift_id'] ?? 0);
                $machineId    = (int)($row['machine_id'] ?? 0);
                $productId    = (int)($row['product_id'] ?? 0);

                if (!$productionId || !$date || !$shiftId || !$machineId || !$productId) {
                    continue;
                }

                $fg = (int)($row['fg'] ?? 0);
                $ng = (int)($row['ng'] ?? 0);

                // 1) Update header DC production
                $db->table('die_casting_production')
                    ->where('id', $productionId)
                    ->update([
                        'qty_a'  => $fg,
                        'qty_ng' => $ng
                    ]);

                // 2) Update WIP: DC -> next (stock harus ikut qtyA koreksi)
                //    Cari to_process_id dari flow, fallback dari wip existing.
                $toProcessId = $this->resolveNextProcessByFlow($db, $productId, $dcProcessId);

                // fallback: kalau flow gak ketemu, ambil dari wip row yg sudah ada
                if (!$toProcessId) {
                    $tmp = $db->table('production_wip')
                        ->select('to_process_id')
                        ->where('source_table', 'die_casting_production')
                        ->where('source_id', $productionId)
                        ->where('production_date', $date)
                        ->where('from_process_id', $dcProcessId)
                        ->get()->getRowArray();
                    $toProcessId = (int)($tmp['to_process_id'] ?? 0);
                }

                if ($toProcessId > 0 && $db->tableExists('production_wip')) {
                    $wipRow = $db->table('production_wip')
                        ->where([
                            'source_table'    => 'die_casting_production',
                            'source_id'       => $productionId,
                            'production_date' => $date,
                            'from_process_id' => $dcProcessId,
                            'to_process_id'   => $toProcessId,
                        ])
                        ->get()->getRowArray();

                    if ($wipRow) {
                        $status = (string)($wipRow['status'] ?? 'WAITING');

                        $upd = [
                            // qty legacy mengikuti koreksi FG agar inventory sinkron
                            'qty' => $fg,
                        ];

                        // jika belum DONE: stock = FG (koreksi)
                        // jika DONE: stock harus 0 (sudah dipindah), tapi qty_out perlu ikut koreksi
                        if ($hasStock) {
                            $upd['stock'] = ($status === 'DONE') ? 0 : $fg;
                        }

                        if ($hasQtyOut && $status === 'DONE') {
                            $upd['qty_out'] = $fg;
                        }

                        if ($hasUpdated) {
                            $upd['updated_at'] = $now->format('Y-m-d H:i:s');
                        }

                        $db->table('production_wip')->where('id', (int)$wipRow['id'])->update($upd);
                    } else {
                        // Jika belum ada row WIP-nya, kita buat minimal agar stock tercatat.
                        // Status default WAITING (belum finish shift).
                        $ins = [
                            'production_date' => $date,
                            'product_id'      => $productId,
                            'from_process_id' => $dcProcessId,
                            'to_process_id'   => $toProcessId,
                            'source_table'    => 'die_casting_production',
                            'source_id'       => $productionId,
                            'qty'             => $fg,
                            'status'          => 'WAITING',
                        ];
                        if ($hasStock)  $ins['stock'] = $fg;
                        if ($hasUpdated) $ins['updated_at'] = $now->format('Y-m-d H:i:s');
                        if ($db->fieldExists('created_at', 'production_wip')) $ins['created_at'] = $now->format('Y-m-d H:i:s');

                        $db->table('production_wip')->insert($ins);
                    }

                    // 3) Jika sudah pernah pindah ke proses berikutnya (finish shift sudah jalan),
                    //    biasanya ada WIP row: (toProcessId -> nextNext) dengan source yg sama.
                    //    Update qty_in & stock pada row tersebut agar ikut koreksi.
                    $nextNextId = $this->resolveNextProcessByFlow($db, $productId, (int)$toProcessId);

                    if ($nextNextId) {
                        $wipNext = $db->table('production_wip')
                            ->where([
                                'source_table'    => 'die_casting_production',
                                'source_id'       => $productionId,
                                'production_date' => $date,
                                'from_process_id' => (int)$toProcessId,
                                'to_process_id'   => (int)$nextNextId,
                            ])
                            ->get()->getRowArray();

                        if ($wipNext) {
                            $upd2 = [
                                'qty' => $fg,
                            ];
                            if ($hasQtyIn)  $upd2['qty_in'] = $fg;
                            if ($hasStock)  $upd2['stock']  = $fg; // stock yang tersedia di proses berikutnya ikut koreksi
                            if ($hasUpdated) $upd2['updated_at'] = $now->format('Y-m-d H:i:s');

                            // jangan ganggu jika row itu sudah DONE
                            if ((string)($wipNext['status'] ?? '') !== 'DONE') {
                                $db->table('production_wip')->where('id', (int)$wipNext['id'])->update($upd2);
                            }
                        }
                    }
                }

                // 4) simpan ng details (multiple) & downtime ke last slot saja
                $ngDetails = $row['ng_details'] ?? [];

                // Support dt_details array (new inline DT) or legacy downtime_category_id
                $dtDetails = $row['dt_details'] ?? [];
                $dtId = 0;
                $downtime = 0;
                if (!empty($dtDetails) && is_array($dtDetails)) {
                    foreach ($dtDetails as $dtEntry) {
                        $downtime += (int)($dtEntry['downtime_minute'] ?? 0);
                        if (!$dtId && !empty($dtEntry['downtime_category_id'])) {
                            $dtId = (int)$dtEntry['downtime_category_id'];
                        }
                    }
                } else {
                    $dtId = (int)($row['downtime_category_id'] ?? 0);
                    $downtime = $dtId > 0 ? ($downtimeValues[$dtId] ?? 0) : 0;
                }

                $lastSlot = $db->table('shift_time_slots sts')
                    ->select('ts.id AS time_slot_id')
                    ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                    ->where('sts.shift_id', $shiftId)
                    ->orderBy('ts.time_start', 'DESC')
                    ->get()->getRowArray();
                $lastSlotId = (int)($lastSlot['time_slot_id'] ?? 0);

                if ($lastSlotId > 0) {
                    $db->table('die_casting_hourly')
                        ->where([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => $machineId,
                            'product_id'      => $productId,
                        ])
                        ->update([
                            'downtime_minute' => 0,
                            'ng_category_id'  => null,
                        ]);

                    // hapus detail di jam mana pun untuk shift/mesin/produk ini
                    $allHourlyIds = $db->table('die_casting_hourly')
                        ->select('id')
                        ->where([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => $machineId,
                            'product_id'      => $productId,
                        ])->get()->getResultArray();
                    
                    if (!empty($allHourlyIds) && $db->tableExists('die_casting_hourly_ng_details')) {
                        $ids = array_column($allHourlyIds, 'id');
                        $db->table('die_casting_hourly_ng_details')->whereIn('hourly_id', $ids)->delete();
                    }

                    $db->table('die_casting_hourly')
                        ->where([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => $machineId,
                            'product_id'      => $productId,
                            'time_slot_id'    => $lastSlotId,
                        ])
                        ->update([
                            'downtime_category_id' => $dtId > 0 ? $dtId : null,
                            'downtime_minute'      => $downtime,
                            'updated_at'           => $now->format('Y-m-d H:i:s')
                        ]);

                    $lastHourlyRow = $db->table('die_casting_hourly')
                        ->select('id')
                        ->where([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => $machineId,
                            'product_id'      => $productId,
                            'time_slot_id'    => $lastSlotId,
                        ])->get()->getRowArray();

                    if ($lastHourlyRow && is_array($ngDetails) && !empty($ngDetails)) {
                        $this->saveNgDetails($db, (int)$lastHourlyRow['id'], $ngDetails);
                        $sumNg = $this->sumNgDetail($db, (int)$lastHourlyRow['id']);
                        $db->table('die_casting_hourly')
                           ->where('id', (int)$lastHourlyRow['id'])
                           ->update(['qty_ng' => $sumNg]);
                        
                        // sinkronkan juga headernya (berjaga-jaga jika input ng dimanipulasi)
                        $db->table('die_casting_production')
                           ->where('id', $productionId)
                           ->update(['qty_ng' => $sumNg]);
                    }
                }

                // 5) simpan remarks per slot
                $remarks = $row['remarks'] ?? [];
                if (is_array($remarks)) {
                    foreach ($remarks as $slotId => $rmk) {
                        $sId = (int)$slotId;
                        if ($sId <= 0) continue;
                        
                        // Cek apakah data hourly untuk slot ini sudah ada
                        $hourlyRow = $db->table('die_casting_hourly')
                            ->select('id')
                            ->where([
                                'production_date' => $date,
                                'shift_id'        => $shiftId,
                                'machine_id'      => $machineId,
                                'product_id'      => $productId,
                                'time_slot_id'    => $sId,
                            ])->get()->getRowArray();

                        if ($hourlyRow) {
                            $db->table('die_casting_hourly')
                               ->where('id', (int)$hourlyRow['id'])
                               ->update(['remark' => $rmk ?: null]);
                        } else if ($rmk) {
                            // Jika belum ada tapi ada remark, buat row baru dengan 0 qty
                            $db->table('die_casting_hourly')->insert([
                                'production_date' => $date,
                                'shift_id'        => $shiftId,
                                'machine_id'      => $machineId,
                                'product_id'      => $productId,
                                'time_slot_id'    => $sId,
                                'qty_fg'          => 0,
                                'qty_ng'          => 0,
                                'remark'          => $rmk,
                                'created_at'      => $now->format('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()->with('success', 'Daily Production per Shift berhasil disimpan + stock WIP ikut update sesuai koreksi qty A.');
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

    private function sumNgDetail($db, int $hourlyId): int
    {
        if (!$db->tableExists('die_casting_hourly_ng_details')) return 0;
        $row = $db->table('die_casting_hourly_ng_details')->select('SUM(qty) AS s')->where('hourly_id', $hourlyId)->get()->getRowArray();
        return (int)($row['s'] ?? 0);
    }
}
