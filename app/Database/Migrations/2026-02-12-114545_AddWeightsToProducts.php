<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWeightsToProducts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('products', [
            'weight_die_casting' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => false,
                'default'    => 0,
                'after'      => 'weight_runner',
            ],
            'weight_machining' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => false,
                'default'    => 0,
                'after'      => 'weight_die_casting',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('products', 'weight_die_casting');
        $this->forge->dropColumn('products', 'weight_machining');
    }
}
