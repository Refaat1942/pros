<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * كوكي الجلسة «Secure» لا يحفظه المتصفح على http — السيرفر الأوفلاين (http://IP) كان يرفض
 * كل تسجيل دخول بـ 419 لو SESSION_SECURE_COOKIE=true. على http نلغي Secure تلقائياً.
 */
class MatchSessionCookieToScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isSecure() && config('session.secure')) {
            config(['session.secure' => false]);
        }

        return $next($request);
    }
}
