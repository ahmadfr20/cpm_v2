<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachineProductsMatrixSeeder extends Seeder
{
    public function run()
    {
        /**
         * MATRIX: MACHINE_CODE => PART_NO[]
         * HANYA PART_NO YANG ADA DI TABEL products
         */
        $matrix = [

            // ================= DC-06 =================
            'DC-06' => [
                'H-1320','H-1330','H-1340','H-1350','H-1220','H-1230',
                'H-1240','H-1250','H-7690 #2','H-9690','H-1481','H-1501',
                'H-1910','H-1920','H-0590','H-0610','H-0580 #2','H-0600 #2',
                'H-7700 #2','H-7720 #2','H-1650','H-1660','H-1670',
                'H-1680','H-7710','H-1750','H-1760','H-9700','H-9710',
                'H-9720','H-9730','H-9740','H-9750','H-9760 #2'
            ],

            // ================= DC-07 =================
            'DC-07' => [
                'H-1220','H-1230','H-1240','H-1250','H-1320','H-1330',
                'H-1340','H-1350','H-1670','H-7610 #2','H-7620 #2',
                'H-7600 #2','H-7590 #2','H-9700','H-9720','H-9730',
                'H-9750','H-1481','H-1501','H-0590','H-1910',
                'H-1920','H-7110','H-7700 #2','H-7720 #2'
            ],

            // ================= DC-08 =================
            'DC-08' => [
                'H-7100','H-7700 #2','H-7720 #2','H-0580 #2','H-0590',
                'H-0600 #2','H-0610','H-1750','H-1760','H-1910',
                'H-1920','H-9750','CWO','Plate MSG','H-1220','H-1230',
                'H-1240','H-1250','H-1320','H-1330','H-1340','H-1350',
                'H-9700','H-7690 #2','H-7110'
            ],

            // ================= DC-09 =================
            'DC-09' => [
                'Carriage Bamboo 2','Carriage FT-2 #1','Carriage FT-2 #2',
                'Bracket YR-9 #2','H-1470','H-1490','H-1650','H-1660',
                'H-1670','H-1680','H-9740','H-9760 #2','H-1481',
                'H-1501','H-7791 #2','H-7720 #2','H-7700 #2'
            ],

            // ================= DC-12 =================
            'DC-12' => [
                'Crank Case','Cover LW','Tank LWN','CGC 4JA-1',
                'Rear Handle Seat','Blok SND 01','Blok SND 02',
                'Bottom Stroma -1','Cover Stroma-1','Body StromaX',
                'Cover StromaX'
            ],

            // ================= DC-13 =================
            'DC-13' => [
                'Housing-1W #4','Housing-5050 #2','Thermostat Housing',
                'Duct Thermostat','Duct Asm','H-1470','H-1490',
                'Bracket ASM Generator','H-0600 #2','H-0610',
                'H-0580 #2','H-0590','H-1650','H-1660','H-1670',
                'H-1680','H-1320','H-1340','H-1350','H-7610 #2',
                'H-7620 #2','H-7700 #2','H-7720 #2','Housing-5050 #1',
                'H-7590 #2','H-9710','H-9720','H-1330'
            ],

            // ================= DC-14 =================
            'DC-14' => [
                'Housing-5050 #1','Housing-5050 #2','Thermostat Housing',
                'H-1470','Duct Asm','Duct Thermostat','H-1490',
                'H-1650','H-1660','H-1670','H-1680','H-0580 #2',
                'H-0590','H-0610','Steering Shaft','H-1350','H-1340',
                'H-1330','H-1320','Bracket ASM Generator','H-7590 #2',
                'H-7600 #2','H-7620 #2','H-1910','H-1920',
                'H-9710','Housing-1W #6','Thermostat TD'
            ],
        ];

        foreach ($matrix as $machineCode => $parts) {

            $machine = $this->db->table('machines')
                ->where('machine_code', $machineCode)
                ->get()
                ->getRow();

            if (!$machine) continue;

            foreach ($parts as $partNo) {

                $product = $this->db->table('products')
                    ->where('part_no', $partNo)
                    ->get()
                    ->getRow();

                if (!$product) continue;

                $exists = $this->db->table('machine_products')
                    ->where([
                        'machine_id' => $machine->id,
                        'product_id' => $product->id
                    ])
                    ->get()
                    ->getRow();

                if (!$exists) {
                    $this->db->table('machine_products')->insert([
                        'machine_id' => $machine->id,
                        'product_id' => $product->id,
                        'is_active'  => 1
                    ]);
                }
            }
        }
    }
}
