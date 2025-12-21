<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'customer_code' => 'CUS001',
                'customer_name' => 'PT Astra Otoparts',
                'address'       => 'Jakarta',
                'phone'         => '08123456789',
                'email'         => 'budi@astra.co.id'
            ],
            [
                'customer_code' => 'CUS002',
                'customer_name' => 'PT Denso Indonesia',
                'address'       => 'Jakarta',
                'phone'         => '08129876543',
                'email'         => 'andi@denso.co.id'
            ]
        ];

        $this->db->table('customers')->insertBatch($data);
    }
}
