<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * يبدأ نسخة اليوم الاحتياطية تلقائياً في الخلفية من أول طلب في اليوم —
 * بدون Task Scheduler أو cron. فحص ملف صغير فقط في كل طلب.
 */
class DailyBackupTrigger
{
    public const STATE_FILE = 'backups/.daily-state.json';

    /** @var (callable(string): bool)|null للاختبارات — يستبدل تشغيل العملية الخلفية */
    public static $launcher = null;

    public function maybeRun(): void
    {
        if (! config('backup.auto_daily') || $this->inConsole()) {
            return;
        }

        $today = ClinicTime::todayDateString();
        $state = self::readState();

        if (($state['last_success_day'] ?? null) === $today) {
            return;
        }

        $lastAttempt = (int) ($state['last_attempt_at'] ?? 0);
        if (($state['last_attempt_day'] ?? null) === $today
            && time() - $lastAttempt < max(5, (int) config('backup.retry_minutes')) * 60) {
            return;
        }

        // قفل ملف — طلبان في نفس اللحظة لا يبدآن نسختين.
        $lockPath = storage_path('backups/.daily.lock');
        self::ensureDir(dirname($lockPath));
        $lock = @fopen($lockPath, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }

        try {
            $state = self::readState();
            if (($state['last_success_day'] ?? null) === $today) {
                return;
            }
            if (($state['last_attempt_day'] ?? null) === $today
                && time() - (int) ($state['last_attempt_at'] ?? 0) < max(5, (int) config('backup.retry_minutes')) * 60) {
                return;
            }

            self::writeState(array_merge($state, [
                'last_attempt_day' => $today,
                'last_attempt_at' => time(),
            ]));

            $this->launch();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** أوامر artisan والجدولة لا تُطلق نسخة — الطلبات من المتصفح فقط. */
    protected function inConsole(): bool
    {
        return app()->runningInConsole();
    }

    public static function markSuccess(array $files): void
    {
        self::writeState(array_merge(self::readState(), [
            'last_success_day' => ClinicTime::todayDateString(),
            'last_success_at' => ClinicTime::now()->format('Y-m-d H:i:s'),
            'files' => array_values(array_map('basename', $files)),
        ]));
    }

    /** @return array<string, mixed> */
    public static function readState(): array
    {
        $path = storage_path(self::STATE_FILE);
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private static function writeState(array $state): void
    {
        $path = storage_path(self::STATE_FILE);
        self::ensureDir(dirname($path));
        @file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    private function launch(): void
    {
        $artisan = base_path('artisan');
        $log = storage_path('logs/backup.log');

        if (is_callable(self::$launcher)) {
            (self::$launcher)($artisan);

            return;
        }

        $php = self::phpCli();
        if ($php === null) {
            Log::warning('Daily backup: php CLI not found — set BACKUP_PHP_CLI in .env');

            return;
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // start /B: عملية منفصلة لا تنتظرها صفحة الموظف.
                $cmd = 'start "" /B "'.$php.'" "'.$artisan.'" prosthetics:backup --auto >> "'.$log.'" 2>&1';
                pclose(popen('cmd /c '.$cmd, 'r'));
            } else {
                exec(escapeshellarg($php).' '.escapeshellarg($artisan).' prosthetics:backup --auto >> '
                    .escapeshellarg($log).' 2>&1 &');
            }
        } catch (\Throwable $e) {
            Log::warning('Daily backup could not start: '.$e->getMessage());
        }
    }

    /** مسار php CLI — من الإعداد، أو بجوار php.ini (Laragon)، أو PHP_BINDIR (Linux). */
    public static function phpCli(): ?string
    {
        $configured = trim((string) config('backup.php_cli'));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $exe = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $candidates = [];

        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }
        if ($ini = php_ini_loaded_file()) {
            $candidates[] = dirname($ini).DIRECTORY_SEPARATOR.$exe;
        }
        $candidates[] = PHP_BINDIR.DIRECTORY_SEPARATOR.$exe;
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';

        foreach ($candidates as $path) {
            if (is_file($path) && ! str_contains(strtolower(basename($path)), 'httpd')) {
                return $path;
            }
        }

        return null;
    }
}
