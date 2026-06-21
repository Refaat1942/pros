<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * يبني رسالة Telegram منسّقة وأنيقة (HTML) من الاستثناء بكل تفاصيله:
 * النوع، الرسالة، الملف والسطر، الطلب (URL/Method)، المستخدم، البيئة، و Stack trace.
 */
class TelegramExceptionFormatter
{
    /** عدد أسطر الـ stack trace المُرسَلة (تكفي لتحديد المصدر دون إغراق). */
    private const TRACE_LINES = 12;

    /**
     * بناء الرسالة الكاملة للتلجرام.
     */
    public function format(Throwable $e, array $extra = []): string
    {
        $appName = config('app.name', 'App');
        $env = strtoupper((string) config('app.env', 'production'));
        $relativeFile = $this->relativePath($e->getFile());

        $lines = [];
        $lines[] = '🚨 <b>'.$this->e($appName).' — تنبيه استثناء</b>';
        $lines[] = '<code>━━━━━━━━━━━━━━━━━━━━</code>';
        $lines[] = '🏷️ <b>النوع:</b> <code>'.$this->e(class_basename($e)).'</code>';
        $lines[] = '💬 <b>الرسالة:</b> '.$this->e($this->truncate($e->getMessage(), 600));
        $lines[] = '📁 <b>الموضع:</b> <code>'.$this->e($relativeFile).':'.$e->getLine().'</code>';

        if ($e->getCode()) {
            $lines[] = '🔢 <b>كود الخطأ:</b> <code>'.$this->e((string) $e->getCode()).'</code>';
        }

        foreach ($this->requestContext() as $label => $value) {
            $lines[] = $label.' '.$value;
        }

        $lines[] = '🖥️ <b>البيئة:</b> <code>'.$this->e($env).'</code>';
        $lines[] = '🕒 <b>الوقت:</b> <code>'.now()->format('Y-m-d H:i:s').'</code>';

        if ($previous = $e->getPrevious()) {
            $lines[] = '↩️ <b>السبب الأصلي:</b> <code>'.$this->e(class_basename($previous)).'</code> — '
                .$this->e($this->truncate($previous->getMessage(), 200));
        }

        foreach ($extra as $key => $value) {
            if (is_scalar($value)) {
                $lines[] = '• <b>'.$this->e((string) $key).':</b> '.$this->e((string) $value);
            }
        }

        $lines[] = '<code>━━━━━━━━━━━━━━━━━━━━</code>';
        $lines[] = '<b>Stack trace:</b>';
        $lines[] = '<pre>'.$this->e($this->traceSnippet($e)).'</pre>';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private function requestContext(): array
    {
        if (app()->runningInConsole()) {
            return ['⌨️ <b>المصدر:</b>' => '<code>CLI / Console</code>'];
        }

        $context = [];
        $request = request();

        if ($request) {
            $method = $this->e($request->method());
            $url = $this->e($this->truncate($request->fullUrl(), 300));
            $context['🌐 <b>الطلب:</b>'] = '<code>'.$method.'</code> '.$url;

            if ($ip = $request->ip()) {
                $context['📍 <b>IP:</b>'] = '<code>'.$this->e($ip).'</code>';
            }
        }

        if (Auth::check()) {
            $user = Auth::user();
            $name = $this->e((string) ($user->name ?? 'unknown'));
            $context['👤 <b>المستخدم:</b>'] = '#'.$user->getAuthIdentifier().' ('.$name.')';
        } else {
            $context['👤 <b>المستخدم:</b>'] = '<i>زائر / غير مسجّل</i>';
        }

        return $context;
    }

    private function traceSnippet(Throwable $e): string
    {
        $trace = explode("\n", $e->getTraceAsString());
        $trace = array_slice($trace, 0, self::TRACE_LINES);

        $trace = array_map(fn ($line) => $this->relativePath($line), $trace);

        $snippet = implode("\n", $trace);

        if (count(explode("\n", $e->getTraceAsString())) > self::TRACE_LINES) {
            $snippet .= "\n…";
        }

        return $snippet;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function truncate(string $value, int $max): string
    {
        $value = trim($value);

        if ($value === '') {
            return '—';
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max).'…' : $value;
    }

    /**
     * تهريب الأحرف الخاصة بـ HTML الخاص بـ Telegram (& < >).
     */
    private function e(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
