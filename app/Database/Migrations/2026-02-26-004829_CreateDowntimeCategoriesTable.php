<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDowntimeCategoriesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Menggunakan process_id relasi ke production_processes
            'process_id' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'downtime_code' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'downtime_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1, // 1 = Aktif, 0 = Nonaktif
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        
        // Kombinasi process_id dan downtime_code harus unik
        $this->forge->addUniqueKey(['process_id', 'downtime_code']);

        $this->forge->createTable('downtime_categories');
    }

    public function down()
    {
        $this->forge->dropTable('downtime_categories');
    }
}