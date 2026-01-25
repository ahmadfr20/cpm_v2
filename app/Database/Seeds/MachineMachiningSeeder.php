<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachineMachiningSeeder extends Seeder
{
    public function run()
    {
        $process = $this->db->table('production_processes')
            ->where('process_name', 'Machining')
            ->get()
            ->getRowArray();

        if (!$process) {
            echo "❌ Process Machining not found\n";
            return;
        }

        $processId = $process['id'];

        /**
         * FORMAT:
         * [line_position, machine_code, machine_name]
         */
        $machines = [

            // ================= LINE 1 =================
            [1,'MCC-4','TC S2BO D'],
            [1,'MCC-3','TC S2BO A'],
            [1,'MCC-2','FANUC DT+'],
            [1,'MCC-1','TC S2BOF'],
            [1,'LT-2','-'],
            [1,'CF-2','-'],

            // ================= LINE 4 =================
            [4,'MCC-9','FANUC RD'],
            [4,'MCC-8','TC S2BO I'],

            // ================= LINE 5 =================
            [5,'ASSY','Assy Part'],
            [5,'ASSY','Assy Part'],
            [5,'ASSY','Leak Test'],

            // ================= LINE 6 =================
            [6,'MCC-7','TC S2BO E'],
            [6,'MCC-6','TC S2BO C'],

            // ================= LINE 7 =================
            [7,'MCB-1','TCC 1000 B'],
            [7,'MCB-1','Leak Test'],
            [7,'MCC-5','TC S2BO G'],

            // ================= LINE 10 =================
            [10,'MCC-17','TC S2BO J'],
            [10,'MCC-16','TC S2D B'],
            [10,'MCC-15','TC S2AO'],
            [10,'MCC-14','TC S2B'],

            // ================= LINE 11 =================
            [11,'MCC-13','TC S2BO B'],
            [11,'MCC-12','TC S2DN A'],
            [11,'MCC-11','TC S2DN B'],
            [11,'MCC-10','TC S2DN C'],

            // ================= LINE 13 =================
            [13,'LT-8','-'],
            [13,'MCC-20','TC R2B'],

            // ================= LINE 14 =================
            [14,'MCC-19','TC S2BO H'],
            [14,'MCC-18','TC S2D A'],

            // ================= LINE 15 =================
            [15,'MCB-4','TC 200'],
        ];

        foreach ($machines as $m) {
            $this->db->table('machines')->insert([
                'line_position'   => $m[0],
                'machine_code'    => $m[1],
                'machine_name'    => $m[2],
                'process_id'      => $processId,
            ]);
        }

        echo "✅ Machining machines seeded (line_position based)\n";
    }
}
