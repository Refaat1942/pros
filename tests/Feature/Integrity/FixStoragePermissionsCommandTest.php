<?php

namespace Tests\Feature\Integrity;

use Tests\TestCase;

class FixStoragePermissionsCommandTest extends TestCase
{
    public function test_fix_storage_permissions_command_runs(): void
    {
        $this->artisan('prosthetics:fix-storage-permissions')
            ->assertSuccessful();
    }
}
