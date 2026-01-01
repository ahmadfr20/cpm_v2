<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeeder extends Seeder
{
public function run()
{
    $customers = $this->db->table('customers')->get()->getResultArray();

    $customerMap = [];
    foreach ($customers as $c) {
        $customerMap[$c['customer_name']] = $c['id'];
    }

    $products = [
        [
            'part_no' => 'PT-2314511',
            'part_name' => 'Bracket Engine',
            'customer_id' => $customerMap['PT Astra Otoparts'],
            'weight' => 1.25,
            'notes' => 'High volume part'
        ],
        [
            'part_no' => 'PT-2314513',
            'part_name' => 'Engine Mount RH',
            'customer_id' => $customerMap['PT Astra Otoparts'],
            'weight' => 2.10,
            'notes' => 'Critical safety part'
        ],
        [
            'part_no' => 'DS-4411201',
            'part_name' => 'Compressor Housing',
            'customer_id' => $customerMap['PT Denso Indonesia'],
            'weight' => 3.75,
            'notes' => 'Tight tolerance machining'
        ],
        [
            'part_no' => 'INT-10001',
            'part_name' => 'Transmission Case',
            'customer_id' => $customerMap['PT Astra Otoparts'],
            'weight' => 6.50,
            'notes' => 'Large die casting part'
        ],
    ];

    foreach ($products as $p) {
        $exists = $this->db->table('products')
            ->where('part_no', $p['part_no'])
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('products')->insert($p);
        }
    }
}

}
