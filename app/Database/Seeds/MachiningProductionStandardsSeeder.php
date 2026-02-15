<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\MachineModel;
use App\Models\ProductModel;
use App\Models\ProductionStandardModel;

class MachiningProductionStandardsSeeder extends Seeder
{
    protected MachineModel $machineModel;
    protected ProductModel $productModel;
    protected ProductionStandardModel $standardModel;

    public function run()
    {
        $this->machineModel  = new MachineModel();
        $this->productModel  = new ProductModel();
        $this->standardModel = new ProductionStandardModel();

        /**
         * Data dari gambar (Alamat Mesin -> daftar Nama Part)
         * Nomor "1., 2., ..." sudah dihilangkan (tidak diperlukan).
         */
        $map = [
            'MCC-4' => ['Housing 0160'],
            'MCC-3' => ['Housing 0160'],
            'MCC-2' => ['Housing 0160'],
            'MCC-1' => ['Housing 0160'],

            'LT-2'  => ['Leak Test', 'Plug'],

            'MCC-9' => ['Carriage FT-2'],

            'MCC-8' => [
                'Carriage Lotus 2',
                'Body Stroma X',
                'Carriage Bamboo-2',
                'Assy Bushing Bamboo-2',
                'Assy Bushing Lotus-2',
                'Assy Shaft Lotus-2',
                'Assy Bushing FT-2',
                'Assy Union Brother',
                'Leak Test Case Comp',
            ],

            'MCC-7' => [
                'Steering Shaft Base',
                'Handlebar Riser Clamp',
                'Passenger Rear Handle',
                'License Plate Bracket',
                'Intermediate Plate MSG',
            ],

            'MCC-6' => [
                'Body Stroma X',
                'Bottom-S1 (OP-1-2)',
                'Cover-S1 (OP-1-2)',
                'Cover-S1 (OP-3-4)',
                'Clip Side',
                'Pole Shaft',
                'Motor Spacer / Adapter',
                'Passenger Footpeg Left',
                'Passenger Footpeg Right',
                'Intermediate Plate MSG',
            ],

            'MCB-1' => [
                'OP-1 : Hub Engine',
                'OP-2 : Hub Engine',
                'Leak Test CGSL APV',
            ],

            'MCC-5' => [
                'CGSL APV',
                'CGC 4JA-1 & Leak Test',
                'Leak Test Pump Body',
                'Leak Test Water Outlet',
                'BRKT 71 LGO (Jig-1)',
                'BRKT 71 LGO (Jig-2)',
                'Tank LW',
                'Cover LW',
            ],

            'MCC-17' => [
                'Thermostat TD (Jig-1)',
                'Thermostat TD (Jig-2)',
                'Thermostat Housing (Jig-1)',
                'Thermostat Housing (Jig-2)',
            ],

            'MCC-16' => [
                'DUCT THERMOSTAT',
                'DUCT ASM WATER',
                'JIG 1 BRACKET ASM GENERATOR',
                'JIG 2 BRACKET ASM GENERATOR',
            ],

            'MCC-15' => [
                'H-7080',
                'H-7090',
                'H-7100',
                'H-7110',
                'Faching H-7791',
            ],

            'MCC-14' => [
                'H-9690',
                'H-9700',
                'H-9710',
                'H-9720',
                'H-5561',
            ],

            'MCC-13' => [
                'H-8440',
                'H-8450',
                'H-7791',
                'H-0580',
                'H-0600',
            ],

            'MCC-12' => [
                'H-9730',
                'H-9750',
                'H-7690',
                'H-7700',
                'H-7710',
                'H-7720',
            ],

            'MCC-11' => [
                'PUMP BODY OP1',
                'PUMP BODY OP2',
                'PUMP COVER',
                'WATER OUTLET',
                'CASE COMP THERMO',
            ],

            'MCC-10' => [
                'CASE COMP THERMO (OP-1-2-3-4-5-6)',
            ],

            'LT-8' => ['Leak Test', 'Plug'],

            'MCC-20' => [
                'Housing-1W',
                'YR-9 OP1',
                'YR-9 OP2',
            ],

            'MCC-19' => ['Housing-1W'],
            'MCC-18' => ['Housing-1W'],

            'MCB-4' => [
                'CWT-YL8',
                'CWO',
                'CWT-79100',
                'CWT-73000',
                'PLUG CYL BLOCK',
            ],
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $inserted = 0;
            $skipped  = 0;
            $notFound = [];

            foreach ($map as $machineCodeRaw => $parts) {
                $machineCode = $this->normalizeMachineCode($machineCodeRaw);

                $machine = $this->machineModel->where('machine_code', $machineCode)->first();
                if (!$machine) {
                    $notFound[] = "Machine not found: {$machineCodeRaw} (expected machine_code={$machineCode})";
                    continue;
                }

                $machineId = (int)$machine['id'];

                foreach ($parts as $partRaw) {
                    $partKey = $this->normalizePartKey($partRaw);
                    if ($partKey === '') continue;

                    $product = $this->findProductSmart($partKey);
                    if (!$product) {
                        $notFound[] = "Product not found for '{$partKey}' (machine {$machineCode})";
                        continue;
                    }

                    $productId = (int)$product['id'];

                    $exists = $this->standardModel
                        ->where(['machine_id' => $machineId, 'product_id' => $productId])
                        ->first();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $ctProdMc = (int)($product['cycle_time_machining'] ?? 0);
                    $ctMachine = ($ctProdMc > 0) ? $ctProdMc : 1; // aman untuk machining

                    $this->standardModel->insert([
                        'machine_id'                 => $machineId,
                        'product_id'                 => $productId,
                        'cycle_time_sec'             => $ctMachine, // CT mesin machining (default dari CT machining product)
                        'cycle_time_die_casting_sec' => (int)($product['cycle_time'] ?? 0),
                        'cycle_time_machining_sec'   => $ctProdMc,
                    ]);

                    $inserted++;
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error on seeding machining production_standards');
            }

            $db->transCommit();

            echo "MachiningProductionStandardsSeeder done.\n";
            echo "Inserted: {$inserted}\n";
            echo "Skipped (existing): {$skipped}\n";

            if (!empty($notFound)) {
                echo "---- NOT FOUND LIST ----\n";
                foreach ($notFound as $nf) echo "- {$nf}\n";
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function normalizeMachineCode(string $raw): string
    {
        return strtoupper(trim($raw));
    }

    /**
     * Hilangkan prefix angka "1." "2." dll kalau ada.
     */
    private function normalizePartKey(string $raw): string
    {
        $s = trim($raw);
        $s = preg_replace('/^\d+\.\s*/', '', $s);
        return trim($s);
    }

    /**
     * Cari produk dengan aturan:
     * - Kalau key "HXXXX" -> cari token "H-XXXX" di part_prod atau part_name
     * - Kalau key "H-XXXX" -> cari token "H-XXXX"
     * - Selain itu -> cari by part_name exact/like, fallback part_prod like, fallback part_no exact/like
     */
    private function findProductSmart(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') return null;

        // ======== RULE HXXXX -> token H-XXXX ========
        if (preg_match('/^H(\d{4})$/i', $key, $m)) {
            $token = 'H-' . $m[1];
            return $this->findByToken($token);
        }

        // ======== RULE H-XXXX (atau H-xxxxx) ========
        if (preg_match('/^H-\d{3,5}$/i', $key)) {
            return $this->findByToken(strtoupper($key));
        }

        // ======== RULE ada "H-####" di dalam teks, contoh: "Faching H-7791" ========
        if (preg_match('/(H-\d{3,5})/i', $key, $m2)) {
            $token = strtoupper($m2[1]);
            // coba token dulu (lebih akurat)
            $p = $this->findByToken($token);
            if ($p) return $p;
            // kalau tidak ketemu, lanjut cari nama full
        }

        // ======== RULE UMUM: cari part_name ========
        $p = $this->productModel->where('UPPER(part_name)', strtoupper($key))->first();
        if ($p) return $p;

        $p = $this->productModel->like('part_name', $key, 'both', null, true)->first();
        if ($p) return $p;

        // fallback part_prod
        $p = $this->productModel->where('UPPER(part_prod)', strtoupper($key))->first();
        if ($p) return $p;

        $p = $this->productModel->like('part_prod', $key, 'both', null, true)->first();
        if ($p) return $p;

        // fallback part_no (jaga-jaga)
        $p = $this->productModel->where('part_no', $key)->first();
        if ($p) return $p;

        $p = $this->productModel->like('part_no', $key, 'both', null, true)->first();
        if ($p) return $p;

        return null;
    }

    /**
     * Cari produk berdasarkan token (misal H-1320) dengan prioritas:
     * 1) part_prod exact
     * 2) part_prod like
     * 3) part_name like (sebagai holder)
     * 4) part_no exact (fallback)
     */
    private function findByToken(string $token): ?array
    {
        $token = strtoupper(trim($token));

        $p = $this->productModel->where('UPPER(part_prod)', $token)->first();
        if ($p) return $p;

        $p = $this->productModel->like('part_prod', $token, 'both', null, true)->first();
        if ($p) return $p;

        $p = $this->productModel->like('part_name', $token, 'both', null, true)->first();
        if ($p) return $p;

        $p = $this->productModel->where('part_no', $token)->first();
        if ($p) return $p;

        return null;
    }
}
