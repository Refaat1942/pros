<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ReceptionDashboardController extends Controller
{
    public function index(): View
    {
        return view('reception.index');
    }
}
