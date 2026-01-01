<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class HomeController extends BaseController
{
    public function index()
    {
        return view('dashboard/home', [
            'fullname' => session()->get('fullname')
        ]);
    }
}
