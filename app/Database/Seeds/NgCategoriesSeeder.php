<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class NgCategoriesSeeder extends Seeder
{
    public function run()
    {
        $data = [

            // ================= DIE CASTING (process_code = 2) =================
            ['ng_code' => 1,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Flow line'],
            ['ng_code' => 2,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Gompal'],
            ['ng_code' => 3,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Scratch'],
            ['ng_code' => 4,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Blister / Gelembung'],
            ['ng_code' => 5,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Over Heat'],
            ['ng_code' => 6,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'NG Dimensi'],
            ['ng_code' => 7,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Retak'],
            ['ng_code' => 8,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Test Cutting'],
            ['ng_code' => 9,  'process_code' => 2, 'process_name' => 'Die Casting', 'ng_name' => 'Lain-lain'],

            // ================= MACHINING (process_code = 5) =================
            ['ng_code' => 22, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Uncutting'],
            ['ng_code' => 23, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Diameter Oval'],
            ['ng_code' => 24, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Diameter Miring'],
            ['ng_code' => 25, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Diameter Seret'],
            ['ng_code' => 26, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Diameter Blong'],
            ['ng_code' => 27, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Jarak'],
            ['ng_code' => 28, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Concentricity'],
            ['ng_code' => 29, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Step'],
            ['ng_code' => 30, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Porosities'],
            ['ng_code' => 32, 'process_code' => 5, 'process_name' => 'Machining', 'ng_name' => 'Lain-lain'],
        ];

        foreach ($data as $row) {

            $exists = $this->db->table('ng_categories')
                ->where([
                    'process_code' => $row['process_code'],
                    'ng_code'      => $row['ng_code'],
                ])
                ->get()
                ->getRow();

            if (!$exists) {
                $this->db->table('ng_categories')->insert($row);
            }
        }
    }
}
