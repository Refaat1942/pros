<?php

namespace Tests\Feature\Console;

use App\Support\DailyBackupTrigger;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupApplicationCommandTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // مخزن مؤقت — لا نلمس storage الحقيقي للمشروع.
        $this->storage = sys_get_temp_dir().'/pros-backup-test-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app/public/branding');
        File::ensureDirectoryExists($this->storage.'/app/approval-letters');
        File::ensureDirectoryExists($this->storage.'/logs');
        file_put_contents($this->storage.'/app/public/branding/logo.png', 'logo');
        file_put_contents($this->storage.'/app/approval-letters/letter-1.pdf', 'letter');
        $this->app->useStoragePath($this->storage);

        DailyBackupTrigger::$launcher = null;
    }

    protected function tearDown(): void
    {
        DailyBackupTrigger::$launcher = null;
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_backup_command_fails_on_unsupported_driver(): void
    {
        $this->artisan('prosthetics:backup')
            ->expectsOutputToContain('PostgreSQL (pg_dump) or MySQL (mysqldump)')
            ->assertFailed();
    }

    public function test_all_uploaded_files_are_archived_even_when_database_dump_fails(): void
    {
        $this->artisan('prosthetics:backup')->assertFailed();

        $zips = glob($this->storage.'/backups/files-*.zip');
        $this->assertCount(1, $zips);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zips[0]));
        $this->assertNotFalse($zip->locateName('storage/app/public/branding/logo.png'));
        $this->assertNotFalse($zip->locateName('storage/app/approval-letters/letter-1.pdf'));
        $zip->close();

        // فشل قاعدة البيانات لا يُسجَّل كنجاح — المحاولة تُعاد لاحقاً.
        $this->assertArrayNotHasKey('last_success_day', DailyBackupTrigger::readState());
    }

    public function test_old_backups_are_pruned_but_the_newest_database_dumps_are_always_kept(): void
    {
        File::ensureDirectoryExists($this->storage.'/backups');
        $dumps = [];
        foreach ([40, 30, 20, 10] as $i => $daysAgo) {
            $dumps[$daysAgo] = $this->storage."/backups/db-2020-01-0{$i}_000000.sql.gz";
            file_put_contents($dumps[$daysAgo], 'dump');
            touch($dumps[$daysAgo], now()->subDays($daysAgo)->getTimestamp());
        }
        $oldZip = $this->storage.'/backups/files-2020-01-01_000000.zip';
        file_put_contents($oldZip, 'zip');
        touch($oldZip, now()->subDays(30)->getTimestamp());

        // قاعدة البيانات هنا sqlite → النسخة الجديدة تفشل (زي لما كلمة سر قاعدة البيانات اتغيرت على السيرفر).
        $this->artisan('prosthetics:backup', ['--keep' => 7]);

        $this->assertFileDoesNotExist($oldZip, 'الملفات القديمة تُحذف');
        $this->assertFileDoesNotExist($dumps[40], 'أقدم من آخر 3 نسخ قاعدة بيانات');
        $this->assertFileExists($dumps[30]);
        $this->assertFileExists($dumps[20]);
        $this->assertFileExists($dumps[10], 'آخر نسخة سليمة لا تُحذف مهما كان عمرها');
    }

    public function test_first_request_of_the_day_starts_one_background_backup(): void
    {
        $launches = 0;
        DailyBackupTrigger::$launcher = function () use (&$launches) {
            $launches++;

            return true;
        };
        config(['backup.auto_daily' => true]);

        $this->runTriggerAsWebRequest();
        $this->runTriggerAsWebRequest();

        $this->assertSame(1, $launches, 'Only one backup per day is started');

        DailyBackupTrigger::markSuccess(['db-x.sql.gz']);
        $this->runTriggerAsWebRequest();
        $this->assertSame(1, $launches, 'No new backup after today succeeded');
    }

    public function test_auto_backup_can_be_disabled(): void
    {
        $launches = 0;
        DailyBackupTrigger::$launcher = function () use (&$launches) {
            $launches++;

            return true;
        };
        config(['backup.auto_daily' => false]);

        $this->runTriggerAsWebRequest();

        $this->assertSame(0, $launches);
    }

    public function test_web_requests_run_the_trigger_middleware(): void
    {
        $this->assertContains(
            \App\Http\Middleware\TriggerDailyBackup::class,
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'],
        );
    }

    private function runTriggerAsWebRequest(): void
    {
        (new class extends DailyBackupTrigger
        {
            protected function inConsole(): bool
            {
                return false;
            }
        })->maybeRun();
    }
}
