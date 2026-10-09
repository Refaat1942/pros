<?php

namespace App\Http\Middleware;

use App\Support\DailyBackupTrigger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد إرسال الصفحة للموظف: يتأكد أن نسخة اليوم الاحتياطية بدأت (تعمل في الخلفية).
 */
class TriggerDailyBackup
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            app(DailyBackupTrigger::class)->maybeRun();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
