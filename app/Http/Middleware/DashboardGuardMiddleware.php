<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * يتحقق أن المستخدم المصادَق يملك صلاحية الوصول إلى لوحة التحكم المطلوبة.
 *
 * - الدور الأساسي: المقطع الأول من الـ URL يطابق users.role->slug.
 * - عبر الصلاحيات: يمكن للإدارة (أو أي دور) فتح لوحة أخرى إذا مُنحت صلاحيات عرض لها.
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
                ->withErrors(['username' => 'تم تعطيل حسابك — تواصل مع الإدارة.']);
        }

        $requiredPrefix = $request->segment(1);
        $userSlug = $user->role?->slug;

        if ($requiredPrefix !== $userSlug && ! $user->canAccessDashboard($requiredPrefix)) {
            abort(403, 'ليس لديك صلاحية الوصول إلى هذه اللوحة.');
        }

        return $next($request);
    }
}
