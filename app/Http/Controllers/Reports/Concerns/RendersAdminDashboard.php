<?php

namespace App\Http\Controllers\Reports\Concerns;

use Illuminate\View\View;

trait RendersAdminDashboard
{
    protected function adminPage(string $page, array $data = []): View
    {
        $pages = config('dashboards.admin.pages', []);

        abort_unless(isset($pages[$page]), 404);

        return view('dashboard.show', array_merge([
            'dashboardKey' => 'admin',
            'activePage'   => $page,
            'pageTitle'    => $pages[$page]['title'],
        ], $data));
    }
}
