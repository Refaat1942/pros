<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * عرض صفحة تسجيل الدخول الخاصة بالداشبورد المطلوب.
     */
    public function showLogin(string $dashboard): View
    {
        $dashboardConfig = config("dashboards.{$dashboard}");

        return view('auth.login', [
            'dashboard'       => $dashboard,
            'dashboardConfig' => $dashboardConfig,
        ]);
    }

    /**
     * معالجة طلب تسجيل الدخول مع التحقق أن دور المستخدم يطابق الداشبورد.
     */
    public function login(LoginRequest $request, string $dashboard): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, false)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'هذا الحساب معطّل — تواصل مع الإدارة.']);
        }

        $userSlug = $user->role?->slug;

        if ($userSlug !== $dashboard) {
            Auth::logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'هذا الحساب غير مصرّح له بالدخول لهذه اللوحة..']);
        }

        $request->session()->regenerate();

        User::where('id', Auth::id())->update(['last_login_at' => now()]);

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        AuditService::log(
            action:      'login',
            description: "تسجيل دخول ناجح — لوحة {$dashboard}",
            tag:         'auth',
            after:       ['email' => $authUser->email, 'role' => $authUser->role?->slug],
        );

        return redirect()->route("{$dashboard}.dashboard");
    }

    /**
     * تسجيل الخروج ومسح الجلسة.
     */
    public function logout(Request $request): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user) {
            AuditService::log(
                action:      'logout',
                description: 'تسجيل خروج',
                tag:         'auth',
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
