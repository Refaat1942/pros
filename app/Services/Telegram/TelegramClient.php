<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;

/**
 * عميل HTTP بسيط للتواصل مع Telegram Bot API.
 *
 * لا يرمي استثناءات للأعلى أبداً — أي فشل في الإرسال يُرجِع false
 * حتى لا يتسبب نظام الإشعارات نفسه في كسر التطبيق أو الدخول في حلقة أخطاء.
 */
class TelegramClient
{
    /** أقصى طول رسالة في Telegram = 4096 حرف؛ نترك هامش أمان. */
    private const MAX_MESSAGE_LENGTH = 3900;

    public function __construct(
        private ?string $token = null,
        private ?string $chatId = null,
    ) {
        $this->token ??= config('services.telegram.token');
        $this->chatId ??= config('services.telegram.chat_id');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->token) && ! empty($this->chatId);
    }

    /**
     * إرسال رسالة نصية (HTML). تُقسَّم تلقائياً إن تجاوزت حد التلجرام.
     */
    public function sendMessage(string $text, ?string $chatId = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $chatId ??= $this->chatId;
        $timeout = (int) config('services.telegram.timeout', 8);

        try {
            foreach ($this->chunk($text) as $part) {
                $response = Http::timeout($timeout)
                    ->asForm()
                    ->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $part,
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true,
                    ]);

                if ($response->failed()) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * تقسيم النص الطويل لأجزاء مع محاولة القطع عند نهاية سطر.
     *
     * @return array<int, string>
     */
    private function chunk(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_MESSAGE_LENGTH) {
            return [$text];
        }

        $parts = [];
        $remaining = $text;

        while (mb_strlen($remaining) > self::MAX_MESSAGE_LENGTH) {
            $slice = mb_substr($remaining, 0, self::MAX_MESSAGE_LENGTH);
            $breakAt = mb_strrpos($slice, "\n");
            $cut = ($breakAt !== false && $breakAt > 0) ? $breakAt : self::MAX_MESSAGE_LENGTH;

            $parts[] = mb_substr($remaining, 0, $cut);
            $remaining = mb_substr($remaining, $cut);
        }

        if ($remaining !== '') {
            $parts[] = $remaining;
        }

        return $parts;
    }
}
