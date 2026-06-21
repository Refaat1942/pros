<?php

namespace App\Logging;

use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramExceptionFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Monolog handler يدفع سجلات الأخطاء إلى Telegram.
 *
 * - لو وُجد استثناء داخل context['exception'] يُنسَّق بالتفصيل الأنيق.
 * - غير ذلك يُرسَل نص السجل العادي مع المستوى.
 */
class TelegramHandler extends AbstractProcessingHandler
{
    public function __construct(
        private TelegramClient $client,
        private TelegramExceptionFormatter $exceptionFormatter,
        int|string|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        // احترام مفتاح التعطيل أو غياب الإعدادات دون كسر سلسلة السجلات.
        if (! config('services.telegram.notify_errors', true) || ! $this->client->isConfigured()) {
            return;
        }

        $exception = $record->context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            $text = $this->exceptionFormatter->format($exception, $this->scalarContext($record));
        } else {
            $text = $this->plainMessage($record);
        }

        $this->client->sendMessage($text);
    }

    private function plainMessage(LogRecord $record): string
    {
        $appName = $this->escape((string) config('app.name', 'App'));
        $level = $this->escape($record->level->getName());
        $message = $this->escape($record->message);
        $time = now()->format('Y-m-d H:i:s');

        $lines = [
            '⚠️ <b>'.$appName.' — '.$level.'</b>',
            '<code>━━━━━━━━━━━━━━━━━━━━</code>',
            $message,
            '🕒 <code>'.$time.'</code>',
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array<string, scalar>
     */
    private function scalarContext(LogRecord $record): array
    {
        $context = [];

        foreach ($record->context as $key => $value) {
            if ($key !== 'exception' && is_scalar($value)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
