<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * بوابة الدخول — 7 لوحات التحكم
 */
class HomeController extends Controller
{
    public function index(): View
    {
        return view('index');
    }
}
