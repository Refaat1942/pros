<?php

namespace Tests\Feature\Console;

use App\Models\AppNotification;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HousekeepingCommandsTest extends TestCase
{
    public function test_purge_notifications_deletes_all_rows(): void
    {
        AppNotification::create([
            'role_slug' => 'admin',
            'event'     => 'test',
            'title'     => 'Test',
            'body'      => 'Body',
        ]);

        $this->artisan('prosthetics:purge-notifications')
            ->assertSuccessful();

        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_purge_logs_clears_log_files(): void
    {
        $path = storage_path('logs/test-housekeeping.log');
        File::put($path, 'sample log line');

        $this->artisan('prosthetics:purge-logs')
            ->assertSuccessful();

        $this->assertSame('', file_get_contents($path));

        @unlink($path);
    }

    public function test_housekeeping_runs_both_commands(): void
    {
        AppNotification::create([
            'role_slug' => 'reception',
            'event'     => 'test',
            'title'     => 'Test',
            'body'      => 'Body',
        ]);

        $path = storage_path('logs/test-housekeeping-combined.log');
        File::put($path, 'line');

        $this->artisan('prosthetics:housekeeping')
            ->assertSuccessful();

        $this->assertSame(0, AppNotification::query()->count());
        $this->assertSame('', file_get_contents($path));

        @unlink($path);
    }
}
