<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        // «Page Expired 419»: صفحة دخول قديمة في تاب، أو مستخدم آخر دخل/خرج من نفس المتصفح.
        // بدل صفحة الخطأ: نرجع لصفحة دخول جديدة برسالة واضحة (والخروج يتم في كل الأحوال).
        $this->renderable(function (HttpException $e, $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'انتهت صلاحية الصفحة — حدّث الصفحة (F5) وحاول مرة أخرى.',
                ], 419);
            }

            if ($request->routeIs('logout')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('home');
            }

            if ($request->routeIs('login.submit')) {
                $request->session()->regenerateToken();

                return redirect()->route('home')
                    ->withInput($request->only('username'))
                    ->with('error', 'انتهت صلاحية صفحة الدخول — أدخل البيانات مرة أخرى.');
            }

            return redirect()->back()->with('error', 'انتهت صلاحية الصفحة — حدّث الصفحة وأعد المحاولة.');
        });

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
