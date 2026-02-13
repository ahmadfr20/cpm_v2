<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserPpicSeeder extends Seeder
{
    public function run()
    {
        // Data user PPIC yang akan di-seed
        $data = [
            'username'   => 'ppic',
            'fullname'   => 'User PPIC',
            // bcrypt hash
            'password'   => password_hash('ppic12345', PASSWORD_BCRYPT),
            'role'       => 'PPIC',
            // created_at di DB sudah default current_timestamp()
        ];

        // Cegah duplicate seed (cek berdasarkan username)
        $exists = $this->db->table('users')
            ->where('username', $data['username'])
            ->countAllResults();

        if ($exists > 0) {
            // Update kalau sudah ada (opsional)
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
