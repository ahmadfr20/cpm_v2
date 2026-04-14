<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQCTables extends Migration
{
    public function up()
    {
        // Table qc_inspections
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true
            ],
            'production_date' => [
                'type' => 'DATE',
            ],
            'shift_id' => [
                'type' => 'INT',
            ],
            'product_id' => [
                'type' => 'INT',
            ],
            'qty_in' => [
                'type' => 'INT',
                'default' => 0
            ],
            'qty_ok' => [
                'type' => 'INT',
                'default' => 0
            ],
            'qty_ng' => [
                'type' => 'INT',
                'default' => 0
            ],
            'inspected_by' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('qc_inspections');

        // Table qc_inspection_ngs
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true
            ],
            'qc_inspection_id' => [
                'type' => 'INT',
            ],
            'ng_category_id' => [
                'type' => 'INT',
            ],
            'qty' => [
                'type' => 'INT',
                'default' => 0
            ],
            'image_path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('qc_inspection_ngs');
    }

    public function down()
    {
        $this->forge->dropTable('qc_inspection_ngs', true);
        $this->forge->dropTable('qc_inspections', true);
    }
}
