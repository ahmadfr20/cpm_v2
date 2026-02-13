<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserOperatorSeeder extends Seeder
{
    public function run()
    {
        $data = [
            'username' => 'operator',
            'fullname' => 'User Operator',
            'password' => password_hash('operator12345', PASSWORD_BCRYPT),
            'role'     => 'OPERATOR',
        ];

        $exists = $this->db->table('users')
            ->where('username', $data['username'])
            ->countAllResults();

        if ($exists > 0) {
            $this->db->table('users')
                ->where('username', $data['username'])
                ->update([
                    'fullname' => $data['fullname'],
                    'password' => $data['password'],
                    'role'     => $data['role'],
                ]);
            return;
        }

        $this->db->table('users')->insert($data);
    }
}
