<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** يعرض معاينة الوثائق مع التقاط أخطاء الرندر (خارج try في الكنترولر التقليدي). */
final class DocumentPreviewRenderer
{
    public function html(View $view, string $documentKey): Response
    {
        try {
            return response($view->render(), 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        } catch (\Throwable $e) {
            Log::error('document_preview_render_failed', [
                'document' => $documentKey,
                'message' => $e->getMessage(),
                'view' => $view->name(),
            ]);
            report($e);

            return $this->errorResponse($documentKey, $e);
        }
    }

    public function errorResponse(string $documentKey, ?\Throwable $e = null): Response
    {
        try {
            $html = view('admin.print.document-preview-error', [
                'document' => $documentKey,
                'errorMessage' => $e?->getMessage(),
            ])->render();

            return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
        } catch (\Throwable $inner) {
            Log::error('document_preview_error_page_failed', [
                'document' => $documentKey,
                'message' => $inner->getMessage(),
            ]);

            return response(
                '<!DOCTYPE html><html lang="ar" dir="rtl"><body style="font-family:Tahoma;padding:24px;">'
                .'<h1>تعذّر عرض معاينة الوثيقة</h1>'
                .'<p>الوثيقة: <code>'.htmlspecialchars($documentKey, ENT_QUOTES, 'UTF-8').'</code></p>'
                .'<p>نفّذ على السيرفر: <code>bash deploy.sh</code> ثم <code>php artisan migrate --force</code></p>'
                .'<p><a href="/admin/documents-hub">مركز الوثائق</a></p></body></html>',
                200
            )->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }
}
