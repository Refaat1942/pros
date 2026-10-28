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
    /**
     * dashboard.page:admin,stock-kits — أو بدائل: dashboard.page:admin,stock-kits,technical,stock-kits
     * (يكفي أن تكون أي صفحة منها مسموحة — نفس الواجهة مشتركة بين أكثر من قسم).
     */
    public function handle(Request $request, Closure $next, string ...$pairs): Response
    {
        $user = auth()->user();
        $allowed = false;

        if ($user) {
            for ($i = 0; $i + 1 < count($pairs); $i += 2) {
                if ($user->canViewDashboardPage($pairs[$i], $pairs[$i + 1])) {
                    $allowed = true;
                    break;
                }
            }
        }

        abort_unless($allowed, 403, 'ليس لديك صلاحية الوصول إلى هذه الصفحة.');

        return $next($request);
    }
}
