<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SpecDashboardController extends Controller
{
    public function index(): View
    {
        return view('spec.index');
    }
}
