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
                foreach ($items as $row) {
                    if (empty($row['shift_id']) || empty($row['machine_id']) || empty($row['product_id'])) continue;

                    $db->table('machining_dandori')->insert([
                        'dandori_date' => $date,
                        'shift_id'     => (int)$row['shift_id'],
                        'machine_id'   => (int)$row['machine_id'],
                        'product_id'   => (int)$row['product_id'],
                        'time_slot_id' => !empty($row['time_slot_id']) ? (int)$row['time_slot_id'] : null,
                        'activity'     => $row['activity'] ?? 'Setup/Dandori Preparation',
                        'created_at'   => date('Y-m-d H:i:s')
                    ]);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('Terjadi kesalahan saat update database.');
            $db->transCommit();

            return redirect()->back()->with('success', 'Jadwal Dandori Machining berhasil diperbarui.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}