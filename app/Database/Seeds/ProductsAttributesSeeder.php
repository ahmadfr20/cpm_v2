<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductsAttributesSeeder extends Seeder
{
    public function run()
    {
        $db    = $this->db;
        $table = $db->table('products');

        // H-XXXX => HOLDER-XXXX (khusus part_name)
        $toHolderName = function (string $part): string {
            $part = trim($part);
            if (preg_match('/^H-(\d+)/i', $part)) {
                return preg_replace('/^H-/i', 'HOLDER-', $part);
            }
            return $part;
        };

        // Data dari tabel yang kamu kirim:
        // part, weight_ascas, weight_runner, cycle_time, efficiency_rate, cavity
        $rows = [
            ['part' => 'HOLDER-5130', 'ascas' => 145, 'runner' => 533, 'cycle' => 35, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-5140', 'ascas' => 135, 'runner' => 650, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-5561 ', 'ascas' => 318, 'runner' => 467, 'cycle' => 46, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-7100', 'ascas' => 112, 'runner' => 538, 'cycle' => 39, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7110', 'ascas' => 170, 'runner' => 650, 'cycle' => 39, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7690 ', 'ascas' => 92, 'runner' => 554, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7700 ', 'ascas' => 385, 'runner' => 845, 'cycle' => 49, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-7710', 'ascas' => 92, 'runner' => 554, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7720 ', 'ascas' => 385, 'runner' => 845, 'cycle' => 49, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-7791 ', 'ascas' => 120, 'runner' => 1045, 'cycle' => 45, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7590 ', 'ascas' => 150, 'runner' => 500, 'cycle' => 39, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7600 ', 'ascas' => 135, 'runner' => 555, 'cycle' => 39, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7610 ', 'ascas' => 150, 'runner' => 500, 'cycle' => 39, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-7620 ', 'ascas' => 135, 'runner' => 555, 'cycle' => 39, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-8440', 'ascas' => 375, 'runner' => 615, 'cycle' => 45, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-8450', 'ascas' => 130, 'runner' => 595, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-9690', 'ascas' => 142, 'runner' => 618, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-9700', 'ascas' => 187, 'runner' => 768, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-9710', 'ascas' => 142, 'runner' => 618, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-9720', 'ascas' => 187, 'runner' => 768, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-9730', 'ascas' => 127, 'runner' => 588, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-9740', 'ascas' => 420, 'runner' => 688, 'cycle' => 60, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-9750', 'ascas' => 127, 'runner' => 588, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-9760 ', 'ascas' => 420, 'runner' => 688, 'cycle' => 60, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-0580 ', 'ascas' => 360, 'runner' => 720, 'cycle' => 47, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-0590', 'ascas' => 120, 'runner' => 595, 'cycle' => 43, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-0600 ', 'ascas' => 360, 'runner' => 720, 'cycle' => 47, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'HOLDER-0610', 'ascas' => 120, 'runner' => 595, 'cycle' => 43, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1220', 'ascas' => 160, 'runner' => 450, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1230', 'ascas' => 135, 'runner' => 480, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1240', 'ascas' => 160, 'runner' => 451, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1250', 'ascas' => 135, 'runner' => 480, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1320', 'ascas' => 85, 'runner' => 626, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1330', 'ascas' => 85, 'runner' => 630, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1340', 'ascas' => 90, 'runner' => 621, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1350', 'ascas' => 90, 'runner' => 625, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1470', 'ascas' => 100, 'runner' => 622, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1481', 'ascas' => 95, 'runner' => 625, 'cycle' => 42, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1490', 'ascas' => 100, 'runner' => 621, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1501', 'ascas' => 95, 'runner' => 625, 'cycle' => 42, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1650', 'ascas' => 85, 'runner' => 545, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1660', 'ascas' => 85, 'runner' => 540, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1670', 'ascas' => 100, 'runner' => 531, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1680', 'ascas' => 100, 'runner' => 525, 'cycle' => 51, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'HOLDER-1750', 'ascas' => 120, 'runner' => 505, 'cycle' => 42, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1760', 'ascas' => 130, 'runner' => 495, 'cycle' => 42, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1910', 'ascas' => 120, 'runner' => 510, 'cycle' => 42, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-1920', 'ascas' => 130, 'runner' => 500, 'cycle' => 42, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-2090', 'ascas' => 135, 'runner' => 628, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-2100', 'ascas' => 164, 'runner' => 669, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-2110', 'ascas' => 136, 'runner' => 677, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'HOLDER-2120', 'ascas' => 163, 'runner' => 640, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 2],

            ['part' => 'Housing-5050 #1', 'ascas' => 173, 'runner' => 652, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Housing-5050 ', 'ascas' => 173, 'runner' => 652, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Housing-1W #4', 'ascas' => 173, 'runner' => 627, 'cycle' => 43, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Housing-1W #6', 'ascas' => 173, 'runner' => 627, 'cycle' => 43, 'eff' => 100.00, 'cavity' => 2],

            ['part' => 'CGC 4JA-1', 'ascas' => 1465, 'runner' => 1735, 'cycle' => 90, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'CGC 4JB-1', 'ascas' => 1195, 'runner' => 1805, 'cycle' => 78, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Duct Asm', 'ascas' => 430, 'runner' => 383, 'cycle' => 55, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Duct Asm ', 'ascas' => 430, 'runner' => 383, 'cycle' => 55, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Duct Thermostat', 'ascas' => 415, 'runner' => 955, 'cycle' => 60, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'BRACKET ASM : GENERATOR', 'ascas' => 2085, 'runner' => 830, 'cycle' => 75, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Plate MSG', 'ascas' => 1215, 'runner' => 747, 'cycle' => 72, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Plate XPI', 'ascas' => 2660, 'runner' => 1340, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'RT-50', 'ascas' => 6800, 'runner' => 3000, 'cycle' => 95, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'XCF', 'ascas' => 7000, 'runner' => -3000, 'cycle' => 95, 'eff' => 100.00, 'cavity' => 1],

            ['part' => 'Case Comp #3', 'ascas' => 210, 'runner' => 307, 'cycle' => 66, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Case Comp ', 'ascas' => 210, 'runner' => 307, 'cycle' => 66, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'CGSL APV', 'ascas' => 395, 'runner' => 455, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'CWO', 'ascas' => 83, 'runner' => 586, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'BRACKET, ENG RR MTG (YR9) S', 'ascas' => 965, 'runner' => 555, 'cycle' => 60, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'BRACKET, ENG RR MTG (YR9)', 'ascas' => 965, 'runner' => 555, 'cycle' => 60, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'CWT YL-8', 'ascas' => 93, 'runner' => 385, 'cycle' => 39, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Bracket-74', 'ascas' => 1330, 'runner' => 691, 'cycle' => 58, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Bracket-71', 'ascas' => 880, 'runner' => 1621, 'cycle' => 72, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Stand Base LR', 'ascas' => 150, 'runner' => 150, 'cycle' => 45, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Manifold 2DP', 'ascas' => 125, 'runner' => 379, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Manifold 779 ', 'ascas' => 2700, 'runner' => 2575, 'cycle' => 82, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Manifold 992 ', 'ascas' => 1235, 'runner' => 1885, 'cycle' => 75, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Thermostat TD', 'ascas' => 530, 'runner' => 420, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Thermostat Housing', 'ascas' => 530, 'runner' => 420, 'cycle' => 41, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Carriage Bamboo 2', 'ascas' => 107, 'runner' => 179, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Carriage Lotus 2', 'ascas' => 70, 'runner' => 345, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Carriage FT-2 ', 'ascas' => 35, 'runner' => 345, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Carriage FT-2 #1', 'ascas' => 35, 'runner' => 345, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 2],

            ['part' => 'BLOK SND 01 (CASTING) X-ONE/ JUPITER', 'ascas' => 1255, 'runner' => 745, 'cycle' => 75, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'BLOK SND 02 (CASTING) SUPRA', 'ascas' => 1220, 'runner' => 780, 'cycle' => 75, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'BLOK SND 03 (CASTING) KHARISMA', 'ascas' => 1090, 'runner' => 910, 'cycle' => 75, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'BLOK SND 04 (CASTING) BLADE', 'ascas' => 1110, 'runner' => 345, 'cycle' => 75, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'BLOK SND 05 (CASTING) KHARISMA', 'ascas' => 1220, 'runner' => 345, 'cycle' => 75, 'eff' => 100.00, 'cavity' => 1],

            ['part' => 'PASSENGER FOOTPEG RIGHT', 'ascas' => 184, 'runner' => 866, 'cycle' => 45, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'PASSENGER FOOTPEG LEFT', 'ascas' => 184, 'runner' => 866, 'cycle' => 45, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'TX-HANDLE BAR RISER CLAMP', 'ascas' => 80, 'runner' => 835, 'cycle' => 40, 'eff' => 100.00, 'cavity' => 4],
            ['part' => 'STEERING SHAFT BASE', 'ascas' => 296, 'runner' => 1179, 'cycle' => 60, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Spacer Adapter', 'ascas' => 200, 'runner' => 625, 'cycle' => 50, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'License Plate', 'ascas' => 515, 'runner' => 600, 'cycle' => 50, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'PASSANGER REAR HANDLE ( BLANK )', 'ascas' => 1423, 'runner' => 2797, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'PASSENGER REAR HANDLE BLACK MATTE', 'ascas' => 1423, 'runner' => 2797, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'PASSENGER REAR HANDLE MATTE F', 'ascas' => 1423, 'runner' => 2797, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'PASSENGER REAR HANDLE WHITE', 'ascas' => 1423, 'runner' => 2797, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'PASSENGER REAR HANDLE WHITE F', 'ascas' => 1423, 'runner' => 2797, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'FGP30HD Pump Body', 'ascas' => 1629, 'runner' => 1601, 'cycle' => 90, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'FGP30HD Pump Cover', 'ascas' => 1035, 'runner' => 975, 'cycle' => 85, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Water Inlet', 'ascas' => 388, 'runner' => 389, 'cycle' => 45, 'eff' => 100.00, 'cavity' => 2],
            ['part' => 'Water Outlet', 'ascas' => 680, 'runner' => 410, 'cycle' => 45, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Tank LWN ', 'ascas' => 2035, 'runner' => 965, 'cycle' => 82, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'TANK L W N', 'ascas' => 2035, 'runner' => 965, 'cycle' => 82, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Cover LW ', 'ascas' => 3050, 'runner' => 2250, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'COVER L W', 'ascas' => 3050, 'runner' => 2250, 'cycle' => 100, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Crank Case', 'ascas' => 2035, 'runner' => 2000, 'cycle' => 81, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'BOTTOM STROMA 1-POLOS', 'ascas' => 520, 'runner' => 480, 'cycle' => 105, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'BODY STORMA X', 'ascas' => 520, 'runner' => 480, 'cycle' => 105, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Cover Stroma-1', 'ascas' => 500, 'runner' => 500, 'cycle' => 86, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'COVER STROMA X-INFITE LOGO', 'ascas' => 500, 'runner' => 500, 'cycle' => 86, 'eff' => 100.00, 'cavity' => 1],
            ['part' => 'Polo Shaft', 'ascas' => 550, 'runner' => 450, 'cycle' => 60, 'eff' => 100.00, 'cavity' => 1],
        ];

        foreach ($rows as $r) {
            $part = trim((string) $r['part']);

            // Cari product existing (case-insensitive + ignore spaces)
            $found = $db->query(
                "SELECT id, part_no, part_name
                 FROM products
                 WHERE LOWER(part_no) = LOWER(?)
                    OR LOWER(part_name) = LOWER(?)
                    OR REPLACE(LOWER(part_no),' ','') = REPLACE(LOWER(?),' ','')
                    OR REPLACE(LOWER(part_name),' ','') = REPLACE(LOWER(?),' ','')
                 LIMIT 1",
                [$part, $part, $part, $part]
            )->getRowArray();

            if (!$found) {
                // UPDATE ONLY => kalau tidak ada, skip
                // log_message('warning', "ProductsAttributesSeeder SKIP: product tidak ditemukan untuk part '{$part}'");
                continue;
            }

            $payload = [
                'weight_ascas'    => (int) $r['ascas'],
                'weight_runner'   => (int) $r['runner'],
                'cycle_time'      => (int) $r['cycle'],
                'cavity'          => (int) $r['cavity'],
                'efficiency_rate' => (float) $r['eff'],
                'updated_at'      => date('Y-m-d H:i:s'),
            ];

            // rename H-XXXX => HOLDER-XXXX di part_name
            if (preg_match('/^H-\d+/i', $part)) {
                $payload['part_name'] = $toHolderName($part);
            }

            $table->where('id', (int) $found['id'])->update($payload);
        }
    }
}
