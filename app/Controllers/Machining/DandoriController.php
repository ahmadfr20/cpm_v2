<?php

namespace App\Controllers\Machining;

use App\Controllers\BaseController;

class DandoriController extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // 1. Ambil Data Shift Machining (MC)
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'MC')
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

        // 3. Ambil Master Mesin & Produk Machining (Untuk menambah baris manual)
        $machines = $db->table('machines m')
            ->select('m.*')
            ->join('production_processes pp', 'pp.id = m.process_id')
            ->where('pp.process_name', 'Machining')
            ->orderBy('m.line_position', 'ASC')
            ->get()->getResultArray();

        $products = $db->table('products')
            ->where('is_active', 1)
            ->orderBy('part_no', 'ASC')
            ->get()->getResultArray();

        // 4. Ambil Data Dandori yang disubmit dari Daily Schedule Machining
        $dandoriRecords = [];
        if ($db->tableExists('machining_dandori')) {
            $dandoriRecords = $db->table('machining_dandori d')
                ->select('d.*, m.line_position')
                ->join('machines m', 'm.id = d.machine_id', 'left')
                ->where('d.dandori_date', $date)
                ->orderBy('m.line_position', 'ASC')
                ->get()->getResultArray();
        }

        // 5. Kelompokkan Data Dandori berdasarkan shift
        $map = [];
        foreach ($dandoriRecords as $d) {
            $map[$d['shift_id']][] = $d;
        }

        return view('machining/dandori/index', [
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
        $db = db_connect();
        $date = $this->request->getPost('date');
        $items = $this->request->getPost('items');

        if (empty($date)) {
            return redirect()->back()->with('error', 'Tanggal tidak valid.');
        }

        $db->transBegin();
        try {
            // Hapus data lama di tanggal tersebut (agar tersinkronisasi saat ada data yang dihapus dari UI)
            if ($db->tableExists('machining_dandori')) {
                $db->table('machining_dandori')->where('dandori_date', $date)->delete();
            }

            // Insert ulang data yang ada di form
            if (!empty($items) && is_array($items)) {
                $checkDuplicate = []; // Array untuk melacak duplikasi mesin & jam

                foreach ($items as $row) {
                    if (empty($row['shift_id']) || empty($row['machine_id']) || empty($row['product_id'])) continue;

                    $shiftId = (int)$row['shift_id'];
                    $machineId = (int)$row['machine_id'];
                    $productId = (int)$row['product_id'];
                    $timeSlotId = !empty($row['time_slot_id']) ? (int)$row['time_slot_id'] : null;

                    // Validasi Duplikasi: 1 Mesin tidak boleh ada 2 setup di jam yang sama pada shift yang sama
                    if ($timeSlotId) {
                        $duplicateKey = $shiftId . '_' . $machineId . '_' . $timeSlotId;
                        if (isset($checkDuplicate[$duplicateKey])) {
                            throw new \Exception("Duplikasi data terdeteksi! Mesin tidak dapat disetup 2 kali pada slot waktu yang sama.");
                        }
                        $checkDuplicate[$duplicateKey] = true;
                    }

                    $db->table('machining_dandori')->insert([
                        'dandori_date' => $date,
                        'shift_id'     => $shiftId,
                        'machine_id'   => $machineId,
                        'product_id'   => $productId,
                        'time_slot_id' => $timeSlotId,
                        'activity'     => $row['activity'] ?? 'Setup/Dandori Preparation',
                        'created_at'   => date('Y-m-d H:i:s')
                    ]);

                    // Generate form baru di daily schedule
                    $processIdRow = $db->table('production_processes')->where('process_name', 'Machining')->get()->getRowArray();
                    $processIdMC = $processIdRow ? (int)$processIdRow['id'] : 2;

                    $schedule = $db->table('daily_schedules')
                        ->where([
                            'schedule_date' => $date, 
                            'shift_id'      => $shiftId, 
                            'section'       => 'Machining',
                            'process_id'    => $processIdMC
                        ])->get()->getRowArray();

                    if (!$schedule) {
                        $db->table('daily_schedules')->insert([
                            'schedule_date' => $date,
                            'process_id'    => $processIdMC, 
                            'shift_id'      => $shiftId,
                            'section'       => 'Machining',
                            'is_completed'  => 0,
                            'created_at'    => date('Y-m-d H:i:s')
                        ]);
                        $scheduleId = (int)$db->insertID();
                    } else {
                        $scheduleId = (int)$schedule['id'];
                    }

                    $existItem = $db->table('daily_schedule_items')
                        ->where(['daily_schedule_id' => $scheduleId, 'machine_id' => $machineId, 'product_id' => $productId])
                        ->get()->getRowArray();

                    if (!$existItem) {
                        $db->table('daily_schedule_items')->insert([
                            'daily_schedule_id' => $scheduleId,
                            'shift_id'          => $shiftId,
                            'machine_id'        => $machineId,
                            'product_id'        => $productId,
                            'cycle_time'        => 0,
                            'cavity'            => 0,
                            'target_per_hour'   => 0,
                            'target_per_shift'  => 0,
                            'is_selected'       => 1
                        ]);
                    }
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('Terjadi kesalahan saat update database.');
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Jadwal Dandori Machining berhasil diperbarui.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}