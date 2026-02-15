<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UpdateVendorCodeAppSeeder extends Seeder
{
    public function run()
    {
        $db = db_connect();

        if (!$db->tableExists('vendors')) {
            throw new \RuntimeException("Table vendors tidak ditemukan.");
        }

        if (!$db->fieldExists('vendor_code_app', 'vendors')) {
            throw new \RuntimeException("Kolom vendors.vendor_code_app belum ada. Jalankan migration dulu.");
        }

        $vendors = $db->table('vendors')
            ->select('id, vendor_code_app')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $db->transBegin();
        try {
            $no = 1;
            foreach ($vendors as $v) {
                // kalau sudah ada isi, skip (biar aman)
                if (!empty($v['vendor_code_app'])) {
                    continue;
                }

                $db->table('vendors')
                    ->where('id', (int)$v['id'])
                    ->update([
                        'vendor_code_app' => 'VEND-' . $no,
                    ]);

                $no++;
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException("DB error saat update vendor_code_app");
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
