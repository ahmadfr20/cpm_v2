<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachinesMachiningSeeder extends Seeder
{
    public function run()
    {
        $processId = 2; // MC = Machining (sesuai tabel production_processes)

        // OPTIONAL: bersihkan dulu data machining biar tidak dobel
        // HATI-HATI kalau sudah ada transaksi yang FK ke machines.
        $this->db->table('machines')->where('process_id', $processId)->delete();

        $data = [
            // LINE 1
            [
                'machine_code'     => 'MCC-4',
                'machine_name'     => 'TC S2BO D',
                'production_line'  => 'LINE 1',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-3',
                'machine_name'     => 'TC S2BO A',
                'production_line'  => 'LINE 1',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],
            [
                'machine_code'     => 'MCC-2',
                'machine_name'     => 'FANUC D14',
                'production_line'  => 'LINE 1',
                'process_id'       => $processId,
                'line_position'    => 3,
            ],
            [
                'machine_code'     => 'MCC-1',
                'machine_name'     => 'TC S2BOF',
                'production_line'  => 'LINE 1',
                'process_id'       => $processId,
                'line_position'    => 4,
            ],
            [
                'machine_code'     => 'LT-2',
                'machine_name'     => '-',
                'production_line'  => 'LINE 1',
                'process_id'       => $processId,
                'line_position'    => 5,
            ],
            [
                'machine_code'     => 'CF-2',
                'machine_name'     => '-',
                'production_line'  => 'LINE 1',
                'process_id'       => $processId,
                'line_position'    => 6,
            ],

            // LINE 4
            [
                'machine_code'     => 'MCC-9',
                'machine_name'     => 'FANUC RD',
                'production_line'  => 'LINE 4',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-8',
                'machine_name'     => 'TC S2BO I',
                'production_line'  => 'LINE 4',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],

            // LINE 6
            [
                'machine_code'     => 'MCC-7',
                'machine_name'     => 'TC S2BO E',
                'production_line'  => 'LINE 6',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-6',
                'machine_name'     => 'TC S2BO C',
                'production_line'  => 'LINE 6',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],

            // LINE 7
            [
                'machine_code'     => 'MCB-1',
                'machine_name'     => 'Brother TCC 1000 B - Leak Test',
                'production_line'  => 'LINE 7',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-5',
                'machine_name'     => 'TC S2BO G',
                'production_line'  => 'LINE 7',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],

            // LINE 10
            [
                'machine_code'     => 'MCC-17',
                'machine_name'     => 'TC S2BO J',
                'production_line'  => 'LINE 10',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-16',
                'machine_name'     => 'Brother TC S2D B',
                'production_line'  => 'LINE 10',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],
            [
                'machine_code'     => 'MCC-15',
                'machine_name'     => 'Brother TC S2AO',
                'production_line'  => 'LINE 10',
                'process_id'       => $processId,
                'line_position'    => 3,
            ],
            [
                'machine_code'     => 'MCC-14',
                'machine_name'     => 'Brother TC S2B',
                'production_line'  => 'LINE 10',
                'process_id'       => $processId,
                'line_position'    => 4,
            ],

            // LINE 11
            [
                'machine_code'     => 'MCC-13',
                'machine_name'     => 'Brother TC S2BO B',
                'production_line'  => 'LINE 11',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-12',
                'machine_name'     => 'Brother TC S2DN A',
                'production_line'  => 'LINE 11',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],
            [
                'machine_code'     => 'MCC-11',
                'machine_name'     => 'Brother TC S2DN B',
                'production_line'  => 'LINE 11',
                'process_id'       => $processId,
                'line_position'    => 3,
            ],
            [
                'machine_code'     => 'MCC-10',
                'machine_name'     => 'Brother TC S2DN C',
                'production_line'  => 'LINE 11',
                'process_id'       => $processId,
                'line_position'    => 4,
            ],

            // LINE 13
            [
                'machine_code'     => 'LT-8',
                'machine_name'     => '-',
                'production_line'  => 'LINE 13',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-20',
                'machine_name'     => 'Brother TC R2B',
                'production_line'  => 'LINE 13',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],

            // LINE 14
            [
                'machine_code'     => 'MCC-19',
                'machine_name'     => 'TC S2BO H',
                'production_line'  => 'LINE 14',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
            [
                'machine_code'     => 'MCC-18',
                'machine_name'     => 'TC S2D A',
                'production_line'  => 'LINE 14',
                'process_id'       => $processId,
                'line_position'    => 2,
            ],

            // LINE 15
            [
                'machine_code'     => 'MCB-4',
                'machine_name'     => 'Brother TC 200',
                'production_line'  => 'LINE 15',
                'process_id'       => $processId,
                'line_position'    => 1,
            ],
        ];

        $this->db->table('machines')->insertBatch($data);
    }
}
