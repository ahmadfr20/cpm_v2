<?php

namespace App\Controllers;

class PanduanController extends BaseController
{
    public function index()
    {
        $data = [
            'title'   => 'Panduan Aplikasi',
            // Gunakan link PDF default (bisa diarahkan ke GDrive, atau file lokal di public/assets)
            // Contoh URL Google Drive embed: 'https://drive.google.com/file/d/ID_FILE/preview'
            // Contoh URL lokal: base_url('assets/pdf/panduan.pdf')
            'pdf_url' => 'https://drive.google.com/file/d/1u5L5QuwiqcdTt_D7wAa-hQLotNsHtAuD/preview' 
        ];

        return view('panduan/index', $data);
    }
}
