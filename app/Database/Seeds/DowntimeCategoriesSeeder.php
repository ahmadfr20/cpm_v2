<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DowntimeCategoriesSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        
        // Coba cari ID dari tabel production_processes untuk DC (Die Casting) dan MC (Machining)
        $dc = $db->table('production_processes')->where('process_code', 'DC')->get()->getRow();
        $mc = $db->table('production_processes')->where('process_code', 'MC')->get()->getRow();

        // Jika tidak ketemu di DB, fallback ke ID 1 dan 2
        $dcId = $dc ? $dc->id : 1;
        $mcId = $mc ? $mc->id : 2;

        $data = [
            // --- DIE CASTING DOWNTIME ---
            [
                'process_id'    => $dcId,
                'downtime_code' => 10,
                'downtime_name' => 'Set Up Mold / Dies',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'process_id'    => $dcId,
                'downtime_code' => 11,
                'downtime_name' => 'Ganti Material / Ingot',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'process_id'    => $dcId,
                'downtime_code' => 12,
                'downtime_name' => 'Mesin Alarm / Trouble',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],

            // --- MACHINING DOWNTIME ---
            [
                'process_id'    => $mcId,
                'downtime_code' => 20,
                'downtime_name' => 'Set Up JIG & Tools',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'process_id'    => $mcId,
                'downtime_code' => 21,
                'downtime_name' => 'Ganti Insert / Mata Bor',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'process_id'    => $mcId,
                'downtime_code' => 22,
                'downtime_name' => 'Mesin CNC Alarm',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
        ];

        $db->table('downtime_categories')->insertBatch($data);
    }
}