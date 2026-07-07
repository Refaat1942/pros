<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Services\Dashboard\DashboardPageDataService;
use Illuminate\View\View;

trait ShowsDashboardPage
{
    abstract protected function dashboardKey(): string;

    public function show(string $page): View
    {
        $key = $this->dashboardKey();
        $pages = config("dashboards.{$key}.pages", []);

        abort_unless(isset($pages[$page]), 404);

        $user = auth()->user();
        abort_unless($user && $user->canViewDashboardPage($key, $page), 403, 'ليس لديك صلاحية الوصول إلى هذه الصفحة.');

        $pageData = app(DashboardPageDataService::class)->resolve($key, $page);

        return view('dashboard.show', array_merge([
            'dashboardKey' => $key,
            'activePage' => $page,
            'pageTitle' => $pages[$page]['title'],
        ], $pageData));
    }
}
