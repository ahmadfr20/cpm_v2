<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDieCastingHourly extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
                'unsigned'       => true,
            ],
            'production_date' => [
                'type' => 'DATE',
            ],
            'shift_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'time_slot_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'machine_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'product_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'qty_fg' => [
                'type'    => 'INT',
                'default' => 0,
            ],
            'qty_ng' => [
                'type'    => 'INT',
                'default' => 0,
            ],
            'ng_category' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'downtime_minute' => [
                'type'    => 'INT',
                'default' => 0,
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

        // ===== FOREIGN KEY =====
        $this->forge->addForeignKey('shift_id', 'shifts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('time_slot_id', 'time_slots', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('machine_id', 'machines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('die_casting_hourly');
    }

    public function down()
    {
        $this->forge->dropTable('die_casting_hourly');
    }
}
