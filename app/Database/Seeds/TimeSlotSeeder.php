<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['time_code' => '01', 'time_start' => '08:00:00', 'time_end' => '09:00:00'],
            ['time_code' => '02', 'time_start' => '09:00:00', 'time_end' => '10:00:00'],
            ['time_code' => '03', 'time_start' => '10:00:00', 'time_end' => '11:00:00'],
            ['time_code' => '04', 'time_start' => '11:00:00', 'time_end' => '12:00:00'],
            ['time_code' => '05', 'time_start' => '13:00:00', 'time_end' => '14:00:00'],
            ['time_code' => '06', 'time_start' => '14:00:00', 'time_end' => '15:00:00'],
        ];

        $this->db->table('time_slots')->insertBatch($data);
    }
}
