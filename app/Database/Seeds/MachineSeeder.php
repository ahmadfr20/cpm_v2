<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run()
    {
        $machines = [];

        // =========================
        // DIE CASTING DC-01 s/d DC-08
        // =========================
        for ($i = 1; $i <= 8; $i++) {
            $code = 'DC-' . str_pad($i, 2, '0', STR_PAD_LEFT);

            $machines[] = [
                'machine_code'    => $code,
                'machine_name'    => 'Die Casting ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'production_line' => 'Die Casting'
            ];
        }

        // =========================
        // INSERT AMAN (CEK DUPLIKAT)
        // =========================
        foreach ($machines as $m) {
            $exists = $this->db->table('machines')
                ->where('machine_code', $m['machine_code'])
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('machines')->insert($m);
            }
        }
    }
}
