<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\ShowsDashboardPage;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    use ShowsDashboardPage;

    protected function dashboardKey(): string
    {
        return 'admin';
    }
}
