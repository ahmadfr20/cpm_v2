<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRemarkToHourlyTables extends Migration
{
    public function up()
    {
        $fields = [
            'remark' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'downtime_minute',
            ],
        ];

        if ($this->db->tableExists('die_casting_hourly')) {
            $this->forge->addColumn('die_casting_hourly', $fields);
        }

        if ($this->db->tableExists('machining_hourly')) {
            $this->forge->addColumn('machining_hourly', $fields);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('die_casting_hourly') && $this->db->fieldExists('remark', 'die_casting_hourly')) {
            $this->forge->dropColumn('die_casting_hourly', 'remark');
        }

        if ($this->db->tableExists('machining_hourly') && $this->db->fieldExists('remark', 'machining_hourly')) {
            $this->forge->dropColumn('machining_hourly', 'remark');
        }
    }
}
