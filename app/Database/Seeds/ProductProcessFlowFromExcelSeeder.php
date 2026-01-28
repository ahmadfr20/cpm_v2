<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductProcessFlowFromExcelSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        /**
         * =========================================
         * Mapping TAG (dari array procs) -> process_name master
         * (disesuaikan dengan tabel production_processes kamu)
         * =========================================
         */
        $processCandidates = [
            'CASTING'   => ['Die Casting'],
            'Machining'       => ['Machining'],
            'LEAK TEST' => ['LEAK TEST'],
            'PAINTING'  => ['PAINTING'],
            'FINISHED GOOD'       => ['FINISHED GOOD'], // kalau mau include FINAL INSPECTION juga, nanti saya ubah
        ];

        // ASSY special: kalau dicentang -> ambil dua ini
        $assyCandidates = ['ASSY BUSHING', 'ASSY SHAFT'];

        /**
         * =========================================
         * Data hasil Excel (sudah difilter):
         * - skip RAW MATERIAL / R/M
         * - skip BURRYTORY, SAND BLASTING, JIG PLUG
         * =========================================
         */
        $rows = [
            ['part_no' => 'HOUSING 0160', 'part_name' => 'A1 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'HOUSING 0160', 'part_name' => 'A5 FANUC', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'HOUSING 0160', 'part_name' => 'A6 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'H-7100', 'part_name' => 'C-5 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-7110', 'part_name' => 'C-5 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-9690', 'part_name' => 'C-6 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-9700', 'part_name' => 'C-6 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-9710', 'part_name' => 'C-6 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-9720', 'part_name' => 'C-6 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-7791', 'part_name' => 'C-7 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-0580', 'part_name' => 'C-7 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-0600', 'part_name' => 'C-7 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-9730', 'part_name' => 'C-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-9750', 'part_name' => 'C-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-7690 #2', 'part_name' => 'C-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-7700 #2', 'part_name' => 'C-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-7710', 'part_name' => 'C-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'H-7720', 'part_name' => 'C-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],

            ['part_no' => 'Holder 7590', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 7600', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 7610', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 7620', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 9740', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 9760', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 0590', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 0610', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1220', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1230', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1240', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1250', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1320', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1330', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1340', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1350', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1470', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1481', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1490', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1501', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1650', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1660', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1670', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1680', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1750', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1760', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1910', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 1920', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 2090', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 2110', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 2100', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Holder 2120', 'part_name' => '-', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],

            ['part_no' => 'Housing-1W 9710', 'part_name' => 'D-3', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Housing-1W 9710', 'part_name' => 'D-4', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],

            ['part_no' => 'Steering Shaft Base', 'part_name' => 'B-5 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'Handlebar Riser Clamp', 'part_name' => 'A2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'Passenger Rear Handle', 'part_name' => 'A2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'License Plate Holder', 'part_name' => 'A2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'Motor Spacer /Adapter', 'part_name' => 'A2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'Passenger Footpeg Left', 'part_name' => 'A2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'Passenger Footpeg Right', 'part_name' => 'A2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'Polo Shaft', 'part_name' => 'B-5 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],

            ['part_no' => 'BOTTOM STROMA 1', 'part_name' => 'EXTERNAL', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'COVER STROMA 1', 'part_name' => 'EXTERNAL', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'SIDE CLIP', 'part_name' => 'EXTERNAL', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'STROMA X', 'part_name' => 'EXTERNAL', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'COVER STROMA X', 'part_name' => 'EXTERNAL', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],

            ['part_no' => 'Intermediate Plate MSG JIG 1', 'part_name' => 'B-5 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Intermediate Plate MSG JIG 2', 'part_name' => 'B-6 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'CGC 4JA-1', 'part_name' => 'B-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],

            ['part_no' => 'Thermostat Housing (Jig-1)', 'part_name' => 'C-3 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'Thermostat Housing (Jig-2)', 'part_name' => 'C-3 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],

            // ASSY -> wajib ASSY BUSHING + ASSY SHAFT
            ['part_no' => 'Duct H Thermostat  + Leak Test', 'part_name' => 'C-3 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'ASSY', 'FINISHED GOOD']],
            ['part_no' => 'Duct Asm + Leak Test + Assy', 'part_name' => 'C-3 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'ASSY', 'FINISHED GOOD']],

            ['part_no' => 'JIG 1 BRACKET ACG', 'part_name' => 'C-3 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
            ['part_no' => 'JIG 2 BRACKET ACG', 'part_name' => 'C-3 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],

            ['part_no' => 'Carriage FT-2 + Assy', 'part_name' => 'B-1 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'ASSY', 'FINISHED GOOD']],
            ['part_no' => 'Carriage Lotus 2+ Assy', 'part_name' => 'B-2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'ASSY', 'FINISHED GOOD']],

            ['part_no' => 'APV + LEAK TEST', 'part_name' => 'B-8 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],

            ['part_no' => 'CWT YL-8', 'part_name' => 'D-6 BUBUT', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'CWO', 'part_name' => 'D-6 BUBUT', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'CWT-79100', 'part_name' => 'D-6 BUBUT', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'CWT-73000', 'part_name' => 'D-6 BUBUT', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],

            ['part_no' => "Case Comp '(OP-4-5-6)", 'part_name' => 'C-9 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Tank LW', 'part_name' => 'B-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Cover LW', 'part_name' => 'B-8 Brother', 'procs' => ['CASTING', 'Machining', 'FINISHED GOOD']],
            ['part_no' => 'Thermostat Euro', 'part_name' => 'A2 Brother', 'procs' => ['CASTING', 'Machining', 'LEAK TEST', 'FINISHED GOOD']],
        ];

        /**
         * =========================================
         * Cache process_name -> id (biar cepat)
         * =========================================
         */
        $processCache = [];

        $resolveProcessId = function (string $processName) use ($db, &$processCache): ?int {
            $key = mb_strtolower(trim($processName));
            if (array_key_exists($key, $processCache)) {
                return $processCache[$key];
            }

            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_name', $processName)
                ->get()
                ->getRowArray();

            $processCache[$key] = $row ? (int)$row['id'] : null;
            return $processCache[$key];
        };

        /**
         * =========================================
         * SEEDING
         * =========================================
         */
        $db->transBegin();

        try {
            foreach ($rows as $r) {
                $partNo   = trim((string)($r['part_no'] ?? ''));
                $partName = trim((string)($r['part_name'] ?? ''));
                $procs    = $r['procs'] ?? [];

                if ($partNo === '') continue;

                // cari product by part_no + part_name (karena part_no bisa duplikat)
                $product = $db->table('products')
                    ->select('id')
                    ->where('part_no', $partNo)
                    ->where('part_name', $partName)
                    ->get()
                    ->getRowArray();

                // fallback: part_no saja (ambil yg paling awal)
                if (!$product) {
                    $product = $db->table('products')
                        ->select('id')
                        ->where('part_no', $partNo)
                        ->orderBy('id', 'ASC')
                        ->get()
                        ->getRowArray();
                }

                if (!$product) {
                    continue; // product belum ada
                }

                $productId = (int)$product['id'];

                // nonaktifkan semua flow existing (biar sesuai seed)
                if ($db->fieldExists('is_active', 'product_process_flows')) {
                    $db->table('product_process_flows')
                        ->where('product_id', $productId)
                        ->set('is_active', 0)
                        ->update();
                }

                // build final process ids sesuai urutan
                $finalProcessIds = [];

                foreach ($procs as $tag) {
                    $tag = trim((string)$tag);
                    if ($tag === '') continue;

                    if ($tag === 'ASSY') {
                        // wajib 2 proses: ASSY BUSHING + ASSY SHAFT
                        $idB = $resolveProcessId($assyCandidates[0]);
                        $idS = $resolveProcessId($assyCandidates[1]);

                        if ($idB) $finalProcessIds[] = $idB;
                        if ($idS) $finalProcessIds[] = $idS;

                        continue;
                    }

                    if (!isset($processCandidates[$tag])) {
                        continue;
                    }

                    // ambil kandidat process_name pertama yg ketemu
                    $pickedId = null;
                    foreach ($processCandidates[$tag] as $pname) {
                        $pid = $resolveProcessId($pname);
                        if ($pid) { $pickedId = $pid; break; }
                    }

                    if ($pickedId) {
                        $finalProcessIds[] = $pickedId;
                    }
                }

                if (!$finalProcessIds) continue;

                // upsert flows sesuai sequence
                $seq = 1;
                foreach ($finalProcessIds as $processId) {
                    $exist = $db->table('product_process_flows')
                        ->select('id')
                        ->where('product_id', $productId)
                        ->where('process_id', $processId)
                        ->get()
                        ->getRowArray();

                    $payload = [
                        'product_id' => $productId,
                        'process_id' => $processId,
                        'sequence'   => $seq,
                    ];

                    if ($db->fieldExists('is_active', 'product_process_flows')) {
                        $payload['is_active'] = 1;
                    }

                    $now = date('Y-m-d H:i:s');
                    if ($db->fieldExists('updated_at', 'product_process_flows')) $payload['updated_at'] = $now;

                    if ($exist) {
                        $db->table('product_process_flows')
                            ->where('id', (int)$exist['id'])
                            ->update($payload);
                    } else {
                        if ($db->fieldExists('created_at', 'product_process_flows')) $payload['created_at'] = $now;
                        $db->table('product_process_flows')->insert($payload);
                    }

                    $seq++;
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('DB error saat insert product_process_flows');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
