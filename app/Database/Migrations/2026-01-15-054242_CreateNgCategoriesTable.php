<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNgCategoriesTable extends Migration
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
            'ng_code' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'process_code' => [
                'type'       => 'INT',
                'constraint' => 11,
                'comment'    => '2=Die Casting, 5=Machining',
            ],
            'process_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'ng_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        $this->forge->addUniqueKey(['process_code', 'ng_code']);

        $this->forge->createTable('ng_categories');
    }

    public function down()
    {
        $this->forge->dropTable('ng_categories');
    }
}
