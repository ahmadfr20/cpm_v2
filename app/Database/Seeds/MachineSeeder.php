<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'machine_code'    => 'DC-01',
                'machine_name'    => 'Die Casting 01',
                'production_line' => 'Die Casting'
            ],
            [
                'machine_code'    => 'DC-02',
                'machine_name'    => 'Die Casting 02',
                'production_line' => 'Die Casting'
            ],
            [
                'machine_code'    => 'MC-01',
                'machine_name'    => 'Machining Center 01',
                'production_line' => 'Machining'
            ]
        ];

        $this->db->table('machines')->insertBatch($data);
    }
}
