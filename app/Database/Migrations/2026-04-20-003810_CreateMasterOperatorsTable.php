<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMasterOperatorsTable extends Migration
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
            'operator_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'section' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
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
        $this->forge->createTable('master_operators');
    }

    public function down()
    {
        $this->forge->dropTable('master_operators');
    }
}
