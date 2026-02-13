<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TimeSlotsSeeder extends Seeder
{
    public function run()
    {
        $table = $this->db->table('time_slots');

        // Semua slot dari seluruh pattern (tanpa slot istirahat minutes=0, tanpa baris total)
        // Format: [start, end]
        $slots = [
            // ===== Pattern (390) =====
            ['07:30:00','08:15:00'],
            ['08:15:00','09:15:00'],
            ['09:15:00','10:15:00'],
            ['10:15:00','11:15:00'],
            ['11:15:00','11:45:00'],
            ['12:45:00','13:45:00'],
            ['13:45:00','14:45:00'],
            ['14:45:00','15:00:00'],

            ['15:30:00','16:15:00'],
            ['16:15:00','17:15:00'],
            ['17:15:00','18:15:00'],
            ['18:15:00','18:30:00'],
            ['19:30:00','20:30:00'],
            ['20:30:00','21:30:00'],
            ['21:30:00','22:30:00'],
            ['22:30:00','23:00:00'],

            ['23:30:00','00:15:00'],
            ['00:15:00','01:15:00'],
            ['01:15:00','02:15:00'],
            ['02:15:00','03:15:00'],
            ['03:15:00','04:00:00'],
            ['04:00:00','05:00:00'],
            ['05:00:00','06:00:00'],
            ['06:00:00','07:00:00'],

            // ===== Pattern (405) =====
            ['07:20:00','08:15:00'],
            ['14:45:00','15:05:00'],
            ['15:20:00','16:15:00'],
            ['22:30:00','23:05:00'],
            ['23:20:00','00:15:00'],
            ['06:00:00','07:05:00'],

            // ===== Pattern (390) alt =====
            ['13:00:00','14:00:00'],
            ['14:00:00','15:00:00'],
            ['15:00:00','15:15:00'],

            ['15:45:00','16:30:00'],
            ['16:30:00','17:30:00'],
            ['17:30:00','18:30:00'],
            ['22:30:00','23:15:00'],

            ['23:45:00','00:30:00'],
            ['00:30:00','01:30:00'],
            ['01:30:00','02:30:00'],
            ['02:30:00','03:30:00'],
            ['03:30:00','04:00:00'],
            ['06:00:00','07:15:00'],

            // ===== Pattern (405) alt =====
            ['15:00:00','15:20:00'],
            ['15:35:00','16:30:00'],
            ['22:30:00','23:20:00'],
            ['23:35:00','00:30:00'],
            ['07:00:00','07:20:00'],

            // ===== Pattern (270) =====
            ['07:45:00','08:30:00'],
            ['08:30:00','09:30:00'],
            ['09:30:00','10:30:00'],
            ['10:30:00','11:30:00'],
            ['11:30:00','12:15:00'],

            ['12:45:00','13:30:00'],
            ['13:30:00','14:30:00'],
            ['14:30:00','15:30:00'],
            ['15:30:00','16:30:00'],
            ['16:30:00','17:15:00'],

            ['17:45:00','18:30:00'],
            ['18:30:00','19:30:00'],
            ['19:30:00','20:30:00'],
            ['20:30:00','21:30:00'],
            ['21:30:00','22:15:00'],

            // ===== Pattern (285) =====
            ['07:35:00','08:30:00'],
            ['11:30:00','12:20:00'],
            ['12:35:00','13:30:00'],
            ['16:30:00','17:20:00'],
            ['17:35:00','18:30:00'],
            ['21:30:00','22:20:00'],
        ];

        // Unikkan berdasarkan start-end
        $unique = [];
        foreach ($slots as $s) {
            $unique[$s[0] . '|' . $s[1]] = $s;
        }
        $uniqueSlots = array_values($unique);

        // Sort rapi
        usort($uniqueSlots, function ($a, $b) {
            if ($a[0] === $b[0]) return strcmp($a[1], $b[1]);
            return strcmp($a[0], $b[0]);
        });

        // Ambil existing slot untuk cek duplikasi cepat
        $existing = $this->db->table('time_slots')
            ->select('id, time_start, time_end')
            ->get()
            ->getResultArray();

        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[$row['time_start'] . '|' . $row['time_end']] = $row['id'];
        }

        // Tentukan nomor time_code berikutnya (TS-001 dst)
        $maxCode = $this->db->table('time_slots')
            ->select('MAX(CAST(SUBSTRING(time_code, 4) AS UNSIGNED)) AS max_no')
            ->like('time_code', 'TS-', 'after')
            ->get()
            ->getRowArray();

        $nextNo = (int)($maxCode['max_no'] ?? 0) + 1;

        $insertBatch = [];

        foreach ($uniqueSlots as $s) {
            $key = $s[0] . '|' . $s[1];

            // kalau sudah ada, skip
            if (isset($existingMap[$key])) {
                continue;
            }

            $insertBatch[] = [
                'time_code'  => 'TS-' . str_pad((string)$nextNo, 3, '0', STR_PAD_LEFT),
                'time_start' => $s[0],
                'time_end'   => $s[1],
            ];
            $nextNo++;
        }

        if (!empty($insertBatch)) {
            $table->insertBatch($insertBatch);
        }
    }
}
