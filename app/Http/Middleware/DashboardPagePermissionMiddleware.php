<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يمنع الوصول لصفحة/واجهة API إذا لم تكن صلاحية العرض ممنوحة في المصفوفة.
 */
class DashboardPagePermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $dashboard, string $page): Response
    {
        $user = auth()->user();

        abort_unless(
            $user && $user->canViewDashboardPage($dashboard, $page),
            403,
            'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
        );

        return $next($request);
    }
}
