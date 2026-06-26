<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * مسح ملفات السجلات — laravel.log و telegram.log وأي ملفات .log دوّارة.
 */
class PurgeApplicationLogsCommand extends Command
{
    protected $signature = 'prosthetics:purge-logs';

    protected $description = 'Clear Laravel log files including storage/logs/telegram.log';

    public function handle(): int
    {
        $logsDir = storage_path('logs');

        if (! is_dir($logsDir)) {
            $this->warn('Logs directory does not exist.');

            return self::SUCCESS;
        }

        $cleared = 0;

        foreach (File::glob($logsDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }

            if (@file_put_contents($path, '') !== false) {
                $cleared++;
                $this->line('Cleared: ' . basename($path));
            }
        }

        $this->info("Cleared {$cleared} log file(s).");

        return self::SUCCESS;
    }
}
