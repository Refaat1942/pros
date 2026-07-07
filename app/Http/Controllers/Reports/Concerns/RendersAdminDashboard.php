<?php

namespace App\Http\Controllers\Reports\Concerns;

use Illuminate\View\View;

trait RendersAdminDashboard
{
    protected function adminPage(string $page, array $data = []): View
    {
        $pages = config('dashboards.admin.pages', []);

        abort_unless(isset($pages[$page]), 404);

        $user = auth()->user();
        abort_unless($user && $user->canViewDashboardPage('admin', $page), 403, 'ليس لديك صلاحية الوصول إلى هذه الصفحة.');

        return view('dashboard.show', array_merge([
            'dashboardKey' => 'admin',
            'activePage' => $page,
            'pageTitle' => $pages[$page]['title'],
        ], $data));
    }
}
