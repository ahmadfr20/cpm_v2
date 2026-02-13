<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/login');
        }

        if (empty($arguments)) {
            return;
        }

        $role = session()->get('role');

        // ADMIN bebas akses
        if ($role === 'ADMIN') {
            return;
        }

        // arguments format:
        // - ['PPIC'] -> hanya role
        // - ['OPERATOR','DC'] -> role + process_code allowed
        $requiredRole    = $arguments[0] ?? null;
        $allowedSections = array_slice($arguments, 1); // sekarang ini process_code list

        // cek role
        if ($requiredRole && $role !== $requiredRole) {
            return redirect()
                ->to('/dashboard')
                ->with('error', "Anda tidak punya akses halaman ini (role tidak sesuai).");
        }

        // cek multi-process privilege
        if (!empty($allowedSections)) {
            // session process_codes = array, contoh ['DC','MC']
            $userProcessCodes = session()->get('process_codes');

            // fallback kalau session lama hanya punya 'section'
            if (!is_array($userProcessCodes)) {
                $single = session()->get('section');
                $userProcessCodes = $single ? [$single] : [];
            }

            // intersection
            $ok = false;
            foreach ($allowedSections as $sec) {
                if (in_array($sec, $userProcessCodes, true)) {
                    $ok = true;
                    break;
                }
            }

            if (!$ok) {
                $allowed = implode(', ', $allowedSections);
                $mine = !empty($userProcessCodes) ? implode(', ', $userProcessCodes) : '-';
                return redirect()
                    ->to('/dashboard')
                    ->with('error', "Anda tidak punya akses halaman ini. Privilege anda: {$mine}. Diizinkan: {$allowed}.");
            }
        }

        return;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nothing
    }
}
