<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * يصلح Permission denied على storage/framework/views بعد تشغيل artisan كـ root.
 */
class FixStoragePermissionsCommand extends Command
{
    protected $signature = 'prosthetics:fix-storage-permissions {--user=www-data : مستخدم خادم الويب}';

    protected $description = 'إنشاء مجلدات storage وضبط صلاحيات www-data (VPS / offline LAN)';

    public function handle(): int
    {
        $webUser = (string) $this->option('user');

        foreach ($this->requiredDirs() as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
                $this->line("أُنشئ: {$dir}");
            }
        }

        if (PHP_OS_FAMILY !== 'Linux' || posix_getuid() !== 0) {
            $this->warn('لضبط الصلاحيات على Linux شغّل كـ root:');
            $this->line("  chown -R {$webUser}:{$webUser} storage bootstrap/cache");
            $this->line('  chmod -R 775 storage bootstrap/cache');
            $this->line("  sudo -u {$webUser} php artisan view:clear");

            return self::SUCCESS;
        }

        if (! $this->posixUserExists($webUser)) {
            $this->error("المستخدم «{$webUser}» غير موجود على هذا السيرفر.");

            return self::FAILURE;
        }

        $commands = [
            "chown -R {$webUser}:{$webUser} storage bootstrap/cache",
            'chmod -R 775 storage bootstrap/cache',
        ];

        foreach ($commands as $command) {
            exec($command, $output, $exitCode);
            if ($exitCode !== 0) {
                $this->error("فشل: {$command}");

                return self::FAILURE;
            }
        }

        $this->info("✅ صلاحيات storage و bootstrap/cache مضبوطة لـ {$webUser}.");
        $this->line("إن ظهر Permission denied للـ views، نفّذ: sudo -u {$webUser} php artisan view:clear");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function requiredDirs(): array
    {
        return [
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'storage/app/public',
            'storage/backups',
            'bootstrap/cache',
        ];
    }

    private function posixUserExists(string $user): bool
    {
        return function_exists('posix_getpwnam') && posix_getpwnam($user) !== false;
    }
}
