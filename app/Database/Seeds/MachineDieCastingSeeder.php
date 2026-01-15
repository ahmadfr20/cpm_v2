<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachineDieCastingSeeder extends Seeder
{
    public function run()
    {
        $machines = [
            ['DC-09', 'Die Casting 09', 9],
            ['DC-10', 'Die Casting 10', 10],
            ['DC-11', 'Die Casting 11', 11],
            ['DC-12', 'Die Casting 12', 12],
            ['DC-13', 'Die Casting 13', 13],
            ['DC-14', 'Die Casting 14', 14],
        ];

        foreach ($machines as $m) {

            // Cek existing berdasarkan machine_code
            $exists = $this->db->table('machines')
                ->where('machine_code', $m[0])
                ->get()
                ->getRow();

            if (!$exists) {
                $this->db->table('machines')->insert([
                    'machine_code'    => $m[0],
                    'machine_name'    => $m[1],
                    'production_line' => 'Die Casting',
                    'process_id'      => 1,
                    'line_position'   => $m[2],
                ]);
            }
        }
    }
}
