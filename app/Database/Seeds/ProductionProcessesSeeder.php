<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductionProcessesSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['process_code' => 'DC', 'process_name' => 'Die Casting'],
            ['process_code' => 'MC', 'process_name' => 'Machining'],
            ['process_code' => 'RM', 'process_name' => 'RAW MATERIAL'],
            ['process_code' => 'BT', 'process_name' => 'BURRYTORY'],
            ['process_code' => 'SB', 'process_name' => 'SAND BLASTING'],
            ['process_code' => 'LT', 'process_name' => 'LEAK TEST'],
            ['process_code' => 'JP', 'process_name' => 'JIG PLUG'],
            ['process_code' => 'AB', 'process_name' => 'ASSY BUSHING'],
            ['process_code' => 'AS', 'process_name' => 'ASSY SHAFT'],
            ['process_code' => 'PT', 'process_name' => 'PAINTING'],
            ['process_code' => 'FI', 'process_name' => 'FINAL INSPECTION'],
            ['process_code' => 'FG', 'process_name' => 'FINISHED GOOD'],
        ];

        // Biar aman kalau seed dijalankan berulang: hapus dulu berdasarkan process_code
        $codes = array_column($data, 'process_code');
        $this->db->table('production_processes')->whereIn('process_code', $codes)->delete();

        $this->db->table('production_processes')->insertBatch($data);
    }
}
