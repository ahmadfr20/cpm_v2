<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShotWeightToProducts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('products', [
            'shot_weight' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
                'after'      => 'customer_id' // Just putting it after customer_id roughly
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('products', 'shot_weight');
    }
}
