<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UpdateCustomerCodeAppSeeder extends Seeder
{
    public function run()
    {
        $db = db_connect();

        // Pastikan kolomnya ada (opsional, tapi aman)
        if (!$db->fieldExists('customer_code_app', 'customers')) {
            throw new \RuntimeException("Kolom customers.customer_code_app belum ada. Jalankan migration dulu.");
        }

        // Update semua row: CUST-{id}
        // Contoh: id=1 => CUST-1
        $db->query("
            UPDATE customers
            SET customer_code_app = CONCAT('CUST-', id)
            WHERE customer_code_app IS NULL OR customer_code_app = ''
        ");
    }
}
