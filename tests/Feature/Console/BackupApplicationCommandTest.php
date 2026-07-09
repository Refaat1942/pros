<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class BackupApplicationCommandTest extends TestCase
{
    public function test_backup_command_fails_on_non_mysql_driver(): void
    {
        $this->artisan('prosthetics:backup')
            ->assertFailed();
    }
}
