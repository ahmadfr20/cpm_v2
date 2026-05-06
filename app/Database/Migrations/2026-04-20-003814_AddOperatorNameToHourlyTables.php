<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOperatorNameToHourlyTables extends Migration
{
    public function up()
    {
        $fields = [
            'operator_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
        ];
        
        // Cek dan tambahkan ke die_casting_hourly jika belum ada
        if ($this->db->tableExists('die_casting_hourly')) {
            if (!$this->db->fieldExists('operator_name', 'die_casting_hourly')) {
                $this->forge->addColumn('die_casting_hourly', $fields);
            }
        }
        
        // Cek dan tambahkan ke machining_hourly jika belum ada
        if ($this->db->tableExists('machining_hourly')) {
            if (!$this->db->fieldExists('operator_name', 'machining_hourly')) {
                $this->forge->addColumn('machining_hourly', $fields);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('die_casting_hourly') && $this->db->fieldExists('operator_name', 'die_casting_hourly')) {
            $this->forge->dropColumn('die_casting_hourly', 'operator_name');
        }
        if ($this->db->tableExists('machining_hourly') && $this->db->fieldExists('operator_name', 'machining_hourly')) {
            $this->forge->dropColumn('machining_hourly', 'operator_name');
        }
    }
}
