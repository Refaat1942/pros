<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يُخزِّن بيانات السياق المطلوبة في سجل الرقابة (MAC address)
 * حتى يتمكن AuditService من قراءتها بدون تكرار قراءة الطلب من داخل Service.
 */
class AuditContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $mac = $request->header('X-Mac-Address');

        if ($mac) {
            $request->attributes->set('audit_mac', filter_var($mac, FILTER_SANITIZE_STRING));
        }

        return $next($request);
    }
}
