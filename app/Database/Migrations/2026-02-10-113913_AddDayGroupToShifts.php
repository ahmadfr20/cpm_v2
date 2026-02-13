<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDayGroupToShifts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('shifts', [
            'day_group' => [
                'type'       => 'ENUM',
                'constraint' => ['MON_THU', 'FRI', 'SAT', 'SUN'],
                'default'    => 'MON_THU',
                'null'       => false,
                'after'      => 'shift_name',
            ],
        ]);

        // Optional index supaya query cepat (mis. cari shift per grup hari)
        $this->forge->addKey('day_group');
    }

    public function down()
    {
        $this->forge->dropColumn('shifts', 'day_group');
    }
}
