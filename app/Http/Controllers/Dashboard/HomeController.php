<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * بوابة الدخول — تسجيل دخول موحّد
 */
class HomeController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(): View|RedirectResponse
    {
        if (auth()->check()) {
            $slug = auth()->user()->role?->slug;

            return redirect($slug ? "/{$slug}" : '/');
        }

        return view('auth.home-login', [
            'branding' => $this->settings->branding(),
        ]);
    }
}
