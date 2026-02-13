<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPartProdToProducts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('products', [
            'part_prod' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'part_name', // taruh setelah part_name (ubah kalau mau)
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('products', 'part_prod');
    }
}
