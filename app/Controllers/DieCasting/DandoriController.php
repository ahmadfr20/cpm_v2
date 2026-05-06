<?php

namespace App\Controllers\DieCasting;

use App\Controllers\BaseController;

class DandoriController extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // 1. Ambil Data Shift Die Casting
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->where('day_group', $this->getDayGroup($date))
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // 2. Ambil Time Slots per Shift untuk pilihan jam Dandori
        $shiftSlots = [];
        foreach ($shifts as $shift) {
            $slots = $db->table('shift_time_slots sts')
                ->select('ts.id as time_slot_id, ts.time_start, ts.time_end')
                ->join('time_slots ts', 'ts.id = sts.time_slot_id')
                ->where('sts.shift_id', (int)$shift['id'])
                ->orderBy('sts.id', 'ASC')
                ->get()->getResultArray();

            foreach ($slots as &$s) {
                $s['label'] = substr($s['time_start'], 0, 5) . ' - ' . substr($s['time_end'], 0, 5);
            }
            $shiftSlots[$shift['id']] = $slots;
        }

        // 3. Ambil Master Mesin & Produk
        $machines = $db->table('machines m')
            ->select('m.*')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Die Casting')
            ->orderBy('m.line_position', 'ASC')
            ->get()->getResultArray();

        $products = $db->table('products')
            ->where('is_active', 1)
            ->orderBy('part_no', 'ASC')
            ->get()->getResultArray();

        // 4. Ambil Data Dandori
        $dandoriRecords = [];
        if ($db->tableExists('die_casting_dandori')) {
            $dandoriRecords = $db->table('die_casting_dandori d')
                ->select('d.*, m.line_position')
                ->join('machines m', 'm.id = d.machine_id', 'left')
                ->where('d.dandori_date', $date)
                ->orderBy('m.line_position', 'ASC')
                ->get()->getResultArray();
        }

        // 5. Kelompokkan Data Dandori berdasarkan shift dan setup unik
        $map = [];
        foreach ($dandoriRecords as $d) {
            $key = $d['shift_id'] . '_' . $d['machine_id'] . '_' . $d['product_id'] . '_' . md5($d['activity']);
            
            if (!isset($map[$d['shift_id']][$key])) {
                $d['time_slot_ids'] = [];
                $d['slot_minutes']  = []; // [ ['slot_id'=>X,'minute'=>Y], ... ]
                $map[$d['shift_id']][$key] = $d;
            }
            if (!empty($d['time_slot_id'])) {
                $slotId = (int)$d['time_slot_id'];
                $map[$d['shift_id']][$key]['time_slot_ids'][] = $slotId;
                $map[$d['shift_id']][$key]['slot_minutes'][] = [
                    'slot_id' => $slotId,
                    'minute'  => (int)($d['dandori_minute'] ?? 0),
                ];
            }
        }

        return view('die_casting/dandori/index', [
            'date'       => $date,
            'shifts'     => $shifts,
            'shiftSlots' => $shiftSlots,
            'machines'   => $machines,
            'products'   => $products,
            'map'        => $map
        ]);
    }

    public function store()
    {
        $db   = db_connect();
        $date  = $this->request->getPost('date');
        $items = $this->request->getPost('items');

        if (empty($date)) {
            return redirect()->back()->with('error', 'Tanggal tidak valid.');
        }

        $db->transBegin();
        try {
            // Hapus data dandori lama (agar sinkron saat ada yang dihapus dari UI)
            if ($db->tableExists('die_casting_dandori')) {
                $db->table('die_casting_dandori')->where('dandori_date', $date)->delete();
            }

            // Insert ulang data yang ada di form
            if (!empty($items) && is_array($items)) {
                $checkDuplicate = [];

                foreach ($items as $row) {
                    if (empty($row['shift_id']) || empty($row['machine_id']) || empty($row['product_id'])) continue;

                    $shiftId   = (int)$row['shift_id'];
                    $machineId = (int)$row['machine_id'];
                    $productId = (int)$row['product_id'];
                    $activity  = $row['activity'] ?? 'Setup/Dandori Preparation';

                    // Format baru: slot_data[slot_id][selected] + slot_data[slot_id][minute]
                    $slotData = $row['slot_data'] ?? [];

                    $hasAnySlot = false;
                    foreach ($slotData as $timeSlotId => $slotInfo) {
                        if (empty($slotInfo['selected'])) continue; // tidak dicentang

                        $timeSlotId    = (int)$timeSlotId;
                        $dandoriMinute = (int)($slotInfo['minute'] ?? 0);

                        // Validasi Duplikasi
                        $dupKey = $shiftId . '_' . $machineId . '_' . $timeSlotId;
                        if (isset($checkDuplicate[$dupKey])) {
                            throw new \Exception("Duplikasi data! Mesin ". $machineId ." tidak dapat disetup lebih dari 1 kali pada slot waktu yang sama.");
                        }
                        $checkDuplicate[$dupKey] = true;

                        $db->table('die_casting_dandori')->insert([
                            'dandori_date'   => $date,
                            'shift_id'       => $shiftId,
                            'machine_id'     => $machineId,
                            'product_id'     => $productId,
                            'time_slot_id'   => $timeSlotId,
                            'dandori_minute' => $dandoriMinute,
                            'activity'       => $activity,
                            'created_at'     => date('Y-m-d H:i:s'),
                        ]);
                        $hasAnySlot = true;
                    }

                    // Jika tidak ada slot dicentang, insert tanpa slot
                    if (!$hasAnySlot) {
                        $db->table('die_casting_dandori')->insert([
                            'dandori_date'   => $date,
                            'shift_id'       => $shiftId,
                            'machine_id'     => $machineId,
                            'product_id'     => $productId,
                            'time_slot_id'   => null,
                            'dandori_minute' => 0,
                            'activity'       => $activity,
                            'created_at'     => date('Y-m-d H:i:s'),
                        ]);
                    }

                    // ─── Maintain die_casting_production (plan record) ────────────────
                    $processIdRow = $db->table('production_processes')
                        ->where('process_name', 'Die Casting')->get()->getRowArray();
                    $processIdDC  = $processIdRow ? (int)$processIdRow['id'] : 1;

                    $existProd = $db->table('die_casting_production')
                        ->where(['production_date' => $date, 'shift_id' => $shiftId,
                                 'machine_id' => $machineId, 'product_id' => $productId])
                        ->get()->getRowArray();

                    if (!$existProd) {
                        $db->table('die_casting_production')->insert([
                            'production_date' => $date,
                            'shift_id'        => $shiftId,
                            'machine_id'      => $machineId,
                            'product_id'      => $productId,
                            'part_label'      => '',
                            'qty_p'           => 0,
                            'qty_a'           => 0,
                            'qty_ng'          => 0,
                            'status'          => 'Dandori',
                            'process_id'      => $processIdDC,
                            'is_completed'    => 0,
                            'created_at'      => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('Terjadi kesalahan saat update database.');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Jadwal Dandori Die Casting berhasil diperbarui.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}