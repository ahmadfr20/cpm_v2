<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'username' => 'admin',
                'fullname' => 'System Administrator',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role'     => 'ADMIN',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'operator',
                'fullname' => 'Operator Produksi',
                'password' => password_hash('operator123', PASSWORD_DEFAULT),
                'role'     => 'OPERATOR',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'ppic',
                'fullname' => 'PPIC',
                'password' => password_hash('ppic123', PASSWORD_DEFAULT),
                'role'     => 'PPIC',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];

        // Insert batch
        $this->db->table('users')->insertBatch($data);
    }
}
