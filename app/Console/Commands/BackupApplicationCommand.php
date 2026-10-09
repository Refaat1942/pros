<?php

namespace App\Console\Commands;

use App\Support\DailyBackupTrigger;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * نسخ احتياطي يومي للتطبيق كله — قاعدة البيانات (PostgreSQL / MySQL) + كل الملفات المرفوعة
 * (storage/app: خطابات الموافقة، المستندات، الشعار…) + إعدادات .env.
 *
 * يعمل تلقائياً مرة يومياً (DailyBackupTrigger) ومن جدولة 02:00 — ويمكن تشغيله يدوياً.
 */
class BackupApplicationCommand extends Command
{
    protected $signature = 'prosthetics:backup
                            {--keep= : حذف النسخ الأقدم من هذا العدد بالأيام (افتراضياً من config/backup.php)}
                            {--auto : تشغيل تلقائي من النسخة اليومية}';

    protected $description = 'Back up the database, all uploaded files and .env under storage/backups';

    public function handle(): int
    {
        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $stamp = now()->format('Y-m-d_His');
        $this->line('['.now()->format('Y-m-d H:i:s').'] Backup started'.($this->option('auto') ? ' (automatic daily)' : ''));

        $dbPath = "{$backupDir}/db-{$stamp}.sql.gz";
        $dbOk = $this->dumpDatabase($dbPath);

        // الملفات تُنسخ حتى لو فشلت قاعدة البيانات — لا نخسر الاثنين معاً.
        $filesPath = $this->archiveFiles($backupDir, $stamp);

        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : (int) config('backup.keep_days', 7);
        $this->pruneOldBackups($backupDir, $keep);

        if (! $dbOk) {
            return self::FAILURE;
        }

        $made = array_values(array_filter([$dbPath, $filesPath]));
        $this->mirror($made, $keep);
        DailyBackupTrigger::markSuccess($made);

        $this->info("Backup complete: {$backupDir}");

        return self::SUCCESS;
    }

    /**
     * كل الملفات المرفوعة + .env في zip واحد (يعمل على Windows و Linux).
     */
    private function archiveFiles(string $backupDir, string $stamp): ?string
    {
        $source = storage_path('app');

        if (class_exists(\ZipArchive::class)) {
            $target = "{$backupDir}/files-{$stamp}.zip";
            $zip = new \ZipArchive;
            if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                $this->warn('Files archive skipped: cannot create '.basename($target));

                return null;
            }

            $count = 0;
            if (is_dir($source)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                );
                foreach ($iterator as $file) {
                    if (! $file->isFile()) {
                        continue;
                    }
                    $relative = 'storage/app/'.str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                    if (basename($relative) === '.gitignore') {
                        continue;
                    }
                    $zip->addFile($file->getPathname(), $relative);
                    $count++;
                }
            }

            if (is_file(base_path('.env'))) {
                $zip->addFile(base_path('.env'), '.env');
                $count++;
            }

            $zip->close();
            $this->line("Files: {$count} → ".basename($target));

            return is_file($target) ? $target : null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->warn('Files archive skipped: enable the PHP zip extension (php.ini → extension=zip).');

            return null;
        }

        $target = "{$backupDir}/files-{$stamp}.tar.gz";
        $process = new Process(['tar', '-czf', $target, '-C', base_path(), 'storage/app', '.env']);
        $process->setTimeout(900);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->warn('Files archive skipped: '.trim($process->getErrorOutput()));

            return null;
        }

        $this->line('Files: '.basename($target));

        return $target;
    }

    /**
     * نسخة ثانية تلقائية على قرص آخر/فلاشة إن حُدّد BACKUP_MIRROR_PATH.
     *
     * @param  list<string>  $files
     */
    private function mirror(array $files, int $keep): void
    {
        $mirror = trim((string) config('backup.mirror_path'));
        if ($mirror === '') {
            return;
        }

        if (! is_dir($mirror) && ! @mkdir($mirror, 0755, true)) {
            $this->warn("Mirror skipped: {$mirror} is not available (disk/USB not connected?).");

            return;
        }

        foreach ($files as $file) {
            if (! @copy($file, rtrim($mirror, '\\/').DIRECTORY_SEPARATOR.basename($file))) {
                $this->warn('Mirror copy failed: '.basename($file));
            }
        }

        $this->pruneOldBackups($mirror, $keep);
        $this->line("Mirror: {$mirror}");
    }

    private function dumpDatabase(string $targetPath): bool
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $driver = (string) ($config['driver'] ?? '');

        $sql = match ($driver) {
            'pgsql' => $this->dumpPostgres($config),
            'mysql' => $this->dumpMysql($config),
            default => null,
        };

        if ($sql === null) {
            $this->error('Database backup requires PostgreSQL (pg_dump) or MySQL (mysqldump). Current driver: '.$driver);

            return false;
        }

        if ($sql === false) {
            return false;
        }

        $gz = gzencode($sql, 9);
        if ($gz === false) {
            $this->error('Failed to compress database dump.');

            return false;
        }

        file_put_contents($targetPath, $gz);
        $this->line('Database: '.basename($targetPath));

        return true;
    }

    /** @param  array<string, mixed>  $config */
    private function dumpPostgres(array $config): string|false
    {
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            $this->error('Database name is not configured.');

            return false;
        }

        $cmd = [
            'pg_dump',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '5432'),
            '--username='.(string) ($config['username'] ?? ''),
            '--no-owner',
            '--no-acl',
            '--format=plain',
            $database,
        ];

        $env = [];
        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $env['PGPASSWORD'] = $password;
        }

        $dump = new Process($cmd, null, $env);
        $dump->setTimeout(600);
        $dump->run();

        if (! $dump->isSuccessful()) {
            $this->error('pg_dump failed: '.trim($dump->getErrorOutput() ?: $dump->getOutput()));

            return false;
        }

        return $dump->getOutput();
    }

    /** @param  array<string, mixed>  $config */
    private function dumpMysql(array $config): string|false
    {
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            $this->error('Database name is not configured.');

            return false;
        }

        $cmd = [
            'mysqldump',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '3306'),
            '--user='.(string) ($config['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--lock-tables=false',
            $database,
        ];

        $env = [];
        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $env['MYSQL_PWD'] = $password;
        }

        $dump = new Process($cmd, null, $env);
        $dump->setTimeout(600);
        $dump->run();

        if (! $dump->isSuccessful()) {
            $this->error('mysqldump failed: '.trim($dump->getErrorOutput() ?: $dump->getOutput()));

            return false;
        }

        return $dump->getOutput();
    }

    private function pruneOldBackups(string $dir, int $keepDays): void
    {
        if ($keepDays <= 0) {
            return;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}
