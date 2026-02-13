<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class NgCategoriesSeeder extends Seeder
{
    public function run()
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        /**
         * IMPORTANT:
         * Jangan TRUNCATE karena ng_categories direferensikan FK oleh die_casting_hourly_ng_details.
         * Kita pakai UPSERT (insert jika belum ada, update jika sudah ada).
         *
         * Key unik yang dipakai:
         * - process_name + ng_code
         */

        $rows = [
            // =======================
            // 1 = Die Casting (DC)
            // =======================
            ['ng_code'=>1,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Flow line',           'is_active'=>1],
            ['ng_code'=>2,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Gompal',             'is_active'=>1],
            ['ng_code'=>3,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Scratch',            'is_active'=>1],
            ['ng_code'=>4,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Blister/gelembung',  'is_active'=>1],
            ['ng_code'=>5,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Over heat',          'is_active'=>1],
            ['ng_code'=>6,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'NG dimensi',         'is_active'=>1],
            ['ng_code'=>7,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Retak',              'is_active'=>1],
            ['ng_code'=>8,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Test Cutting',       'is_active'=>1],
            ['ng_code'=>9,  'process_code'=>1,  'process_name'=>'Die Casting', 'ng_name'=>'Lain lain',          'is_active'=>1],

            // =======================
            // 4 = BURRYTORY (BT)
            // =======================
            ['ng_code'=>10, 'process_code'=>4,  'process_name'=>'BURRYTORY',   'ng_name'=>'Over Kikir',         'is_active'=>1],
            ['ng_code'=>11, 'process_code'=>4,  'process_name'=>'BURRYTORY',   'ng_name'=>'Kurang Kikir',       'is_active'=>1],
            ['ng_code'=>12, 'process_code'=>4,  'process_name'=>'BURRYTORY',   'ng_name'=>'Over Sanding',       'is_active'=>1],
            ['ng_code'=>13, 'process_code'=>4,  'process_name'=>'BURRYTORY',   'ng_name'=>'Kurang Sanding',     'is_active'=>1],
            ['ng_code'=>14, 'process_code'=>4,  'process_name'=>'BURRYTORY',   'ng_name'=>'Dent',               'is_active'=>1],
            ['ng_code'=>15, 'process_code'=>4,  'process_name'=>'BURRYTORY',   'ng_name'=>'Gompal Gate',        'is_active'=>1],
            ['ng_code'=>16, 'process_code'=>4,  'process_name'=>'BURRYTORY',   'ng_name'=>'Lain lain',          'is_active'=>1],

            // =======================
            // 5 = SAND BLASTING (SB)
            // =======================
            ['ng_code'=>17, 'process_code'=>5,  'process_name'=>'SAND BLASTING','ng_name'=>'Dirty',             'is_active'=>1],
            ['ng_code'=>18, 'process_code'=>5,  'process_name'=>'SAND BLASTING','ng_name'=>'Kurang Blasting',   'is_active'=>1],
            ['ng_code'=>19, 'process_code'=>5,  'process_name'=>'SAND BLASTING','ng_name'=>'Over Blasting',     'is_active'=>1],
            ['ng_code'=>20, 'process_code'=>5,  'process_name'=>'SAND BLASTING','ng_name'=>'Dent',              'is_active'=>1],
            ['ng_code'=>21, 'process_code'=>5,  'process_name'=>'SAND BLASTING','ng_name'=>'Lain Lain',         'is_active'=>1],

            // =======================
            // 2 = MACHINING (MC)
            // =======================
            ['ng_code'=>22, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Uncutting',          'is_active'=>1],
            ['ng_code'=>23, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Diameter Oval',      'is_active'=>1],
            ['ng_code'=>24, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Diameter Miring',    'is_active'=>1],
            ['ng_code'=>25, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Diameter Seret',     'is_active'=>1],
            ['ng_code'=>26, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Diameter Blong',     'is_active'=>1],
            ['ng_code'=>27, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Jarak',              'is_active'=>1],
            ['ng_code'=>28, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Concentricity',      'is_active'=>1],
            ['ng_code'=>29, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Step',               'is_active'=>1],
            ['ng_code'=>30, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Porocities',         'is_active'=>1],
            ['ng_code'=>32, 'process_code'=>2,  'process_name'=>'Machining',   'ng_name'=>'Lain Lain',          'is_active'=>1],

            // =======================
            // 6 = LEAK TEST (LT)
            // =======================
            ['ng_code'=>33, 'process_code'=>6,  'process_name'=>'LEAK TEST',   'ng_name'=>'Leak Test',          'is_active'=>1],

            // =======================
            // 7 = JIG PLUG (JP)
            // =======================
            ['ng_code'=>34, 'process_code'=>7,  'process_name'=>'JIG PLUG',    'ng_name'=>'Seret',              'is_active'=>1],
            ['ng_code'=>35, 'process_code'=>7,  'process_name'=>'JIG PLUG',    'ng_name'=>'Miring',             'is_active'=>1],
            ['ng_code'=>36, 'process_code'=>7,  'process_name'=>'JIG PLUG',    'ng_name'=>'Pin tidak terpasang','is_active'=>1],
            ['ng_code'=>37, 'process_code'=>7,  'process_name'=>'JIG PLUG',    'ng_name'=>'Shaft tidak terpasang','is_active'=>1],

            // =======================
            // 10 = PAINTING (PT)
            // =======================
            ['ng_code'=>38, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Peel off',           'is_active'=>1],
            ['ng_code'=>39, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Peel Of/Mengelupas', 'is_active'=>1],
            ['ng_code'=>40, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Dirty/Kotor',        'is_active'=>1],
            ['ng_code'=>41, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Serabut',            'is_active'=>1],
            ['ng_code'=>42, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Tipis',              'is_active'=>1],
            ['ng_code'=>43, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Belang',             'is_active'=>1],
            ['ng_code'=>44, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Kasar',              'is_active'=>1],
            ['ng_code'=>45, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Crack',              'is_active'=>1],
            ['ng_code'=>46, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Gores',              'is_active'=>1],
            ['ng_code'=>47, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Bubble',             'is_active'=>1],
            ['ng_code'=>48, 'process_code'=>10, 'process_name'=>'PAINTING',    'ng_name'=>'Mata Ikan',          'is_active'=>1],
        ];

        $db->transBegin();

        try {
            $table = $db->table('ng_categories');

            foreach ($rows as $r) {
                // cari existing by unique key (process_name + ng_code)
                $exist = $table
                    ->where('process_name', (string)$r['process_name'])
                    ->where('ng_code', (int)$r['ng_code'])
                    ->get()->getRowArray();

                if ($exist) {
                    // update existing
                    $update = [
                        'process_code' => (int)$r['process_code'],
                        'ng_name'      => (string)$r['ng_name'],
                        'is_active'    => (int)$r['is_active'],
                    ];

                    // updated_at optional
                    if ($db->fieldExists('updated_at', 'ng_categories')) {
                        $update['updated_at'] = $now;
                    }

                    $table->where('id', (int)$exist['id'])->update($update);
                } else {
                    // insert baru
                    $insert = [
                        'ng_code'      => (int)$r['ng_code'],
                        'process_code' => (int)$r['process_code'],
                        'process_name' => (string)$r['process_name'],
                        'ng_name'      => (string)$r['ng_name'],
                        'is_active'    => (int)$r['is_active'],
                    ];

                    if ($db->fieldExists('created_at', 'ng_categories')) $insert['created_at'] = $now;
                    if ($db->fieldExists('updated_at', 'ng_categories')) $insert['updated_at'] = $now;

                    $table->insert($insert);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
