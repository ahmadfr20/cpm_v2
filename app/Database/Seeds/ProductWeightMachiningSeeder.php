<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductWeightMachiningSeeder extends Seeder
{
    public function run()
    {
        // Data mapping akurat berdasarkan Part No di Database
        $data = [
            ['part_no' => 'AE159878-4030', 'weight_machining' => 0.33],
            ['part_no' => 'AE159878-4490', 'weight_machining' => 0.105],
            ['part_no' => 'AE159878-4510', 'weight_machining' => 0.105],
            ['part_no' => 'AE159878-5090', 'weight_machining' => 0.127],
            ['part_no' => 'AE159878-5130', 'weight_machining' => 0.14],
            ['part_no' => 'AE159878-5140', 'weight_machining' => 0.13],
            ['part_no' => 'AE159878-5571', 'weight_machining' => 0.171],
            ['part_no' => 'AE159878-7080', 'weight_machining' => 0.107],
            ['part_no' => 'AE159878-7090', 'weight_machining' => 0.165],
            ['part_no' => 'AE159878-7790', 'weight_machining' => 0.114],
            ['part_no' => 'AE159878-8440', 'weight_machining' => 0.365],
            ['part_no' => 'AE159878-8450', 'weight_machining' => 0.125],
            ['part_no' => 'AE159878-7100', 'weight_machining' => 0.105],
            ['part_no' => 'AE159878-7110', 'weight_machining' => 0.16],
            ['part_no' => 'AE159878-7690', 'weight_machining' => 0.085],
            ['part_no' => 'AE159878-7700', 'weight_machining' => 0.375],
            ['part_no' => 'AE159878-7710', 'weight_machining' => 0.085],
            ['part_no' => 'AE159878-7720', 'weight_machining' => 0.375],
            ['part_no' => 'AE159878-7791', 'weight_machining' => 0.115],
            ['part_no' => 'AE159879-7590', 'weight_machining' => 0.145],
            ['part_no' => 'AE159879-7600', 'weight_machining' => 0.13],
            ['part_no' => 'AE159879-7610', 'weight_machining' => 0.145],
            ['part_no' => 'AE159879-7620', 'weight_machining' => 0.13],
            ['part_no' => 'AE159878-9690', 'weight_machining' => 0.135],
            ['part_no' => 'AE159878-9700', 'weight_machining' => 0.18],
            ['part_no' => 'AE159878-9710', 'weight_machining' => 0.135],
            ['part_no' => 'AE159878-9720', 'weight_machining' => 0.18],
            ['part_no' => 'AE159879-9730', 'weight_machining' => 0.12],
            ['part_no' => 'AE159879-9740', 'weight_machining' => 0.41],
            ['part_no' => 'AE159879-9750', 'weight_machining' => 0.125],
            ['part_no' => 'AE159879-9760', 'weight_machining' => 0.41],
            ['part_no' => 'AE159888-0580', 'weight_machining' => 0.355],
            ['part_no' => 'AE159889-0590', 'weight_machining' => 0.115],
            ['part_no' => 'AE159888-0600', 'weight_machining' => 0.36],
            ['part_no' => 'AE159889-0610', 'weight_machining' => 0.115],
            ['part_no' => 'AE159889-1220', 'weight_machining' => 0.155],
            ['part_no' => 'AE159889-1230', 'weight_machining' => 0.13],
            ['part_no' => 'AE159889-1240', 'weight_machining' => 0.155],
            ['part_no' => 'AE159889-1250', 'weight_machining' => 0.13],
            ['part_no' => 'AE159889-1320', 'weight_machining' => 0.085],
            ['part_no' => 'AE159889-1330', 'weight_machining' => 0.085],
            ['part_no' => 'AE159889-1340', 'weight_machining' => 0.09],
            ['part_no' => 'AE159889-1350', 'weight_machining' => 0.09],
            ['part_no' => 'AE159889-1470', 'weight_machining' => 0.095],
            ['part_no' => 'AE159889-1481', 'weight_machining' => 0.09],
            ['part_no' => 'AE159889-1490', 'weight_machining' => 0.095],
            ['part_no' => 'AE159889-1501', 'weight_machining' => 0.09],
            ['part_no' => 'AE159889-1650', 'weight_machining' => 0.085],
            ['part_no' => 'AE159889-1660', 'weight_machining' => 0.085],
            ['part_no' => 'AE159889-1670', 'weight_machining' => 0.1],
            ['part_no' => 'AE159889-1680', 'weight_machining' => 0.1],
            ['part_no' => 'AE159889-1750', 'weight_machining' => 0.12],
            ['part_no' => 'AE159889-1760', 'weight_machining' => 0.13],
            ['part_no' => 'AE159889-1910', 'weight_machining' => 0.13],
            ['part_no' => 'AE159889-1920', 'weight_machining' => 0.125],
            ['part_no' => 'AE159889-2090', 'weight_machining' => 0.19],
            ['part_no' => 'AE159889-2100', 'weight_machining' => 0.19],
            ['part_no' => 'AE159889-2110', 'weight_machining' => 0.19],
            ['part_no' => 'AE159889-2120', 'weight_machining' => 0.19],
            ['part_no' => 'AE059111-9710C', 'weight_machining' => 0.165],
            ['part_no' => '17690-52S00-0001', 'weight_machining' => 0.205],
            ['part_no' => '17690-52S00-000S', 'weight_machining' => 0.205],
            ['part_no' => '25121-60K00-000S', 'weight_machining' => 0.385],
            ['part_no' => '25121-60K00-000', 'weight_machining' => 0.385],
            ['part_no' => '17570-80C00', 'weight_machining' => 0.075],
            ['part_no' => '11749-68K01-000', 'weight_machining' => 0.94],
            ['part_no' => '11751-74LA0-000S', 'weight_machining' => 1.344],
            ['part_no' => '17561-79100-000', 'weight_machining' => 0.088],
            ['part_no' => '17561-73000-000S', 'weight_machining' => 0.086],
            ['part_no' => '17570-80C00-000S', 'weight_machining' => 0.075],
            ['part_no' => '17131-80000-000S', 'weight_machining' => 0.06],
            ['part_no' => 'MRM-8973064312', 'weight_machining' => 1.455],
            ['part_no' => 'MRM- 8975208290', 'weight_machining' => 0.405],
            ['part_no' => 'MRM - 8975208290', 'weight_machining' => 0.405],
            ['part_no' => 'MRM-8975208300DOM', 'weight_machining' => 0.425],
            ['part_no' => 'MRM-8975208300EXP', 'weight_machining' => 0.425],
            ['part_no' => 'MRM-8980826151', 'weight_machining' => 2.06],
            ['part_no' => 'MRM-8971702040', 'weight_machining' => 1.085],
            ['part_no' => '10-FT-1X008-01-03', 'weight_machining' => 0.299],
            ['part_no' => '10-FT-1X011-01-03', 'weight_machining' => 0.07],
            ['part_no' => '40-FR-1X013-01-05', 'weight_machining' => 0.19],
            ['part_no' => '40-FR-1X014-01-05', 'weight_machining' => 0.19],
            ['part_no' => '70-PT-1X020-00-02', 'weight_machining' => 0.285],
            ['part_no' => '40-FR-1X021-13-01', 'weight_machining' => 1.647],
            ['part_no' => '40-FR-1X021-C2-00', 'weight_machining' => 1.647],
            ['part_no' => '10-FT-1X008-01-02F', 'weight_machining' => 0.299],
            ['part_no' => '10-FT-1X011-01-03F', 'weight_machining' => 0.07],
            ['part_no' => '40-FR-1X013-01-05F', 'weight_machining' => 0.19],
            ['part_no' => '40-FR-1X014-01-05F', 'weight_machining' => 0.19],
            ['part_no' => '70-PT-1X020-00-02F', 'weight_machining' => 0.285],
            ['part_no' => '40-FR-1X021-13-01F', 'weight_machining' => 1.647],
            ['part_no' => '40-FR-1X021-C2-00F', 'weight_machining' => 1.647],
            ['part_no' => '50-SA-1X025-13-04F', 'weight_machining' => 0.5],
            ['part_no' => '813LW40001', 'weight_machining' => 2.055],
            ['part_no' => '823LW25000', 'weight_machining' => 3.1],
            ['part_no' => '823LW25000P', 'weight_machining' => 3.1],
            ['part_no' => 'ME-223992', 'weight_machining' => 1.184],
            ['part_no' => 'ME-221779', 'weight_machining' => 2.65],
            ['part_no' => 'A400-203-00-73', 'weight_machining' => 0.515],
            ['part_no' => 'ME014341-L1', 'weight_machining' => 0.533],
            ['part_no' => '1798456-00', 'weight_machining' => 0.11],
            ['part_no' => '179845600', 'weight_machining' => 0.11],
            ['part_no' => '1875161-01', 'weight_machining' => 0.075],
            ['part_no' => '1906369-00', 'weight_machining' => 0.04],
            ['part_no' => 'CRC-FSE168/SP200', 'weight_machining' => 2.04],
            ['part_no' => '8997209891541', 'weight_machining' => 3.715],
            ['part_no' => '8997209891558--1565', 'weight_machining' => 1.006],
            ['part_no' => '8997209891602', 'weight_machining' => 0.52],
            ['part_no' => 'B1-2705', 'weight_machining' => 2.71],
            ['part_no' => 'C1-0735', 'weight_machining' => 0.735],
            ['part_no' => '9835.01.00.065', 'weight_machining' => 0.118],
            ['part_no' => '9835.01.01.005', 'weight_machining' => 0.15],
            ['part_no' => 'FGP30HD', 'weight_machining' => 1.629],
            ['part_no' => 'FGP30STD.26', 'weight_machining' => 0.68],
            ['part_no' => 'FGP30STD.18', 'weight_machining' => 0.194],
        ];

        $builder = $this->db->table('products');
        
        $updatedCount = 0;
        foreach ($data as $item) {
            // Update mutlak berdasarkan part_no
            $builder->where('part_no', $item['part_no'])
                    ->update(['weight_machining' => $item['weight_machining']]);
            
            if ($this->db->affectedRows() > 0) {
                $updatedCount++;
            }
        }

        echo "Berhasil update weight_machining untuk $updatedCount product berdasarkan exact part_no.\n";
    }
}