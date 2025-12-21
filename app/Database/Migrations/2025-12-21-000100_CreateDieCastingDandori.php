<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDieCastingDandori extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true
            ],
            'dandori_date' => [
                'type' => 'DATE',
                'null' => false
            ],
            'shift_id' => [
                'type' => 'INT',
                'null' => false
            ],
            'machine_id' => [
                'type' => 'INT',
                'null' => false
            ],
            'product_id' => [
                'type' => 'INT',
                'null' => false
            ],
            'activity' => [
                'type' => 'VARCHAR',
                'constraint' => 100
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('shift_id');
        $this->forge->addKey('machine_id');
        $this->forge->addKey('product_id');

        $this->forge->createTable('die_casting_dandori');
    }

    public function down()
    {
        $this->forge->dropTable('die_casting_dandori');
    }
}
