<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * بوابة الدخول — تسجيل دخول موحّد
 */
class HomeController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(): Response|RedirectResponse
    {
        if (auth()->check()) {
            $user = auth()->user();
            $slug = $user->dashboardSlug();

            if ($slug && $slug !== 'home' && $user->canAccessDashboard($slug)) {
                return redirect("/{$slug}");
            }

            auth()->logout();

            return redirect('/')->withErrors([
                'username' => 'لا توجد صلاحيات مفعّلة لهذا الحساب — تواصل مع الإدارة.',
            ]);
        }

        // صفحة الدخول لا تُحفظ في كاش المتصفح — نسخة قديمة منها تحمل توكن منتهي (419).
        return response()
            ->view('auth.home-login', ['branding' => $this->settings->branding()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
