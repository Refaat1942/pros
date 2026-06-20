<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isActive()) {
            $loginDashboard = $user->role?->slug ?? $request->segment(1) ?? 'home';

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->to("/{$loginDashboard}/login")
                ->withErrors(['email' => 'تم تعطيل حسابك — تواصل مع الإدارة.']);
        }

        $requiredPrefix = $request->segment(1);
        $userSlug       = $user->role?->slug;

        if ($requiredPrefix !== $userSlug) {
            abort(403, 'ليس لديك صلاحية الوصول إلى هذه اللوحة.');
        }

        return $next($request);
    }
}
