<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDowntimeDetailsTables extends Migration
{
    public function up()
    {
        // 1. Die Casting Hourly Downtime Details
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'hourly_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'downtime_category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'downtime_minute' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
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
        $this->forge->addKey('hourly_id');
        $this->forge->createTable('die_casting_hourly_downtime_details', true);

        // 2. Machining Hourly Downtime Details
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'machining_hourly_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'downtime_category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'downtime_minute' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
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
        $this->forge->addKey('machining_hourly_id');
        $this->forge->createTable('machining_hourly_downtime_details', true);
    }

    public function down()
    {
        $this->forge->dropTable('die_casting_hourly_downtime_details', true);
        $this->forge->dropTable('machining_hourly_downtime_details', true);
    }
}
