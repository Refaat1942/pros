<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdjustmentsDashboardController extends Controller
{
    public function index(): View
    {
        return view('adjustments.index');
    }
}
