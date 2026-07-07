<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Web route files (middleware: web).
     *
     * @var list<string>
     */
    protected array $webRouteFiles = [
        'auth',
        'home',
        'reception',
        'doctor',
        'spec',
        'adjustments',
        'operations',
        'technical',
        'admin',
        'fallback',
    ];

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // تسجيل الدخول: يحدّ من محاولات تخمين كلمة المرور (لكل اسم مستخدم + IP).
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('username')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // نقاط النهاية العامة (بدون مصادقة): تمنع تعداد رموز QR / معرّفات التتبّع.
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        $this->routes(function () {
            Route::middleware('web')->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        });
    }
}
