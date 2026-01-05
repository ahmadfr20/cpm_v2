<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductionStandardSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        // default standard
        $cycleTime = 40;
        $cavity = 2;
        $effectiveRate = 1.00;

        // mapping machine => product part_no
        $data = [
            'DC-01' => ['PT-2314511','PT-2314512','PT-2314513','DS-4411201','INT-10001'],
            'DC-02' => ['PT-2314511','PT-2314512','PT-2314513','INT-10001'],
            'DC-03' => ['PT-2314511','PT-2314512','PT-2314513','DS-4411201','INT-10001'],
            'DC-04' => ['PT-2314511','PT-2314512'],
            'DC-05' => ['PT-2314511','PT-2314512','PT-2314513','INT-10001'],
            'DC-06' => ['PT-2314513','DS-4411201','INT-10001'],
            'DC-07' => ['PT-2314511','PT-2314512','PT-2314513'],
            'DC-08' => ['PT-2314511','PT-2314512','DS-4411201'],
            'MC-01' => ['PT-2314511','PT-2314512','DS-4411201','INT-10001'],
        ];

        foreach ($data as $machineCode => $parts) {

            $machine = $db->table('machines')
                ->where('machine_code', $machineCode)
                ->get()
                ->getRowArray();

            if (!$machine) {
                continue;
            }

            foreach ($parts as $partNo) {

                $product = $db->table('products')
                    ->where('part_no', $partNo)
                    ->get()
                    ->getRowArray();

                if (!$product) {
                    continue;
                }

                // cek duplicate (important)
                $exists = $db->table('production_standards')
                    ->where('machine_id', $machine['id'])
                    ->where('product_id', $product['id'])
                    ->countAllResults();

                if ($exists > 0) {
                    continue;
                }

                $db->table('production_standards')->insert([
                    'machine_id'      => $machine['id'],
                    'product_id'      => $product['id'],
                    'cycle_time_sec'  => $cycleTime,
                    'cavity'          => $cavity,
                    'effective_rate'  => $effectiveRate,
                    'created_at'      => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
}
