<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateProductsWeight extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('products', 'weight');

        $this->forge->addColumn('products', [
            'weight_ascas' => [
                'type'       => 'INT',
                'null'       => true,
                'comment'    => 'Weight as-cast (gram)'
            ],
            'weight_runner' => [
                'type'       => 'INT',
                'null'       => true,
                'comment'    => 'Runner weight (gram)'
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('products', ['weight_ascas', 'weight_runner']);

        $this->forge->addColumn('products', [
            'weight' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0
            ]
        ]);
    }
}
