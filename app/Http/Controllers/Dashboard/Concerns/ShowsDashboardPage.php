<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use Illuminate\View\View;

trait ShowsDashboardPage
{
    abstract protected function dashboardKey(): string;

    public function show(string $page): View
    {
        $key = $this->dashboardKey();
        $pages = config("dashboards.{$key}.pages", []);

        abort_unless(isset($pages[$page]), 404);

        return view('dashboard.show', [
            'dashboardKey' => $key,
            'activePage' => $page,
            'pageTitle' => $pages[$page]['title'],
        ]);
    }
}
