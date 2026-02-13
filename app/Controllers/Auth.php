<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\ProductionProcessModel;

class Auth extends BaseController
{
    public function login()
    {
        // kalau sudah login, langsung ke dashboard
        if (session()->get('logged_in')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }

    public function authenticate()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        if ($username === '' || $password === '') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Username dan password wajib diisi.');
        }

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();

        if (! $user) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Username tidak ditemukan');
        }

        if (! password_verify($password, (string) ($user['password'] ?? ''))) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Password salah');
        }

        // ===== Multi-section (process) =====
        // ADMIN: all access => tidak perlu proses
        $role = (string) ($user['role'] ?? '');

        $processIds   = [];
        $processCodes = [];

        if ($role !== 'ADMIN') {
            $db = db_connect();

            // Ambil daftar process dari pivot user_processes -> production_processes
            $rows = $db->table('user_processes up')
                ->select('pp.id, pp.process_code')
                ->join('production_processes pp', 'pp.id = up.process_id', 'inner')
                ->where('up.user_id', (int) $user['id'])
                ->orderBy('pp.id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($rows as $r) {
                $pid = (int) ($r['id'] ?? 0);
                $pcode = (string) ($r['process_code'] ?? '');
                if ($pid > 0) $processIds[] = $pid;
                if ($pcode !== '') $processCodes[] = $pcode;
            }

            // kalau non-admin tapi belum punya process, tolak login (opsional tapi disarankan)
            if (empty($processCodes)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Akun belum memiliki section/process. Hubungi ADMIN.');
            }
        }

        // backward compatibility: "section" lama
        // kita set dari process_code pertama agar route filter lama tidak crash
        $sectionCompat = null;
        if (!empty($processCodes)) {
            $sectionCompat = $processCodes[0];
        } elseif ($role === 'ADMIN') {
            $sectionCompat = 'ALL';
        }

        session()->set([
            'logged_in'     => true,
            'user_id'       => (int) $user['id'],
            'username'      => (string) $user['username'],
            'fullname'      => (string) ($user['fullname'] ?? ''),
            'role'          => $role,

            // lama (compat)
            'section'       => $sectionCompat,

            // baru (multi-section)
            'process_ids'   => $processIds,     // array<int>
            'process_codes' => $processCodes,   // array<string> contoh: ['DC','MC']
        ]);

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
