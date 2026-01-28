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

        /* =========================
         * SHIFT MACHINING (MC)
         * ========================= */
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($shifts as &$shift) {

            /* =========================
             * TIME SLOT
             * ========================= */
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()
                ->getResultArray();

            /* =========================
             * TOTAL MENIT SHIFT
             * ========================= */
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $start = strtotime($slot['time_start']);
                $end   = strtotime($slot['time_end']);
                if ($end <= $start) $end += 86400;

                $slot['minute'] = ($end - $start) / 60;
                $totalMinute   += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            /* =========================
             * ITEM DARI DAILY SCHEDULE LEAK TEST
             * ========================= */
            $shift['items'] = $db->table('daily_schedule_items dsi')
                ->select('
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
                ->where('ds.section', 'Leak Test')
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            /* =========================
             * HOURLY MAP (LEAK TEST)
             * ========================= */
            $hourly = $db->table('machining_leak_test_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()
                ->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map']
                    [$h['machine_id']]
                    [$h['product_id']]
                    [$h['time_slot_id']] = $h;
            }
        }

        return view('machining/leak_test/daily_production/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }

    /* =====================================================
     * STORE HOURLY
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
            foreach ($items as $row) {
                if (
                    empty($row['date']) ||
                    empty($row['shift_id']) ||
                    empty($row['machine_id']) ||
                    empty($row['product_id']) ||
                    empty($row['time_slot_id'])
                ) {
                    continue;
                }

                $where = [
                    'production_date' => $row['date'],
                    'shift_id'        => $row['shift_id'],
                    'machine_id'      => $row['machine_id'],
                    'product_id'      => $row['product_id'],
                    'time_slot_id'    => $row['time_slot_id'],
                ];

                $data = [
                    'qty_ok'      => (int) ($row['ok'] ?? 0),
                    'qty_ng'      => (int) ($row['ng'] ?? 0),
                    'ng_category' => $row['ng_category'] ?? null,   // ✅ tambahan
                    'updated_at'  => date('Y-m-d H:i:s')
                ];

                $exist = $db->table('machining_leak_test_hourly')
                    ->where($where)
                    ->get()
                    ->getRowArray();

                if ($exist) {
                    $db->table('machining_leak_test_hourly')
                        ->where('id', $exist['id'])
                        ->update($data);
                } else {
                    $db->table('machining_leak_test_hourly')
                        ->insert(array_merge($where, $data, [
                            'created_at' => date('Y-m-d H:i:s')
                        ]));
                }
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Leak Test hourly production tersimpan');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =====================================================
     * FINISH SHIFT (Shift terakhir MC) + TRANSFER KE NEXT PROCESS
     * ===================================================== */
    public function finishShift()
    {
        $db      = db_connect();
        $date    = $this->request->getPost('date') ?? date('Y-m-d');
        $shiftId = (int) ($this->request->getPost('shift_id') ?? 0);

        if (!$shiftId) {
            return redirect()->back()->with('error', 'shift_id tidak valid');
        }

        $db->transBegin();

        try {
            // 1) Validasi: shift harus shift terakhir MC (setara “shift 3”)
            $lastShift = $this->getLastMachiningShift($db);
            if (!$lastShift) {
                throw new \RuntimeException('Tidak ditemukan shift MC aktif');
            }
            if ((int)$lastShift['id'] !== $shiftId) {
                throw new \RuntimeException('Finish Shift hanya boleh pada shift terakhir MC');
            }

            // 2) Validasi: shift sudah selesai (berdasarkan slot terakhir)
            $lastSlot = $this->getLastTimeSlotOfShift($db, $shiftId);
            if (!$lastSlot) {
                throw new \RuntimeException('Time slot shift tidak ditemukan');
            }
            if (!$this->isAfterSlotEnd($date, $lastSlot['time_start'], $lastSlot['time_end'])) {
                throw new \RuntimeException('Shift belum selesai. Tunggu sampai slot terakhir berakhir.');
            }

            // 3) Process Leak Test ID (pakai code LT)
            $leakTestProcessId = $this->getProcessIdByCode($db, 'LT');
            if (!$leakTestProcessId) {
                throw new \RuntimeException('Process LT (Leak Test) tidak ditemukan');
            }

            // 4) Ambil total OK per product (fg) dari hourly leak test untuk date+shift
            $totals = $db->table('machining_leak_test_hourly')
                ->select('product_id, SUM(qty_ok) AS fg')
                ->where('production_date', $date)
                ->where('shift_id', $shiftId)
                ->groupBy('product_id')
                ->get()->getResultArray();

            if (empty($totals)) {
                throw new \RuntimeException('Tidak ada data hourly Leak Test untuk di-finish');
            }

            foreach ($totals as $t) {
                $productId = (int) $t['product_id'];
                $fg        = (int) $t['fg'];

                if ($productId <= 0) continue;

                // 5) Tentukan prev/next process dari flow
                $flow = $this->getFlowPrevNext($db, $productId, $leakTestProcessId);
                if (empty($flow['sequence'])) {
                    // produk tidak punya flow leak test → skip
                    continue;
                }

                $prevProcessId = $flow['prev'];  // boleh null kalau proses pertama
                $nextProcessId = $flow['next'];  // boleh null kalau proses terakhir

                // 6) Update inbound WIP (prev -> leak test): DONE + qty_out/stock
                //    Catatan: WIP key = date + product + from + to
                $inKey = [
                    'production_date' => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $prevProcessId,
                    'to_process_id'   => $leakTestProcessId,
                ];

                // pastikan inbound ada
                $inExist = $db->table('production_wip')->where($inKey)->get()->getRowArray();
                if (!$inExist) {
                    // kalau schedule belum dibuat tapi hourly ada, kita buat minimal agar tidak putus
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

                // qty_out & stock kita set ke hasil FG leak test
                $db->table('production_wip')->where('id', $inExist['id'])->update([
                    'qty_out'    => $fg,
                    'stock'      => $fg,
                    'status'     => 'DONE',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // 7) Transfer ke next process (leak test -> next) jika ada next
                if (!empty($nextProcessId)) {
                    $outKey = [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => $leakTestProcessId,
                        'to_process_id'   => $nextProcessId,
                    ];

                    $outExist = $db->table('production_wip')->where($outKey)->get()->getRowArray();

                    if ($outExist) {
                        // tambah qty_in
                        $db->table('production_wip')->where('id', $outExist['id'])->update([
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

                    // 8) stock leak test jadi 0 setelah dipindah
                    $db->table('production_wip')->where('id', $inExist['id'])->update([
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
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function getLastTimeSlotOfShift($db, int $shiftId): ?array
    {
        $row = $db->table('shift_time_slots sts')
            ->select('ts.time_start, ts.time_end')
            ->join('time_slots ts', 'ts.id = sts.time_slot_id')
            ->where('sts.shift_id', $shiftId)
            ->orderBy('ts.time_start', 'DESC')
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function isAfterSlotEnd(string $date, string $start, string $end): bool
    {
        // handle slot melewati tengah malam
        $startTs = strtotime($date . ' ' . $start);
        $endTs   = strtotime($date . ' ' . $end);
        if ($endTs <= $startTs) {
            $endTs += 86400;
        }

        return time() >= $endTs;
    }
}
