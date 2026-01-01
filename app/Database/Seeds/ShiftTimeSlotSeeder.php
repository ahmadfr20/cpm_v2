<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ShiftTimeSlotSeeder extends Seeder
{
    public function run()
    {
        // Ambil shift berdasarkan shift_code
        $shiftRows = $this->db->table('shifts')
            ->whereIn('shift_code', ['1','2','3'])
            ->get()->getResultArray();

        if (count($shiftRows) < 3) {
            throw new \Exception('Shift 1–3 belum lengkap di tabel shifts');
        }

        // Map shift_code => shift_id
        $shiftIds = [];
        foreach ($shiftRows as $row) {
            $shiftIds[$row['shift_code']] = $row['id'];
        }

        // Range waktu per shift (sesuai CPM)
        $ranges = [
            '1' => [['07:20','15:20']],
            '2' => [['12:35','23:20']],
            '3' => [['23:20','07:20']], // night shift
        ];

        $timeSlots = $this->db->table('time_slots')->get()->getResultArray();

        foreach ($ranges as $shiftCode => $timeRanges) {

            $shiftId = $shiftIds[$shiftCode];

            foreach ($timeSlots as $ts) {
                foreach ($timeRanges as $r) {

                    // SHIFT 3 (lintas hari)
                    if ($shiftCode === '3') {
                        if (
                            $ts['time_start'] >= $r[0] ||
                            $ts['time_end'] <= $r[1]
                        ) {
                            $this->db->table('shift_time_slots')->insert([
                                'shift_id' => $shiftId,
                                'time_slot_id' => $ts['id']
                            ]);
                        }
                    }
                    // SHIFT 1 & 2
                    else {
                        if (
                            $ts['time_start'] >= $r[0] &&
                            $ts['time_end'] <= $r[1]
                        ) {
                            $this->db->table('shift_time_slots')->insert([
                                'shift_id' => $shiftId,
                                'time_slot_id' => $ts['id']
                            ]);
                        }
                    }
                }
            }
        }
    }
}
