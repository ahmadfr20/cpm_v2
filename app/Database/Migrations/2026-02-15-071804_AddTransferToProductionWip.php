<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTransferToProductionWip extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('production_wip')) return;

        if (!$this->db->fieldExists('transfer', 'production_wip')) {
            $this->forge->addColumn('production_wip', [
                'transfer' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                    'after'      => 'stock', // taruh setelah stock biar rapi
                ],
            ]);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('production_wip')) return;

        if ($this->db->fieldExists('transfer', 'production_wip')) {
            $this->forge->dropColumn('production_wip', 'transfer');
        }
    }
}
