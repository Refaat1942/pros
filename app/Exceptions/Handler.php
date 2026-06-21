<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // يخزّن في storage/logs/telegram.log ويرسل إشعاراً منسّقاً للبوت.
            // محاط بـ try/catch حتى لا يتسبب نظام الإشعار نفسه في خطأ ثانٍ.
            try {
                Log::channel('telegram')->error($e->getMessage(), ['exception' => $e]);
            } catch (Throwable $loggingError) {
                // تجاهل بصمت — لا نريد كسر سلسلة معالجة الأخطاء.
            }
        });
    }
}
