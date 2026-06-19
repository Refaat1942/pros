<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يتحقق أن المستخدم المصادَق ينتمي فعلاً إلى الـ Dashboard الذي يحاول الوصول إليه.
 *
 * المنطق: المقطع الأول من الـ URL يجب أن يطابق users.role->slug.
 * مثال: /admin/* يتطلب role->slug === 'admin'
 */
class DashboardGuardMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $requiredPrefix = $request->segment(1);
        $userSlug       = $user->role?->slug;

        if ($requiredPrefix !== $userSlug) {
            abort(403, 'ليس لديك صلاحية الوصول إلى هذه اللوحة.');
        }

        return $next($request);
    }
}
