<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\MachineModel;
use App\Models\ProductModel;
use App\Models\ProductionStandardModel;

class ProductionStandardsSeeder extends Seeder
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
         * Mapping dari gambar:
         * Key pakai format "DC 06" dst, nanti dinormalisasi jadi "DC-06"
         */
        $map = [
            'DC 06' => [
                'H1320','H1330','H1340','H1350','H1220','H1230','H1240','H1250',
                'H7690','H9690','H1481','H1501','H1910','H1920','H0590','H0610',
                'H0580','H0600','H7700','H7720','BRACKET','H1650','H1660','H1670',
                'H1680','H7710','H1750','H1760','H1W#4','H9700','H9710','H9720',
                'H9730','H9740','H9750','H9760','H7590','H7600','H7610','H7620',
                'CASE COM H 7720','CASE COM POLE SHAFT H 2100','H7110',
                'CASE COMP#2','CASE COMP#3','CAP OIL SND',
            ],

            'DC 07' => [
                'H1220','H1230','H1240','H1250','H1320','H1330','H1340','H1350',
                'H1670','H7600','H7610','H7620',
                'H9700','H9710','H9720','H9730','H9750',
                'H9690','APV','LICENSE','SPACER','STEERING','HANDLE',
                'FOOTPEG','FOOTPEG',
                'H1750','H1760','H7590','H7690','H0580',
                'FT-2#1','FT-2#2','BAMBOO',
                'H7791',
            ],

            'DC 08' => [
                'H7100','H7110','H7700','H7720','H0580','H0590','H0600','H0610',
                'H1750','H1760','H1910','H1920','H9700','H9710','H9720','H9730',
                'H9750','H9740','LOTUS','FT-2#1','FT-2#2','BAMBOO','CWO',
                'H1220','H1230','H1240','H1250','H7791','H7710','H7610','H9760',
                'POLE SHAFT','H1481','H1501','APV',
                'H2090','H2100','H2110','H2120','H9690',
            ],

            'DC 09' => [
                'BAMBOO','FT-2#1','FT-2#2','RY-9','H1470','H1490','H1650','H1660',
                'H1670','H1680','H9740','H9760','H9750','H1481','H1501','H7791',
                'H7720','H7700','MSG','H1220','H1230','H1240','H1250','H1320','H1330',
                'H1340','H1350','APV','H7690','H7100','H7110','LOTUS','H9750',
                'FOOTPEG IYL-8','FOOTPEG IH 0610','H1680',
            ],

            'DC 12' => [
                'CRANK CASE','COVER LW','TANK LW','4 JA','REAR HANDLE','SND 1','SND 2',
                'BOTTOM STROMA 1','COVER STROMA 1','BODY STROMA X','COVER STROMA X',
            ],

            'DC 13' => [
                'H1W#4','H5050#2','THERMOSTAT HOUSING NKI','DUCT THERMOSTAT','DUCT ASM',
                'H1470','H1490','BRACKET ASM','H0600','H0610','H0580','H0590',
                'H1650','H1660','H1670','H1680',
                'H1320','H1340','H1350','H7610','H7620','H7700','H7720','H5050#1',
                'H7590','H9710','H9720','H1330',
            ],

            'DC 14' => [
                'H5050#1','H5050#2','THERMOSTAT HOUSING NKI','H1470','DUCT ASM','DUCT THERMOSTAT',
                'H1490','H1650','H1660','H1670','H1680',
                'H0580','H0590','H0610','STEERING','H1350','H1340','H1330','H1320',
                'BRACKET ASM','H7590','H7600','H7620','H1910','H1920','H9710','H1W#6',
                'THERMOSTAT TD',
            ],
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $totalInserted = 0;
            $totalSkipped  = 0;
            $notFound      = [];

            foreach ($map as $dcKey => $productKeys) {
                // Normalize "DC 06" -> "DC-06"
                $machineCode = $this->normalizeDcMachineCode($dcKey);

                $machine = $this->machineModel->where('machine_code', $machineCode)->first();
                if (!$machine) {
                    $notFound[] = "Machine not found: {$dcKey} (expected machine_code={$machineCode})";
                    continue;
                }

                $machineId = (int)$machine['id'];

                foreach ($productKeys as $keyRaw) {
                    $key = trim((string)$keyRaw);
                    if ($key === '') continue;

                    $product = $this->findProductSmart($key);

                    if (!$product) {
                        $notFound[] = "Product not found for '{$key}' (machine {$machineCode})";
                        continue;
                    }

                    $productId = (int)$product['id'];

                    // Cek existing
                    $exists = $this->standardModel
                        ->where(['machine_id' => $machineId, 'product_id' => $productId])
                        ->first();

                    if ($exists) {
                        $totalSkipped++;
                        continue;
                    }

                    // Semua DC => CT mesin 0 (sesuai rule kamu)
                    $this->standardModel->insert([
                        'machine_id'                 => $machineId,
                        'product_id'                 => $productId,
                        'cycle_time_sec'             => 0,
                        'cycle_time_die_casting_sec' => (int)($product['cycle_time'] ?? 0),
                        'cycle_time_machining_sec'   => (int)($product['cycle_time_machining'] ?? 0),
                    ]);

                    $totalInserted++;
                }

                // Pastikan semua standar untuk DC ini CT mesin 0
                $this->standardModel
                    ->where('machine_id', $machineId)
                    ->set(['cycle_time_sec' => 0])
                    ->update();
            }

            if ($db->transStatus() === false) {
                throw new \Exception('DB error on seeding production_standards');
            }

            $db->transCommit();

            echo "ProductionStandardsSeeder done.\n";
            echo "Inserted: {$totalInserted}\n";
            echo "Skipped (existing): {$totalSkipped}\n";

            if (!empty($notFound)) {
                echo "---- NOT FOUND LIST ----\n";
                foreach ($notFound as $nf) echo "- {$nf}\n";
            }

        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Normalize DC key:
     * "DC 06" / "DC06" / "dc-06" -> "DC-06"
     */
    private function normalizeDcMachineCode(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        $raw = str_replace([' ', '_'], '', $raw); // DC06 atau DC-06 tetap
        // Pastikan format "DC-XX"
        if (preg_match('/^DC-?\d{1,2}$/', $raw)) {
            $num = (int)preg_replace('/\D/', '', $raw);
            return 'DC-' . str_pad((string)$num, 2, '0', STR_PAD_LEFT);
        }

        // fallback: jika input aneh, coba ambil angka
        $num = (int)preg_replace('/\D/', '', $raw);
        return 'DC-' . str_pad((string)$num, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Cari product dengan aturan:
     * - Jika key HXXXX -> cari "H-XXXX" di part_prod atau part_name (LIKE)
     * - Selain itu:
     *    1) coba part_no exact
     *    2) coba part_prod exact
     *    3) coba part_name exact (case-insensitive)
     *    4) fallback LIKE part_prod / part_name
     */
    private function findProductSmart(string $key): ?array
    {
        $key = trim($key);

        // ========= RULE KHUSUS HXXXX =========
        if (preg_match('/^H(\d{4})$/i', $key, $m)) {
            $num = $m[1];
            $hDash = "H-{$num}";  // H1320 -> H-1320

            // Prioritas: part_prod mengandung H-1320
            $p = $this->productModel->where('part_prod', $hDash)->first();
            if ($p) return $p;

            $p = $this->productModel->like('part_prod', $hDash, 'both', null, true)->first();
            if ($p) return $p;

            // Fallback: part_name mengandung H-1320
            $p = $this->productModel->like('part_name', $hDash, 'both', null, true)->first();
            if ($p) return $p;

            // Last fallback: kalau ternyata disimpan di part_no
            $p = $this->productModel->where('part_no', $key)->first();
            if ($p) return $p;

            return null;
        }

        // ========= RULE UMUM =========
        // 1) part_no exact
        $p = $this->productModel->where('part_no', $key)->first();
        if ($p) return $p;

        // 2) part_prod exact
        $p = $this->productModel->where('part_prod', $key)->first();
        if ($p) return $p;

        // 3) part_name exact case-insensitive
        $p = $this->productModel->where('UPPER(part_name)', strtoupper($key))->first();
        if ($p) return $p;

        // 4) LIKE part_prod
        $p = $this->productModel->like('part_prod', $key, 'both', null, true)->first();
        if ($p) return $p;

        // 5) LIKE part_name
        $p = $this->productModel->like('part_name', $key, 'both', null, true)->first();
        if ($p) return $p;

        return null;
    }
}
