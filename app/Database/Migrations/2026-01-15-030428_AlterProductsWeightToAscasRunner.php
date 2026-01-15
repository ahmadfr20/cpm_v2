<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterProductsWeightToAscasRunner extends Migration
{
    public function up()
    {
        // DROP kolom lama (kalau ada)
        if ($this->db->fieldExists('weight', 'products')) {
            $this->forge->dropColumn('products', 'weight');
        }

        // ADD kolom baru (kalau belum ada)
        if (!$this->db->fieldExists('weight_ascas', 'products')) {
            $this->forge->addColumn('products', [
                'weight_ascas' => [
                    'type' => 'INT',
                    'null' => true,
                    'after' => 'customer_id',
                ],
            ]);
        }

        if (!$this->db->fieldExists('weight_runner', 'products')) {
            $this->forge->addColumn('products', [
                'weight_runner' => [
                    'type' => 'INT',
                    'null' => true,
                    'after' => 'weight_ascas',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('weight_ascas', 'products')) {
            $this->forge->dropColumn('products', 'weight_ascas');
        }

        if ($this->db->fieldExists('weight_runner', 'products')) {
            $this->forge->dropColumn('products', 'weight_runner');
        }

        if (!$this->db->fieldExists('weight', 'products')) {
            $this->forge->addColumn('products', [
                'weight' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 0,
                ],
            ]);
        }
    }
}
