<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVendorCodeAppToVendors extends Migration
{
    public function up()
    {
        // Pastikan tabel vendors ada
        if (!$this->db->tableExists('vendors')) {
            throw new \RuntimeException('Table vendors tidak ditemukan.');
        }

        // Kalau kolom sudah ada, skip
        if ($this->db->fieldExists('vendor_code_app', 'vendors')) {
            return;
        }

        $this->forge->addColumn('vendors', [
            'vendor_code_app' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'vendor_code', // taruh setelah vendor_code
            ],
        ]);

        // Optional: index untuk pencarian cepat
        $this->forge->addKey('vendor_code_app');
    }

    public function down()
    {
        if ($this->db->tableExists('vendors') && $this->db->fieldExists('vendor_code_app', 'vendors')) {
            $this->forge->dropColumn('vendors', 'vendor_code_app');
        }
    }
}
