<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeederV2 extends Seeder
{
    public function run()
    {
        $db = db_connect();

        // ✅ UBAH kalau customer_id Astra kamu bukan 1
        $astraCustomerId = 1;

        $now = date('Y-m-d H:i:s');

        // (Part No, Part Name) dari list kamu
        $rows = [
            ['HOUSING 0160', 'A1 Brother'],
            ['HOUSING 0160', 'A5 FANUC'],
            ['HOUSING 0160', 'A6 Brother'],
            ['H-7100', 'C-5 Brother'],
            ['H-7110', 'C-5 Brother'],
            ['H-9690', 'C-6 Brother'],
            ['H-9700', 'C-6 Brother'],
            ['H-9710', 'C-6 Brother'],
            ['H-9720', 'C-6 Brother'],
            ['H-7791', 'C-7 Brother'],
            ['H-0580', 'C-7 Brother'],
            ['H-0600', 'C-7 Brother'],
            ['H-9730', 'C-8 Brother'],
            ['H-9750', 'C-8 Brother'],
            ['H-7690 #2', 'C-8 Brother'],
            ['H-7700 #2', 'C-8 Brother'],
            ['H-7710', 'C-8 Brother'],
            ['H-7720', 'C-8 Brother'],
            ['Holder 7590', '-'],
            ['Holder 7600', '-'],
            ['Holder 7610', '-'],
            ['Holder 7620', '-'],
            ['Holder 9740', '-'],
            ['Holder 9760', '-'],
            ['Holder 0590', '-'],
            ['Holder 0610', '-'],
            ['Holder 1220', '-'],
            ['Holder 1230', '-'],
            ['Holder 1240', '-'],
            ['Holder 1250', '-'],
            ['Holder 1320', '-'],
            ['Holder 1330', '-'],
            ['Holder 1340', '-'],
            ['Holder 1350', '-'],
            ['Holder 1470', '-'],
            ['Holder 1481', '-'],
            ['Holder 1490', '-'],
            ['Holder 1501', '-'],
            ['Holder 1650', '-'],
            ['Holder 1660', '-'],
            ['Holder 1670', '-'],
            ['Holder 1680', '-'],
            ['Holder 1750', '-'],
            ['Holder 1760', '-'],
            ['Holder 1910', '-'],
            ['Holder 1920', '-'],
            ['Holder 2090', '-'],
            ['Holder 2110', '-'],
            ['Holder 2100', '-'],
            ['Holder 2120', '-'],
            ['Housing-1W 9710', 'D-3'],
            ['Housing-1W 9710', 'D-4'],
            ['Steering Shaft Base', 'B-5 Brother'],
            ['Handlebar Riser Clamp', 'A2 Brother'],
            ['Passenger Rear Handle', 'A2 Brother'],
            ['License Plate Holder', 'A2 Brother'],
            ['Motor Spacer /Adapter', 'A2 Brother'],
            ['Passenger Footpeg Left', 'A2 Brother'],
            ['Passenger Footpeg Right', 'A2 Brother'],
            ['Polo Shaft', 'B-5 Brother'],
            ['BOTTOM STROMA 1', 'EXTERNAL'],
            ['COVER STROMA 1', 'EXTERNAL'],
            ['SIDE CLIP', 'EXTERNAL'],
            ['STROMA X', 'EXTERNAL'],
            ['COVER STROMA X', 'EXTERNAL'],
            ['Intermediate Plate MSG JIG 1', 'B-5 Brother'],
            ['Intermediate Plate MSG JIG 2', 'B-6 Brother'],
            ['CGC 4JA-1', 'B-8 Brother'],
            ['Thermostat Housing (Jig-1)', 'C-3 Brother'],
            ['Thermostat Housing (Jig-2)', 'C-3 Brother'],
            ['Duct H Thermostat  + Leak Test', 'C-3 Brother'],
            ['Duct Asm + Leak Test + Assy', 'C-3 Brother'],
            ['JIG 1 BRACKET ACG', 'C-3 Brother'],
            ['JIG 2 BRACKET ACG', 'C-3 Brother'],
            ['Carriage FT-2 + Assy', 'B-1 Brother'],
            ['Carriage Lotus 2+ Assy', 'B-2 Brother'],
            ['APV + LEAK TEST', 'B-8 Brother'],
            ['CWT YL-8', 'D-6 BUBUT'],
            ['CWO', 'D-6 BUBUT'],
            ['CWT-79100', 'D-6 BUBUT'],
            ['CWT-73000', 'D-6 BUBUT'],
            ["Case Comp '(OP-4-5-6)", 'C-9  Brother'],
            ['Tank LW', 'B-8 Brother'],
            ['Cover LW', 'B-8 Brother'],
            ['Thermostat Euro', 'A2 Brother'],
        ];

        // ✅ Dedupe internal list (kalau ada dobel di array)
        $unique = [];
        foreach ($rows as $r) {
            $partNo   = trim((string)$r[0]);
            $partName = trim(preg_replace('/\s+/', ' ', (string)$r[1])); // rapikan spasi
            $key = mb_strtolower($partNo . '|' . $partName);

            $unique[$key] = [$partNo, $partName];
        }

        $builder = $db->table('products');

        $inserted = 0;
        $skipped  = 0;

        foreach ($unique as [$partNo, $partName]) {
            // ✅ Skip kalau sudah ada part_no + part_name yang sama
            $exists = $builder
                ->select('id')
                ->where('part_no', $partNo)
                ->where('part_name', $partName)
                ->limit(1)
                ->get()
                ->getRowArray();

            if ($exists) {
                $skipped++;
                continue;
            }

            $data = [
                'part_no'         => $partNo,
                'part_name'       => $partName,
                'customer_id'     => $astraCustomerId,
                'weight_ascas'    => mt_rand(1, 1000),
                'weight_runner'   => mt_rand(1, 1000),
                'cycle_time'      => 40,
                'cavity'          => 2,
                'efficiency_rate' => 100,
                'notes'           => null,
                'is_active'       => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            $builder->insert($data);
            $inserted++;
        }

        echo "ProductsAstraSeeder selesai. Inserted={$inserted}, Skipped={$skipped}\n";
    }
}
