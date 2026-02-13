<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductProcessFlowsSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        // 1) Ambil process_id berdasarkan process_code (biar tidak hardcode ID)
        $wantedProcessCodes = ['DC','MC','LT','AB','AS','FI','FG'];

        $procRows = $db->table('production_processes')
            ->select('id, process_code')
            ->whereIn('process_code', $wantedProcessCodes)
            ->get()->getResultArray();

        $procIdByCode = [];
        foreach ($procRows as $r) {
            $procIdByCode[strtoupper($r['process_code'])] = (int) $r['id'];
        }

        foreach ($wantedProcessCodes as $code) {
            if (!isset($procIdByCode[$code])) {
                throw new \RuntimeException("Process code '{$code}' tidak ditemukan di production_processes.");
            }
        }

        // 2) Mapping label flow -> process_code
        // ASSY akan dipecah jadi AB lalu AS
        $labelToProcessCodes = [
            'CASTING'     => ['DC'],
            'M/C'         => ['MC'],
            'LEAK TEST'   => ['LT'],
            'ASSY'        => ['AB','AS'],
            'FINAL INPEC' => ['FI'],
            'F/G'         => ['FG'],
        ];

        // 3) Flow dari Excel (Sheet: Flow Process Rev1)
        // - Sudah otomatis: start dari CASTING
        // - R/M, BURRYTORY, SAND BLASTING, JIG PLUG, PAINTING tidak ikut
        $flowsByPart = [
            'APV + LEAK TEST' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'BOTTOM STROMA 1' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'Carriage FT-2 + Assy' => ['CASTING', 'M/C', 'ASSY', 'Final Inpec', 'F/G'],
            'Carriage Lotus 2+ Assy' => ['CASTING', 'M/C', 'ASSY', 'Final Inpec', 'F/G'],
            'Case Comp \'(OP-4-5-6)' => ['CASTING', 'M/C', 'LEAK TEST', 'ASSY', 'Final Inpec', 'F/G'],
            'CGC 4JA-1' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'Cover LW' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'COVER STROMA 1' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'COVER STROMA X' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'CWO' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'CWT YL-8' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'CWT-73000' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'CWT-79100' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'D-1' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-10' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-11' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-12' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-13' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-14' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-2' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-3' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-4' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-5' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-6' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-7' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-8' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'D-9' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'Duct Asm + Leak Test' => ['CASTING', 'M/C', 'LEAK TEST', 'ASSY', 'Final Inpec', 'F/G'],
            'HOLDER-7100' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7110' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7120' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7130' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7140' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7150' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7160' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7170' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7180' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7190' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7200' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7210' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7220' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7230' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7240' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7250' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7260' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7270' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7280' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7290' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOLDER-7300' => ['CASTING', 'M/C', 'Final Inpec', 'F/G'],
            'HOUSING 0160' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'MOTOR CASE' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'MOTOR CASE + LEAK TEST' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'PLATE + LEAK TEST' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'Rotor \'+ Leak Test' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
            'TROCHOID COVER + Leak Test' => ['CASTING', 'M/C', 'LEAK TEST', 'Final Inpec', 'F/G'],
        ];

        // 4) Helper: cari product_id (case-insensitive + anti mismatch spasi)
        $findProductId = function (string $part) use ($db): ?int {
            $part = trim($part);
            if ($part === '') return null;

            // 4.1 exact match: part_no / part_name (case-insensitive)
            $row = $db->query(
                "SELECT id FROM products
                 WHERE LOWER(part_no) = LOWER(?)
                    OR LOWER(part_name) = LOWER(?)
                 LIMIT 1",
                [$part, $part]
            )->getRowArray();
            if ($row) return (int)$row['id'];

            // 4.2 normalize spasi: cocokkan setelah remove spasi
            $row = $db->query(
                "SELECT id FROM products
                 WHERE REPLACE(LOWER(part_no),' ','') = REPLACE(LOWER(?),' ','')
                    OR REPLACE(LOWER(part_name),' ','') = REPLACE(LOWER(?),' ','')
                 LIMIT 1",
                [$part, $part]
            )->getRowArray();
            if ($row) return (int)$row['id'];

            // 4.3 khusus HOLDER-XXXX: assign ke produk HOLDER (kalau master produk holder pakai part_name=Holder)
            if (preg_match('/^HOLDER-\d+/i', $part)) {
                $row = $db->query(
                    "SELECT id FROM products
                     WHERE (LOWER(part_name) LIKE '%holder%')
                       AND (LOWER(part_no) = LOWER(?) OR REPLACE(LOWER(part_no),' ','') = REPLACE(LOWER(?),' ',''))
                     LIMIT 1",
                    [$part, $part]
                )->getRowArray();

                if ($row) return (int)$row['id'];

                // fallback paling longgar: kalau ada 1 produk bernama holder, pakai itu
                $row = $db->query(
                    "SELECT id FROM products
                     WHERE LOWER(part_name) LIKE '%holder%'
                     LIMIT 1"
                )->getRowArray();

                if ($row) return (int)$row['id'];
            }

            return null;
        };

        $ppf = $db->table('product_process_flows');

        // 5) Insert flow berdasarkan Excel
        foreach ($flowsByPart as $part => $labels) {
            $productId = $findProductId($part);
            if (!$productId) {
                // kalau mau, aktifkan log ini untuk cek part yang tidak match di DB
                // log_message('warning', "Product tidak ditemukan untuk PART: {$part}");
                continue;
            }

            // delete existing flow agar idempotent
            $ppf->where('product_id', $productId)->delete();

            $sequence = 1;
            $seenProcess = [];

            foreach ($labels as $label) {
                $key = strtoupper(trim($label));

                // normalisasi "Final Inpec" (kadang beda kapital)
                if ($key === 'FINAL INPEC' || $key === 'FINAL INPEC ') {
                    $key = 'FINAL INPEC';
                }
                if ($key === 'F/G') {
                    $key = 'F/G';
                }

                // map label -> process_code(s)
                $processCodes = null;
                foreach ($labelToProcessCodes as $k => $codes) {
                    if (strtoupper($k) === $key) {
                        $processCodes = $codes;
                        break;
                    }
                }
                if (!$processCodes) continue;

                foreach ($processCodes as $pCode) {
                    $processId = $procIdByCode[$pCode] ?? null;
                    if (!$processId) continue;

                    // avoid duplicates
                    $dupKey = $productId . '|' . $processId;
                    if (isset($seenProcess[$dupKey])) continue;
                    $seenProcess[$dupKey] = true;

                    $ppf->insert([
                        'product_id' => $productId,
                        'process_id' => $processId,
                        'sequence'   => $sequence++,
                        'is_active'  => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        // 6) Fallback: pastikan SEMUA product di DB punya flow minimal
        // Default: CASTING -> M/C -> Final Inpec -> F/G
        $default = ['CASTING','M/C','Final Inpec','F/G'];

        $products = $db->table('products')->select('id')->get()->getResultArray();

        foreach ($products as $p) {
            $pid = (int)$p['id'];

            $has = $db->table('product_process_flows')
                ->where('product_id', $pid)
                ->countAllResults();

            if ($has > 0) continue;

            $seq = 1;
            foreach ($default as $label) {
                $key = strtoupper(trim($label));
                if ($key === 'FINAL INPEC') $key = 'FINAL INPEC';

                $processCodes = $labelToProcessCodes[$key] ?? null;
                if (!$processCodes) continue;

                foreach ($processCodes as $pCode) {
                    $ppf->insert([
                        'product_id' => $pid,
                        'process_id' => $procIdByCode[$pCode],
                        'sequence'   => $seq++,
                        'is_active'  => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }
}
