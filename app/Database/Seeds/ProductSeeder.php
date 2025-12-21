<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'part_no'     => 'PT-2314511',
                'part_name'   => 'Bracket Engine',
                'customer_id' => 1,
                'weight'      => 1.25,
                'notes'       => 'High volume part'
            ],
            [
                'part_no'     => 'PT-2314512',
                'part_name'   => 'Housing Gear',
                'customer_id' => 2,
                'weight'      => 2.40,
                'notes'       => 'Critical dimension'
            ]
        ];

        $this->db->table('products')->insertBatch($data);
    }
}
