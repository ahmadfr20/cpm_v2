<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class LeakTestDailyProductionController extends BaseController
{
    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
    {
        $db       = db_connect();
        $date     = $this->request->getGet('date') ?? date('Y-m-d');
        $operator = session()->get('fullname') ?? '-';

        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        foreach ($shifts as &$shift) {
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start', 'ASC')
                ->get()->getResultArray();

            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;

                $slot['minute'] = (int)(($end - $start) / 60);
                $totalMinute   += $slot['minute'];
            }
            unset($slot);
            $shift['total_minute'] = $totalMinute;

            $shift['items'] = $db->table('daily_schedule_items dsi')
                ->select('
                    dsi.id as schedule_item_id,
                    dsi.machine_id,
                    m.machine_code,
                    m.line_position,
                    dsi.product_id,
                    p.part_no,
                    p.part_name,
                    dsi.target_per_shift
                ')
                ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->join('machines m', 'm.id = dsi.machine_id')
                ->join('products p', 'p.id = dsi.product_id')
                ->where('ds.schedule_date', $date)
                ->where('ds.shift_id', $shift['id'])
                ->groupStart()
                    ->where('ds.section', 'Leak Test')
                    ->orWhere('ds.section', 'LEAK TEST')
                ->groupEnd()
                ->orderBy('m.line_position', 'ASC')
                ->get()->getResultArray();

            $hourly = $db->table('machining_leak_test_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map']
                    [(int)$h['machine_id']]
                    [(int)$h['product_id']]
                    [(int)$h['time_slot_id']] = $h;
            }
        }
        unset($shift);

        return view('machining/leak_test/daily_production/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }

    /* =====================================================
     * STORE HOURLY + SYNC schedule item + WIP stock
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items');

        if (!$items || !is_array($items)) {
            return redirect()->back()->with('error', 'Data kosong');
        }

        $db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');

            // cek kolom hourly yang benar
            $hourlyHasQtyOk = $db->fieldExists('qty_ok', 'machining_leak_test_hourly');
            $hourlyHasQtyNg = $db->fieldExists('qty_ng', 'machining_leak_test_hourly');

            if (!$hourlyHasQtyOk) {
                throw new \RuntimeException('Kolom machining_leak_test_hourly.qty_ok tidak ditemukan.');
            }

            // schedule item actual: bisa qty_ok atau qty_a (biar kompatibel)
            $dsiHasQtyOk = $db->fieldExists('qty_ok', 'daily_schedule_items');
            $dsiHasQtyA  = $db->fieldExists('qty_a', 'daily_schedule_items');

            // WIP columns
            $hasWip      = $db->tableExists('production_wip');
            $wipHasStock = $hasWip && $db->fieldExists('stock', 'production_wip');
            $wipHasQtyOut= $hasWip && $db->fieldExists('qty_out', 'production_wip');
            $wipHasQtyIn = $hasWip && $db->fieldExists('qty_in', 'production_wip');
            $wipHasQty   = $hasWip && $db->fieldExists('qty', 'production_wip');

            // 1) UPSERT hourly + kumpulkan combo yang kena
            $affected = []; // unique by date|shift|machine|product
            foreach ($items as $row) {
                $date      = $row['date'] ?? null;
                $shiftId   = (int)($row['shift_id'] ?? 0);
                $machineId = (int)($row['machine_id'] ?? 0);
                $productId = (int)($row['product_id'] ?? 0);
                $slotId    = (int)($row['time_slot_id'] ?? 0);

                if (!$date || $shiftId <= 0 || $machineId <= 0 || $productId <= 0 || $slotId <= 0) {
                    continue;
                }

                $key = $date.'|'.$shiftId.'|'.$machineId.'|'.$productId;
                $affected[$key] = [
                    'date'       => $date,
                    'shift_id'   => $shiftId,
                    'machine_id' => $machineId,
                    'product_id' => $productId,
                ];

                $where = [
                    'production_date' => $date,
                    'shift_id'        => $shiftId,
                    'machine_id'      => $machineId,
                    'product_id'      => $productId,
                    'time_slot_id'    => $slotId,
                ];

                $data = [
                    'qty_ok'      => (int)($row['ok'] ?? 0),
                    'updated_at'  => $now,
                ];
                if ($hourlyHasQtyNg) {
                    $data['qty_ng'] = (int)($row['ng'] ?? 0);
                }
                if ($db->fieldExists('ng_category', 'machining_leak_test_hourly')) {
                    $data['ng_category'] = $row['ng_category'] ?? null;
                }

                $exist = $db->table('machining_leak_test_hourly')
                    ->where($where)
                    ->get()->getRowArray();

                if ($exist) {
                    $db->table('machining_leak_test_hourly')
                        ->where('id', (int)$exist['id'])
                        ->update($data);
                } else {
                    $insert = $where + $data;
                    if ($db->fieldExists('created_at', 'machining_leak_test_hourly')) {
                        $insert['created_at'] = $now;
                    }
                    $db->table('machining_leak_test_hourly')->insert($insert);
                }
            }

            if (empty($affected)) {
                $db->transCommit();
                return redirect()->back()->with('success', 'Tidak ada data valid untuk disimpan.');
            }

            // 2) SYNC schedule item actual + WIP stock/qty_out dari SUM(qty_ok)
            foreach ($affected as $a) {
                $date      = $a['date'];
                $shiftId   = (int)$a['shift_id'];
                $machineId = (int)$a['machine_id'];
                $productId = (int)$a['product_id'];

                // total OK untuk kombinasi ini
                $sumRow = $db->table('machining_leak_test_hourly')
                    ->select('SUM(qty_ok) AS ok_total')
                    ->where('production_date', $date)
                    ->where('shift_id', $shiftId)
                    ->where('machine_id', $machineId)
                    ->where('product_id', $productId)
                    ->get()->getRowArray();

                $okTotal = (int)($sumRow['ok_total'] ?? 0);

                // ambil schedule header+item agar dapat process_id Leak Test yang benar (menghindari mismatch)
                $dsiRow = $db->table('daily_schedule_items dsi')
                    ->select('dsi.id AS dsi_id, ds.id AS ds_id, ds.process_id AS lt_process_id')
                    ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                    ->where('ds.schedule_date', $date)
                    ->where('ds.shift_id', $shiftId)
                    ->groupStart()
                        ->where('ds.section', 'Leak Test')
                        ->orWhere('ds.section', 'LEAK TEST')
                    ->groupEnd()
                    ->where('dsi.machine_id', $machineId)
                    ->where('dsi.product_id', $productId)
                    ->get()->getRowArray();

                if (!$dsiRow) {
                    // hourly tersimpan, tapi tidak ada schedule item untuk sync
                    continue;
                }

                $dsiId = (int)$dsiRow['dsi_id'];

                // Leak Test process id yang dipakai schedule (kalau kosong fallback pakai master by code LT)
                $ltProcessId = (int)($dsiRow['lt_process_id'] ?? 0);
                if ($ltProcessId <= 0) {
                    $ltProcessId = $this->getProcessIdByCode($db, 'LT') ?? 0;
                }
                if ($ltProcessId <= 0) {
                    // kalau benar-benar tidak ketemu, skip sync WIP biar tidak salah update
                    continue;
                }

                // (A) update actual di schedule item
                if ($dsiHasQtyOk) {
                    $db->table('daily_schedule_items')->where('id', $dsiId)->update(['qty_ok' => $okTotal]);
                } elseif ($dsiHasQtyA) {
                    $db->table('daily_schedule_items')->where('id', $dsiId)->update(['qty_a' => $okTotal]);
                }

                // (B) update WIP inbound prev -> Leak Test
                if ($hasWip && ($wipHasStock || $wipHasQtyOut)) {
                    // cari row WIP yang dibuat oleh schedule (paling aman: by to_process + source_table + source_id)
                    $wipWhere = [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'to_process_id'   => $ltProcessId,
                        'source_table'    => 'daily_schedule_items',
                        'source_id'       => $dsiId,
                    ];

                    $wipExist = $db->table('production_wip')->where($wipWhere)->get()->getRowArray();

                    $upd = [];
                    if ($wipHasQtyOut) $upd['qty_out'] = $okTotal;
                    if ($wipHasStock)  $upd['stock']   = $okTotal;
                    if ($db->fieldExists('updated_at', 'production_wip')) $upd['updated_at'] = $now;

                    if ($wipExist) {
                        // jangan ubah status di sini; status DONE dilakukan di finishShift
                        if (!empty($upd)) {
                            $db->table('production_wip')->where('id', (int)$wipExist['id'])->update($upd);
                        }
                    } else {
                        // kalau belum ada (misal schedule lama bikin tanpa source_table/source_id),
                        // coba fallback: cari berdasarkan date+product+to_process_id saja (ambil 1 row)
                        $fallback = $db->table('production_wip')
                            ->where([
                                'production_date' => $date,
                                'product_id'      => $productId,
                                'to_process_id'   => $ltProcessId,
                            ])
                            ->orderBy('id', 'DESC')
                            ->get()->getRowArray();

                        if ($fallback) {
                            if (!empty($upd)) {
                                $db->table('production_wip')->where('id', (int)$fallback['id'])->update($upd);
                            }
                        } else {
                            // insert minimal inbound
                            $prevProcessId = $this->resolvePrevProcessIdActive($db, $productId, $ltProcessId);
                            $ins = [
                                'production_date' => $date,
                                'product_id'      => $productId,
                                'from_process_id' => $prevProcessId,
                                'to_process_id'   => $ltProcessId,
                                'status'          => 'SCHEDULED',
                                'source_table'    => 'daily_schedule_items',
                                'source_id'       => $dsiId,
                            ];
                            if ($wipHasQty)    $ins['qty']     = 0;
                            if ($wipHasQtyIn)  $ins['qty_in']  = 0;
                            if ($wipHasQtyOut) $ins['qty_out'] = $okTotal;
                            if ($wipHasStock)  $ins['stock']   = $okTotal;
                            if ($db->fieldExists('created_at', 'production_wip')) $ins['created_at'] = $now;
                            if ($db->fieldExists('updated_at', 'production_wip')) $ins['updated_at'] = $now;

                            $db->table('production_wip')->insert($ins);
                        }
                    }
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('DB error');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Leak Test hourly tersimpan + qty_ok & stock WIP ter-update');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * FINISH SHIFT (pastikan pakai SUM(qty_ok))
     * ===================================================== */
    public function finishShift()
    {
        $db      = db_connect();
        $date    = $this->request->getPost('date') ?? date('Y-m-d');
        $shiftId = (int)($this->request->getPost('shift_id') ?? 0);

        if (!$shiftId) {
            return redirect()->back()->with('error', 'shift_id tidak valid');
        }

        $db->transBegin();

        try {
            $lastShift = $this->getLastMachiningShift($db);
            if (!$lastShift) throw new \RuntimeException('Tidak ditemukan shift MC aktif');
            if ((int)$lastShift['id'] !== $shiftId) throw new \RuntimeException('Finish Shift hanya boleh pada shift terakhir MC');

            $lastSlot = $this->getLastTimeSlotOfShift($db, $shiftId);
            if (!$lastSlot) throw new \RuntimeException('Time slot shift tidak ditemukan');
            if (!$this->isAfterSlotEnd($date, $lastSlot['time_start'], $lastSlot['time_end'])) {
                throw new \RuntimeException('Shift belum selesai. Tunggu sampai slot terakhir berakhir.');
            }

            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT');
            if (!$leakTestProcessId) throw new \RuntimeException('Process LT (Leak Test) tidak ditemukan');

            $totals = $db->table('machining_leak_test_hourly')
                ->select('product_id, SUM(qty_ok) AS fg')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->groupBy('product_id')
                ->get()->getResultArray();

            if (empty($totals)) throw new \RuntimeException('Tidak ada data hourly Leak Test untuk di-finish');

            foreach ($totals as $t) {
                $productId = (int)$t['product_id'];
                $fg        = (int)$t['fg'];
                if ($productId <= 0) continue;

                $flow = $this->getFlowPrevNext($db, $productId, $leakTestProcessId);
                if (empty($flow['sequence'])) continue;

                $prevProcessId = $flow['prev'];
                $nextProcessId = $flow['next'];

                $inKey = [
                    'production_date' => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevProcessId,
                    'to_process_id'   => $leakTestProcessId,
                ];

                $inExist = $db->table('production_wip')->where($inKey)->get()->getRowArray();
                if (!$inExist) {
                    $db->table('production_wip')->insert($inKey + [
                        'status'       => 'SCHEDULED',
                        'qty'          => 0,
                        'qty_in'       => 0,
                        'qty_out'      => 0,
                        'stock'        => 0,
                        'source_table' => 'machining_leak_test_hourly',
                        'source_id'    => null,
                        'created_at'   => date('Y-m-d H:i:s'),
                    ]);
                    $inExist = $db->table('production_wip')->where($inKey)->get()->getRowArray();
                }

                $db->table('production_wip')->where('id', (int)$inExist['id'])->update([
                    'qty_out'    => $fg,
                    'stock'      => $fg,
                    'status'     => 'DONE',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                if (!empty($nextProcessId)) {
                    $outKey = [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $leakTestProcessId,
                        'to_process_id'   => $nextProcessId,
                    ];

                    $outExist = $db->table('production_wip')->where($outKey)->get()->getRowArray();

                    if ($outExist) {
                        $db->table('production_wip')->where('id', (int)$outExist['id'])->update([
                            'qty_in'     => ((int)($outExist['qty_in'] ?? 0)) + $fg,
                            'status'     => 'WAITING',
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $db->table('production_wip')->insert($outKey + [
                            'status'       => 'WAITING',
                            'qty'          => 0,
                            'qty_in'       => $fg,
                            'qty_out'      => 0,
                            'stock'        => 0,
                            'source_table' => 'production_wip',
                            'source_id'    => $inExist['id'],
                            'created_at'   => date('Y-m-d H:i:s')
                        ]);
                    }

                    $db->table('production_wip')->where('id', (int)$inExist['id'])->update([
                        'stock'      => 0,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Finish Shift Leak Test sukses: WIP DONE & transfer ke proses berikutnya');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ===================== HELPERS =====================

    private function getProcessIdByCode($db, string $code): ?int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_code', $code)
            ->get()->getRowArray();

        return $row ? (int)$row['id'] : null;
    }

    private function resolvePrevProcessIdActive($db, int $productId, int $currentProcessId): ?int
    {
        $cur = $db->table('product_process_flows')
            ->select('sequence')
            ->where([
                'product_id' => $productId,
                'process_id' => $currentProcessId,
                'is_active'  => 1,
            ])
            ->get()->getRowArray();

        if (!$cur) return null;

        $seq = (int)$cur['sequence'];
        if ($seq <= 1) return null;

        $prev = $db->table('product_process_flows')
            ->select('process_id')
            ->where([
                'product_id' => $productId,
                'sequence'   => $seq - 1,
                'is_active'  => 1,
            ])
            ->get()->getRowArray();

        return $prev ? (int)$prev['process_id'] : null;
    }

    private function getFlowPrevNext($db, int $productId, int $currentProcessId): array
    {
        $cur = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'process_id' => $currentProcessId])
            ->get()->getRowArray();

        if (!$cur) return ['prev' => null, 'next' => null, 'sequence' => null];

        $seq = (int)$cur['sequence'];

        $prev = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'sequence' => $seq - 1])
            ->get()->getRowArray();

        $next = $db->table('product_process_flows')
            ->where(['product_id' => $productId, 'sequence' => $seq + 1])
            ->get()->getRowArray();

        return [
            'prev'     => $prev ? (int)$prev['process_id'] : null,
            'next'     => $next ? (int)$next['process_id'] : null,
            'sequence' => $seq
        ];
    }

    private function getLastMachiningShift($db): ?array
    {
        $row = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'DESC')
            ->get()->getRowArray();

        return $row ?: null;
    }

    private function getLastTimeSlotOfShift($db, int $shiftId): ?array
    {
        $row = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('ts.time_start', 'DESC')
            ->get()->getRowArray();

        return $row ?: null;
    }

    private function isAfterSlotEnd(string $date, string $start, string $end): bool
    {
        $startTs = strtotime($date . ' ' . $start);
        $endTs   = strtotime($date . ' ' . $end);
        if ($endTs <= $startTs) $endTs += 86400;

        return time() >= $endTs;
    }
}
