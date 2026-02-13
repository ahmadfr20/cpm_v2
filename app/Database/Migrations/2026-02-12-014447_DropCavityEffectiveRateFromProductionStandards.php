<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropCavityEffectiveRateFromProductionStandards extends Migration
{
    public function up()
    {
        // Pastikan tabelnya ada
        if (!$this->db->tableExists('production_standards')) {
            return;
        }

        // Drop kolom kalau ada
        if ($this->db->fieldExists('cavity', 'production_standards')) {
            $this->forge->dropColumn('production_standards', 'cavity');
        }

        if ($this->db->fieldExists('effective_rate', 'production_standards')) {
            $this->forge->dropColumn('production_standards', 'effective_rate');
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('production_standards')) {
            return;
        }

        // Balikin lagi kalau butuh rollback
        if (!$this->db->fieldExists('cavity', 'production_standards')) {
            $this->forge->addColumn('production_standards', [
                'cavity' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'cycle_time_sec',
                ],
            ]);
        }

        if (!$this->db->fieldExists('effective_rate', 'production_standards')) {
            $this->forge->addColumn('production_standards', [
                'effective_rate' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '6,2',
                    'default'    => 100.00,
                    'null'       => false,
                    'after'      => 'cavity',
                ],
            ]);
        }
    }
}
