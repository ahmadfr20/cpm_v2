<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SeedCycleTimeMachiningProducts extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('products')) {
            throw new \RuntimeException("Tabel products tidak ditemukan.");
        }
        if (!$db->fieldExists('cycle_time_machining', 'products')) {
            throw new \RuntimeException("Kolom products.cycle_time_machining tidak ditemukan.");
        }

        $hasPartNo    = $db->fieldExists('part_no', 'products');
        $hasPartName  = $db->fieldExists('part_name', 'products');
        $hasPartProd  = $db->fieldExists('part_prod', 'products');
        $hasUpdatedAt = $db->fieldExists('updated_at', 'products');

        if (!$hasPartNo && !$hasPartName && !$hasPartProd) {
            throw new \RuntimeException("Tidak ada kolom part_no/part_name/part_prod di products.");
        }

        // MAP CT MACHINING (sec)
        $map = [
            'Housing 0160' => 69,
            'Leak Test' => 64,
            'Plug' => 32,
            'Carriage FT-2' => 180,
            'Carriage Lotus 2' => 365,
            'Body Stroma X' => 400,
            'Carriage Bamboo-2' => 240,
            'Assy Bushing Bamboo-2' => 240,
            'Assy Bushing Lotus-2' => 60,
            'Assy Shaft Lotus-2' => 171,
            'Assy Bushing FT-2' => 65,
            'Assy Union Brother' => 157,
            'Leak Test Case Comp' => 60,
            'Steering Shaft Base' => 327,
            'Handlebar Riser Clamp' => 171,
            'Passenger Rear Handle' => 86,
            'License Plate Bracket' => 90,
            'Intermediate Plate MSG' => 330,
            'Bottom-S1 (OP-1-2)' => 510,
            'Cover-S1 (OP-1-2)' => 103,
            'Cover-S1 (OP-3-4)' => 109,
            'Clip Side' => 53,
            'Polo Shaft' => 106,
            'Motor Spacer / Adapter' => 277,
            'Passenger Footpeg Left' => 55,
            'Passenger Footpeg Right' => 55,
            'OP-1 : Hub Engine' => 72,
            'OP-2 : Hub Engine' => 72,
            'Leak Test CGSL APV' => 300,
            'CGSL APV' => 285,
            'CGC 4JA-1 & Leak Test' => 240,
            'Leak Test Pump Body' => 95,
            'Leak Test Water Outlet' => 95,
            'BRKT 71 LGO (Jig-1)' => 162,
            'BRKT 71 LGO (Jig-2)' => 162,
            'Tank LW' => 256,
            'Cover LW' => 180,
            'Thermostat TD (Jig-1)' => 190,
            'Thermostat TD (Jig-2)' => 240,
            'Thermostat Housing (Jig-1)' => 190,
            'Thermostat Housing (Jig-2)' => 240,
            'DUCT THERMOSTAT' => 700,
            'DUCT ASM WATER' => 277,
            'JIG 1 BRACKET ASM GENERATOR' => 325,
            'JIG 2 BRACKET ASM GENERATOR' => 112,

            'H-7080' => 86,
            'H-7090' => 86,
            'H-7100' => 55,
            'H-7110' => 55,
            'H-7791' => 86,
            'H-9690' => 55,
            'H-9700' => 55,
            'H-9710' => 55,
            'H-9720' => 55,
            'H-5561' => 80,
            'H-8440' => 86,
            'H-8450' => 66,
            'H-0580' => 55,
            'H-0600' => 55,
            'H-9730' => 55,
            'H-9750' => 55,
            'H-7690' => 55,
            'H-7700' => 55,
            'H-7710' => 55,
            'H-7720' => 55,

            'PUMP BODY OP1' => 148,
            'PUMP BODY OP2' => 180,
            'PUMP COVER' => 102,
            'WATER OUTLET' => 97,
            'CASE COMP THERMO' => 180,
            'CASE COMP THERMO (OP-1-2-3-4-5-6)' => 255,

            'Housing-1W' => 66,
            'YR-9 OP1' => 113,
            'YR-9 OP2' => 72,
            'CWT-YL8' => 60,
            'CWO' => 115,
            'CWT-79100' => 150,
            'CWT-73000' => 150,
            'PLUG CYL BLOCK' => 300,
        ];

        // helper: kalau query CI balikin false, ambil error DB
        $safeGetRow = function($builder) use ($db) {
            $q = $builder->get();
            if ($q === false) {
                $err = $db->error();
                throw new \RuntimeException("Query gagal: " . ($err['message'] ?? 'unknown'));
            }
            return $q->getRowArray();
        };

        // helper cari produk (exact -> like)
        $findProduct = function(string $key) use ($db, $safeGetRow, $hasPartNo, $hasPartName, $hasPartProd): ?array {
            $key = trim($key);
            if ($key === '') return null;

            $keyLower = mb_strtolower($key);

            // Urutan exact: part_prod -> part_no -> part_name
            $exactCols = [];
            if ($hasPartProd) $exactCols[] = 'part_prod';
            if ($hasPartNo)   $exactCols[] = 'part_no';
            if ($hasPartName) $exactCols[] = 'part_name';

            foreach ($exactCols as $col) {
                // ✅ FIX: value HARUS di-quote -> pakai $db->escape()
                $cond = "LOWER($col) = " . $db->escape($keyLower);

                $builder = $db->table('products')
                    ->select('id, part_no, part_prod, part_name')
                    ->where($cond, null, false)
                    ->limit(1);

                $row = $safeGetRow($builder);
                if ($row) return $row;
            }

            // LIKE sanitize (escape wildcard)
            $likeKey = str_replace(['%', '_'], ['\%', '\_'], $key);

            // LIKE part_name
            if ($hasPartName) {
                $builder = $db->table('products')
                    ->select('id, part_no, part_prod, part_name')
                    ->like('part_name', $likeKey, 'both', true)
                    ->orderBy('id', 'ASC')
                    ->limit(1);

                $row = $safeGetRow($builder);
                if ($row) return $row;
            }

            // LIKE part_prod
            if ($hasPartProd) {
                $builder = $db->table('products')
                    ->select('id, part_no, part_prod, part_name')
                    ->like('part_prod', $likeKey, 'both', true)
                    ->orderBy('id', 'ASC')
                    ->limit(1);

                $row = $safeGetRow($builder);
                if ($row) return $row;
            }

            // LIKE part_no
            if ($hasPartNo) {
                $builder = $db->table('products')
                    ->select('id, part_no, part_prod, part_name')
                    ->like('part_no', $likeKey, 'both', true)
                    ->orderBy('id', 'ASC')
                    ->limit(1);

                $row = $safeGetRow($builder);
                if ($row) return $row;
            }

            return null;
        };

        $updated  = 0;
        $notFound = [];

        $db->transStart();

        foreach ($map as $key => $ct) {
            $key = trim((string)$key);
            if ($key === '') continue;

            $prod = $findProduct($key);

            if (!$prod) {
                $notFound[] = $key;
                continue;
            }

            $payload = ['cycle_time_machining' => (int)$ct];
            if ($hasUpdatedAt) $payload['updated_at'] = date('Y-m-d H:i:s');

            $ok = $db->table('products')->where('id', (int)$prod['id'])->update($payload);
            if ($ok === false) {
                $err = $db->error();
                throw new \RuntimeException("Update gagal (ID={$prod['id']}): " . ($err['message'] ?? 'unknown'));
            }

            $updated++;
        }

        $db->transComplete();

        echo "SeedCycleTimeMachiningProducts DONE\n";
        echo "Updated: {$updated}\n";
        echo "Not Found: " . count($notFound) . "\n";

        if (!empty($notFound)) {
            echo "=== Not Found List ===\n";
            foreach ($notFound as $nf) echo "- {$nf}\n";
        }
    }
}
