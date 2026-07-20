<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class BackupApplicationCommandTest extends TestCase
{
    public function test_backup_command_fails_on_unsupported_driver(): void
    {
        $this->artisan('prosthetics:backup')
            ->expectsOutputToContain('PostgreSQL (pg_dump) or MySQL (mysqldump)')
            ->assertFailed();
    }
}
