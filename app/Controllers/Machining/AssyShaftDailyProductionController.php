<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class AssyShaftDailyProductionController extends BaseController
{
    /* =========================
     * PROCESS ID (Assy Shaft)
     * ========================= */
    private function getProcessIdAssyShaft($db): int
    {
        // by code AS if exists
        if ($db->fieldExists('process_code', 'production_processes')) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_code', 'AS')
                ->get()->getRowArray();
            if (!empty($row['id'])) return (int)$row['id'];
        }

        // exact name
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Assy Shaft')
            ->get()->getRowArray();
        if (!empty($row['id'])) return (int)$row['id'];

        // like fallback
        $row = $db->table('production_processes')
            ->select('id')
            ->like('process_name', 'Assy Shaft')
            ->get()->getRowArray();
        if (!empty($row['id'])) return (int)$row['id'];

        throw new \Exception('Process "Assy Shaft" belum ada di master production_processes');
    }

    /* =========================
     * Detect kolom tanggal di production_wip
     * ========================= */
    private function detectWipDateColumn($db): string
    {
        if ($db->fieldExists('production_date', 'production_wip')) return 'production_date';
        if ($db->fieldExists('schedule_date', 'production_wip'))   return 'schedule_date';
        if ($db->fieldExists('wip_date', 'production_wip'))        return 'wip_date';
        // fallback aman
        return 'production_date';
    }

    /* =========================
     * FLOW: resolve prev process (by sequence - 1)
     * ========================= */
    private function resolvePrevProcessId($db, int $productId, int $toProcessId): ?int
    {
        $flows = $db->table('product_process_flows')
            ->select('process_id, sequence')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getResultArray();

        if (!$flows) return null;

        $idx = null;
        foreach ($flows as $i => $f) {
            if ((int)$f['process_id'] === (int)$toProcessId) { $idx = $i; break; }
        }
        if ($idx === null) return null;

        return isset($flows[$idx - 1]) ? (int)$flows[$idx - 1]['process_id'] : null;
    }

    /* =========================================================
     * ✅ SYNC STOCK WIP Assy Shaft dari hourly OK (SUM qty_fg)
     * - Update/buat row WIP inbound (prev -> Assy Shaft) source daily_schedule_items
     * - stock = qty OK aktual (qty A)
     * - tidak mengubah qty_out
     * ========================================================= */
    private function syncAssyShaftWipStockFromHourly($db, string $date): void
    {
        if (!$db->tableExists('production_wip')) return;
        if (!$db->tableExists('machining_assy_shaft_hourly')) return;

        // perlu untuk mapping ke source_id schedule item
        if (!$db->tableExists('daily_schedule_items') || !$db->tableExists('daily_schedules')) return;

        $assyProcessId = $this->getProcessIdAssyShaft($db);
        $wipDateCol    = $this->detectWipDateColumn($db);

        $hasQtyIn   = $db->fieldExists('qty_in', 'production_wip');
        $hasQtyOut  = $db->fieldExists('qty_out', 'production_wip');
        $hasStock   = $db->fieldExists('stock', 'production_wip');
        $hasSrcTbl  = $db->fieldExists('source_table', 'production_wip');
        $hasSrcId   = $db->fieldExists('source_id', 'production_wip');

        if (!$hasStock) return;

        // schedule items Assy Shaft (semua shift pada tanggal itu)
        $items = $db->table('daily_schedule_items dsi')
            ->select('dsi.id AS dsi_id, dsi.machine_id, dsi.product_id')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('ds.schedule_date', $date)
            ->where('ds.section', 'Assy Shaft')
            ->where('dsi.target_per_shift >', 0)
            ->get()
            ->getResultArray();

        if (!$items) return;

        // total OK harian per (machine_id, product_id) dari semua shift
        $actualRows = $db->table('machining_assy_shaft_hourly')
            ->select('machine_id, product_id, SUM(qty_fg) AS fg_total')
            ->where('production_date', $date)
            ->groupBy('machine_id, product_id')
            ->get()
            ->getResultArray();

        $actualMap = [];
        foreach ($actualRows as $a) {
            $mid = (int)$a['machine_id'];
            $pid = (int)$a['product_id'];
            $actualMap[$mid.'_'.$pid] = (int)($a['fg_total'] ?? 0);
        }

        $now = date('Y-m-d H:i:s');

        foreach ($items as $si) {
            $dsiId     = (int)$si['dsi_id'];
            $machineId = (int)$si['machine_id'];
            $productId = (int)$si['product_id'];

            if ($dsiId <= 0 || $machineId <= 0 || $productId <= 0) continue;

            $qtyA = (int)($actualMap[$machineId.'_'.$productId] ?? 0);

            // prev process inbound ke Assy Shaft
            $prevProcessId = $this->resolvePrevProcessId($db, $productId, $assyProcessId);
            if (!$prevProcessId) continue;

            // key inbound: prev -> Assy Shaft (source daily_schedule_items)
            $key = [
                $wipDateCol       => $date,
                'product_id'      => $productId,
                'from_process_id' => $prevProcessId,
                'to_process_id'   => $assyProcessId,
            ];
            if ($hasSrcTbl) $key['source_table'] = 'daily_schedule_items';
            if ($hasSrcId)  $key['source_id']    = $dsiId;

            $exist = $db->table('production_wip')->where($key)->get()->getRowArray();

            if (!$exist) {
                // buat minimal agar stock muncul
                $payload = $key + [
                    'qty'    => 0,
                    'status' => 'SCHEDULED',
                    'stock'  => $qtyA,
                ];
                if ($hasQtyIn)  $payload['qty_in']  = 0;
                if ($hasQtyOut) $payload['qty_out'] = 0;

                if ($db->fieldExists('created_at', 'production_wip')) $payload['created_at'] = $now;
                if ($db->fieldExists('updated_at', 'production_wip')) $payload['updated_at'] = $now;

                $db->table('production_wip')->insert($payload);
            } else {
                // update stock = qty OK aktual (qty A)
                $upd = ['stock' => $qtyA];
                if ($db->fieldExists('updated_at', 'production_wip')) $upd['updated_at'] = $now;
                $db->table('production_wip')->where('id', (int)$exist['id'])->update($upd);
            }
        }
    }

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
            ->get()
            ->getResultArray();

        foreach ($shifts as &$shift) {

            /* ===== TIME SLOT ===== */
            $shift['slots'] = $db->table('shift_time_slots sts')
                ->select('ts.id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', $shift['id'])
                ->orderBy('ts.time_start')
                ->get()
                ->getResultArray();

            /* ===== TOTAL MENIT ===== */
            $totalMinute = 0;
            foreach ($shift['slots'] as &$slot) {
                $s = strtotime($slot['time_start']);
                $e = strtotime($slot['time_end']);
                if ($e <= $s) $e += 86400;

                $slot['minute'] = ($e - $s) / 60;
                $totalMinute   += $slot['minute'];
            }
            $shift['total_minute'] = $totalMinute;

            /* ===== ITEM DARI DAILY SCHEDULE ASSY SHAFT ===== */
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
                ->where('ds.section', 'Assy Shaft')
                ->orderBy('m.line_position')
                ->get()
                ->getResultArray();

            /* ===== HOURLY MAP – ASSY SHAFT ===== */
            $hourly = $db->table('machining_assy_shaft_hourly')
                ->where('production_date', $date)
                ->where('shift_id', $shift['id'])
                ->get()
                ->getResultArray();

            $shift['hourly_map'] = [];
            foreach ($hourly as $h) {
                $shift['hourly_map']
                    [(int)$h['machine_id']]
                    [(int)$h['product_id']]
                    [(int)$h['time_slot_id']] = $h;
            }
        }
        unset($shift);

        return view('machining/assy_shaft/daily_production/index', [
            'date'     => $date,
            'operator' => $operator,
            'shifts'   => $shifts
        ]);
    }

    /* =====================================================
     * STORE
     * - simpan hourly
     * - ✅ update stock WIP inbound (prev -> Assy Shaft) = SUM OK (qty_fg)
     * ===================================================== */
    public function store()
    {
        $db    = db_connect();
        $items = $this->request->getPost('items') ?? [];

        if (empty($items)) {
            return redirect()->back()->with('error', 'Tidak ada data disimpan');
        }

        // ambil tanggal dari payload
        $date = null;
        foreach ($items as $r) {
            if (!empty($r['date'])) { $date = (string)$r['date']; break; }
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
                    'production_date' => (string)$row['date'],
                    'shift_id'        => (int)$row['shift_id'],
                    'machine_id'      => (int)$row['machine_id'],
                    'product_id'      => (int)$row['product_id'],
                    'time_slot_id'    => (int)$row['time_slot_id'],
                ];

                // ✅ OK -> qty_fg
                $data = [
                    'qty_fg' => (int)($row['ok'] ?? 0),
                    'qty_ng' => (int)($row['ng'] ?? 0),
                ];

                // created_at/updated_at aman
                if ($db->fieldExists('updated_at', 'machining_assy_shaft_hourly')) {
                    $data['updated_at'] = date('Y-m-d H:i:s');
                } else {
                    // kalau tidak ada updated_at, tidak masalah
                }

                $exist = $db->table('machining_assy_shaft_hourly')
                    ->where($where)
                    ->get()
                    ->getRowArray();

                if ($exist) {
                    $db->table('machining_assy_shaft_hourly')
                        ->where('id', (int)$exist['id'])
                        ->update($data);
                } else {
                    if ($db->fieldExists('created_at', 'machining_assy_shaft_hourly')) {
                        $data['created_at'] = date('Y-m-d H:i:s');
                    }
                    $db->table('machining_assy_shaft_hourly')
                        ->insert(array_merge($where, $data));
                }
            }

            // ✅ sync stock WIP inbound setelah save hourly (mirip assy bushing)
            if ($date) {
                $this->syncAssyShaftWipStockFromHourly($db, $date);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->back()
                ->with('success', 'Assy Shaft hourly production berhasil disimpan + Stock WIP ter-update');

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
