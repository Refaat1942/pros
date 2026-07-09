<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ينهي الجلسة بعد فترة خمول (افتراضياً 5 دقائق) ويُعيد المستخدم لصفحة الدخول.
 */
class EnforceIdleSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = max(1, (int) config('session.idle_timeout', 5));
        $timeoutSeconds = $timeoutMinutes * 60;
        $lastActivity = $request->session()->get('last_activity');

        if (is_int($lastActivity) && (time() - $lastActivity) >= $timeoutSeconds) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('home')
                ->with(
                    'error',
                    "انتهت الجلسة بعد {$timeoutMinutes} دقائق بدون نشاط — سجّل الدخول مرة أخرى."
                );
        }

        $request->session()->put('last_activity', time());

        return $next($request);
    }
}
