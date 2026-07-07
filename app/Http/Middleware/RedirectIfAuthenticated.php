<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * يعيد توجيه المستخدم المصادَق إلى لوحة دوره بدلاً من صفحة مبدئية ثابتة.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $slug = Auth::user()->role?->slug;

                return redirect($slug ? "/{$slug}" : '/');
            }
        }

        return $next($request);
    }
}
