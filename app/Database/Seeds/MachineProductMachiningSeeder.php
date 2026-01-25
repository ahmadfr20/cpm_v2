<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachineProductMachiningSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        /**
         * FORMAT:
         * 'MACHINE NAME' => [
         *      'PRODUCT NAME',
         *      'PRODUCT NAME',
         * ]
         */
        $mapping = [

            'TC S2BO D' => [
                'Housing 0160',
            ],

            'TC S2BO A' => [
                'Housing 0160',
            ],

            'FANUC DT+' => [
                'Housing 0160',
            ],

            'TC S2BOF' => [
                'Housing 0160',
            ],

            '-' => [
                'Leak Test',
                'Plug',
            ],

            'FANUC RD' => [
                'Carriage FT-2',
                'Carriage Lotus 2',
                'Body Stroma X',
            ],

            'TC S2BO I' => [
                'Carriage Bamboo 2',
            ],

            'Assy Part' => [
                'Assy Bushing Bamboo-2',
                'Assy Bushing Lotus-2',
                'Assy Shaft Lotus-2',
                'Assy Bushing FT-2',
            ],

            'Leak Test' => [
                'Assy Union Brother',
                'Leak Test Case Comp',
            ],

            'TC S2BO E' => [
                'Steering Shaft Base',
                'Handlebar Riser Clamp',
                'Passenger Rear Handle',
                'License Plate Bracket',
                'Intermediate Plate MSG',
            ],

            'TC S2BO C' => [
                'Body Stroma X',
                'Bottom-S1 (OP-1-2)',
                'Cover-S1 (OP-1-2)',
                'Cover-S1 (OP-3-4)',
                'Clip Side',
                'Polo Shaft',
                'Motor Spacer / Adapter',
                'Passenger Footpeg Left',
                'Passenger Footpeg Right',
                'Intermediate Plate MSG',
            ],

            'TCC 1000 B' => [
                'Hub Engine',
            ],

            'TC S2BO G' => [
                'CGSL APV',
                'CGC 4JA-1',
                'CGC 4JB-1',
                'Leak Test Pump Body',
                'Leak Test Water Outlet',
                'BRKT 71 LGO (Jig-1)',
                'BRKT 71 LGO (Jig-2)',
                'Tank LW',
                'Cover LW',
            ],

            'TC S2BO J' => [
                'Thermostat TD (Jig-1)',
                'Thermostat TD (Jig-2)',
                'Thermostat Housing (Jig-1)',
                'Thermostat Housing (Jig-2)',
            ],

            'TC S2D B' => [
                'JIG 1 BRACKET ASM GENERATOR',
                'JIG 2 BRACKET ASM GENERATOR',
            ],

            'TC S2AO' => [
                'H-7080',
                'H-7090',
                'H-7100',
                'H-7110',
                'Facing H-7791',
            ],

            'TC S2B' => [
                'H-9690',
                'H-9700',
                'H-9710',
                'H-9720',
                'H-5561',
            ],

            'TC S2BO B' => [
                'H-8440',
                'H-8450',
                'H-7791',
                'H-0580',
                'H-0600',
            ],

            'TC S2DN A' => [
                'H-9730',
                'H-9750',
                'H-7690',
                'H-7700',
                'H-7710',
                'H-7720',
            ],

            'Brother TC S2DN B' => [
                'Pump Body OP1',
                'Pump Body OP2',
                'Pump Cover',
                'Water Outlet',
                'Case Comp Thermo',
            ],

            'Brother TC S2DN C' => [
                'Case Comp Thermo',
            ],

            'Brother TC R2B' => [
                'Housing-1W',
                'Bracket YR-9',
            ],

            'TC S2BO H' => [
                'Housing-1W',
            ],

            'TC S2D A' => [
                'Housing-1W',
            ],

            'TC 200' => [
                'CWT-YL8',
                'CWO',
                'CWT-79100',
                'CWT-73000',
                'Plug Cyl Block',
            ],
        ];

        foreach ($mapping as $machineName => $products) {

            $machines = $db->table('machines')
                ->where('machine_name', $machineName)
                ->get()
                ->getResultArray();

            if (!$machines) {
                echo "⚠️ Machine not found: {$machineName}\n";
                continue;
            }

            foreach ($machines as $machine) {

                foreach ($products as $productName) {

                    $product = $db->table('products')
                        ->like('part_name', $productName)
                        ->get()
                        ->getRowArray();

                    if (!$product) {
                        echo "⚠️ Product not found: {$productName}\n";
                        continue;
                    }

                    $exist = $db->table('machine_products')
                        ->where([
                            'machine_id' => $machine['id'],
                            'product_id' => $product['id'],
                        ])
                        ->get()
                        ->getRowArray();

                    if ($exist) continue;

                    $db->table('machine_products')->insert([
                        'machine_id' => $machine['id'],
                        'product_id' => $product['id'],
                        'is_active'  => 1,
                    ]);
                }
            }
        }

        echo "✅ Machine → Product mapping seeded successfully\n";
    }
}
