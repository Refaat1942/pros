<?php

namespace App\Logging;

use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramExceptionFormatter;
use Monolog\Logger;

/**
 * مصنع قناة Telegram المخصّصة لـ Monolog.
 *
 * مرتبط عبر config/logging.php:
 *   'driver' => 'custom', 'via' => TelegramLogChannel::class
 */
class TelegramLogChannel
{
    public function __invoke(array $config): Logger
    {
        $level = Logger::toMonologLevel($config['level'] ?? 'error');

        $handler = new TelegramHandler(
            app(TelegramClient::class),
            app(TelegramExceptionFormatter::class),
            $level,
        );

        return new Logger('telegram', [$handler]);
    }
}
