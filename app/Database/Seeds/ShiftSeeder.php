<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['shift_code' => '2', 'shift_name' => 'Shift 2'],
            ['shift_code' => '3', 'shift_name' => 'Shift 3'],
            ['shift_code' => '4', 'shift_name' => 'Shift 4'],
        ];

        $this->db->table('shifts')->insertBatch($data);
    }
}
