<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductionStandardsSeeder extends Seeder
{
    public function run()
    {
        // Mesin yang akan di-generate standard-nya
        $machineCodes = [
            'DC-06',
            'DC-07',
            'DC-08',
            'DC-12',
            'DC-13',
            'DC-14',
        ];

        foreach ($machineCodes as $code) {

            // Ambil mesin
            $machine = $this->db->table('machines')
                ->where('machine_code', $code)
                ->get()
                ->getRow();

            if (!$machine) {
                continue;
            }

            // Ambil semua product yang aktif di mesin tsb
            $products = $this->db->table('machine_products mp')
                ->select('mp.product_id')
                ->where('mp.machine_id', $machine->id)
                ->where('mp.is_active', 1)
                ->get()
                ->getResult();

            foreach ($products as $p) {

                // Cek apakah standard sudah ada
                $exists = $this->db->table('production_standards')
                    ->where([
                        'machine_id' => $machine->id,
                        'product_id' => $p->product_id,
                    ])
                    ->get()
                    ->getRow();

                if ($exists) {
                    continue;
                }

                // Insert standard default
                $this->db->table('production_standards')->insert([
                    'machine_id'     => $machine->id,
                    'product_id'     => $p->product_id,
                    'cycle_time_sec' => 20,  // default awal
                    'cavity'         => 2,   // default awal
                    'effective_rate' => 1,   // 100%
                ]);
            }
        }
    }
}
