<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * يعيد التوجيه إلى صفحة login الخاصة بالداشبورد الذي حاول الوصول إليه.
     *
     * مثال: /reception/queue → /reception/login
     *        /admin/overview  → /admin/login
     *        /unknown         → / (الصفحة الرئيسية)
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        $segment = $request->segment(1);

        if ($segment && in_array($segment, Role::ALL_SLUGS, true)) {
            return url("/{$segment}/login");
        }

        return url('/');
    }
}
