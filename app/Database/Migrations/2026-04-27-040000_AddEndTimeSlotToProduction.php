<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEndTimeSlotToProduction extends Migration
{
    public function up()
    {
        $forge = \Config\Database::forge();
        
        $fields = [
            'end_time_slot_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
        ];

        $db = db_connect();

        if ($db->tableExists('die_casting_production')) {
            if (!$db->fieldExists('end_time_slot_id', 'die_casting_production')) {
                $forge->addColumn('die_casting_production', $fields);
            }
        }

        if ($db->tableExists('daily_schedule_items')) {
            if (!$db->fieldExists('end_time_slot_id', 'daily_schedule_items')) {
                $forge->addColumn('daily_schedule_items', $fields);
            }
        }
    }

    public function down()
    {
        $forge = \Config\Database::forge();
        $db = db_connect();

        if ($db->tableExists('die_casting_production')) {
            if ($db->fieldExists('end_time_slot_id', 'die_casting_production')) {
                $forge->dropColumn('die_casting_production', 'end_time_slot_id');
            }
        }

        if ($db->tableExists('daily_schedule_items')) {
            if ($db->fieldExists('end_time_slot_id', 'daily_schedule_items')) {
                $forge->dropColumn('daily_schedule_items', 'end_time_slot_id');
            }
        }
    }
}
