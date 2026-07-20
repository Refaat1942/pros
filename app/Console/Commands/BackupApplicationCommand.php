<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * نسخ احتياطي يومي — PostgreSQL / MySQL + ملفات الشعار المرفوعة.
 */
class BackupApplicationCommand extends Command
{
    protected $signature = 'prosthetics:backup {--keep=7 : حذف النسخ الأقدم من هذا العدد بالأيام}';

    protected $description = 'Create database (+ branding uploads) backup under storage/backups';

    public function handle(): int
    {
        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $stamp = now()->format('Y-m-d_His');
        $dbPath = "{$backupDir}/db-{$stamp}.sql.gz";

        if (! $this->dumpDatabase($dbPath)) {
            return self::FAILURE;
        }

        $this->archiveBrandingUploads("{$backupDir}/files-{$stamp}.tar.gz");
        $this->pruneOldBackups($backupDir, (int) $this->option('keep'));

        $this->info("Backup complete: {$backupDir}");

        return self::SUCCESS;
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

    private function archiveBrandingUploads(string $targetPath): void
    {
        $source = storage_path('app/public/branding');
        if (! is_dir($source)) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->warn('Skipping uploads archive on Windows (tar not used in dev).');

            return;
        }

        $process = new Process([
            'tar',
            '-czf',
            $targetPath,
            '-C',
            storage_path('app/public'),
            'branding',
        ]);
        $process->setTimeout(300);
        $process->run();

        if ($process->isSuccessful()) {
            $this->line('Uploads: '.basename($targetPath));
        } else {
            $this->warn('Uploads archive skipped: '.trim($process->getErrorOutput()));
        }
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
