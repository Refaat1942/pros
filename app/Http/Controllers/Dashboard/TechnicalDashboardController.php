<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TechnicalDashboardController extends Controller
{
    public function index(): View
    {
        return view('technical.index');
    }
}
