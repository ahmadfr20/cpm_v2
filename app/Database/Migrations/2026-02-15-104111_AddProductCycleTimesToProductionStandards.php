<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductCycleTimesToProductionStandards extends Migration
{
    public function up()
    {
        // Tambah kolom baru
        $this->forge->addColumn('production_standards', [
            'cycle_time_die_casting_sec' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'default'    => 0,
                'after'      => 'cycle_time_sec',
            ],
            'cycle_time_machining_sec' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'default'    => 0,
                'after'      => 'cycle_time_die_casting_sec',
            ],
        ]);

        // Backfill dari products (kalau sudah ada data lama)
        $db = \Config\Database::connect();
        $db->query("
            UPDATE production_standards ps
            JOIN products p ON p.id = ps.product_id
            SET
                ps.cycle_time_die_casting_sec = IFNULL(p.cycle_time, 0),
                ps.cycle_time_machining_sec   = IFNULL(p.cycle_time_machining, 0)
        ");
    }

    public function down()
    {
        $this->forge->dropColumn('production_standards', 'cycle_time_die_casting_sec');
        $this->forge->dropColumn('production_standards', 'cycle_time_machining_sec');
    }
}
