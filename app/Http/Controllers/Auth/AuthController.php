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
    public function __construct(private readonly DeviceService $deviceService) {}

    /**
     * عرض صفحة تسجيل الدخول الخاصة بالداشبورد المطلوب.
     */
    public function showLogin(string $dashboard): View
    {
        $dashboardConfig = config("dashboards.{$dashboard}");

        return view('auth.login', [
            'dashboard' => $dashboard,
            'dashboardConfig' => $dashboardConfig,
        ]);
    }

    /**
     * تسجيل دخول موحّد — يوجّه المستخدم إلى لوحة دوره تلقائياً.
     */
    public function loginUnified(LoginRequest $request): RedirectResponse
    {
        return $this->completeLogin($request, null);
    }

    /**
     * @deprecated استخدم /login — يُحوَّل للصفحة الرئيسية
     */
    public function login(LoginRequest $request, string $dashboard): RedirectResponse
    {
        return $this->completeLogin($request, $dashboard);
    }

    private function completeLogin(LoginRequest $request, ?string $requestedDashboard): RedirectResponse
    {
        $credentials = $request->only('username', 'password');

        if (! Auth::attempt($credentials, false)) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'اسم المستخدم أو كلمة المرور غير صحيحة.']);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'هذا الحساب معطّل — تواصل مع الإدارة.']);
        }

        $dashboard = $user->role?->slug;

        if (! $dashboard || ! config("dashboards.{$dashboard}")) {
            Auth::logout();

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'لا توجد لوحة مرتبطة بهذا الحساب.']);
        }

        if ($requestedDashboard !== null
            && $dashboard !== $requestedDashboard
            && ! $user->canAccessDashboard($requestedDashboard)) {
            Auth::logout();

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'هذا الحساب غير مصرّح له بالدخول لهذه اللوحة.']);
        }

        if ($requestedDashboard !== null && $user->canAccessDashboard($requestedDashboard)) {
            $dashboard = $requestedDashboard;
        }

        $request->session()->regenerate();

        User::where('id', Auth::id())->update(['last_login_at' => now()]);

        /** @var User $authUser */
        $authUser = Auth::user();

        $this->deviceService->register(
            $authUser,
            $request->input('device_id'),
            $request->input('device_type'),
        );

        AuditService::log(
            action: 'login',
            description: "تسجيل دخول ناجح — لوحة {$dashboard}",
            tag: 'auth',
            after: ['username' => $authUser->username, 'role' => $authUser->role?->slug],
        );

        return redirect()->route("{$dashboard}.dashboard");
    }

    /**
     * تسجيل الخروج ومسح الجلسة.
     */
    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            AuditService::log(
                action: 'logout',
                description: 'تسجيل خروج',
                tag: 'auth',
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
