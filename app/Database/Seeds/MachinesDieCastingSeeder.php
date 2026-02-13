<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MachinesDieCastingSeeder extends Seeder
{
    public function run()
    {
        $processId = 1; // DC = Die Casting (sesuai production_processes)

        // OPTIONAL: hapus dulu semua mesin die casting biar tidak dobel
        // HATI-HATI kalau sudah ada transaksi terkait.
        $this->db->table('machines')->where('process_id', $processId)->delete();

        $data = [];

        for ($i = 1; $i <= 14; $i++) {
            $codeNumber = str_pad((string) $i, 2, '0', STR_PAD_LEFT); // 01..14

            $data[] = [
                'machine_code'    => 'DC-' . $codeNumber,
                'machine_name'    => 'DIE CASTING ' . $codeNumber,
                'production_line' => 'DIE CASTING',
                'process_id'      => $processId,
                'line_position'   => $i, // urut 1..14
            ];
        }

        $this->db->table('machines')->insertBatch($data);
    }
}
