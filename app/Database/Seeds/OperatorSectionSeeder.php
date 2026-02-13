<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class OperatorSectionSeeder extends Seeder
{
    public function run()
    {
        $defaultPassword = '123456';
        $hashedPassword  = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $operators = [
            [
                'username' => 'op_die_casting',
                'fullname' => 'Operator Die Casting',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'DIE_CASTING',
            ],
            [
                'username' => 'op_machining',
                'fullname' => 'Operator Machining',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'MACHINING',
            ],
            [
                'username' => 'op_leak_test',
                'fullname' => 'Operator Leak Test',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'LEAK_TEST',
            ],
            [
                'username' => 'op_assy_shaft',
                'fullname' => 'Operator Assy Shaft',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'ASSY_SHAFT',
            ],
            [
                'username' => 'op_assy_bushing',
                'fullname' => 'Operator Assy Bushing',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'ASSY_BUSHING',
            ],
            [
                'username' => 'op_shot_blasting',
                'fullname' => 'Operator Shot Blasting',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'SHOT_BLASTING',
            ],
            [
                'username' => 'op_baritori',
                'fullname' => 'Operator Baritori',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'BARITORI',
            ],
            [
                'username' => 'op_painting',
                'fullname' => 'Operator Painting',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'PAINTING',
            ],
            [
                'username' => 'op_final_inspection',
                'fullname' => 'Operator Final Inspection',
                'password' => $hashedPassword,
                'role'     => 'OPERATOR',
                'section'  => 'FINAL_INSPECTION',
            ],
        ];

        // Insert aman: skip kalau username sudah ada
        foreach ($operators as $op) {
            $exists = $this->db->table('users')
                ->select('id')
                ->where('username', $op['username'])
                ->get()
                ->getRow();

            if (! $exists) {
                $this->db->table('users')->insert($op);
            }
        }
    }
}
