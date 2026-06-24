<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Notifications\DeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly DeviceService $deviceService)
    {
    }

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
     * معالجة طلب تسجيل الدخول — الدور الأساسي أو صلاحية الوصول للوحة عبر المصفوفة.
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

        if ($userSlug !== $dashboard && ! $user->canAccessDashboard($dashboard)) {
            Auth::logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'هذا الحساب غير مصرّح له بالدخول لهذه اللوحة..']);
        }

        $request->session()->regenerate();

        User::where('id', Auth::id())->update(['last_login_at' => now()]);

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // استلام بيانات الجهاز (device_id, device_type) وتسجيلها لإشعارات FCM.
        $this->deviceService->register(
            $authUser,
            $request->input('device_id'),
            $request->input('device_type'),
        );

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
