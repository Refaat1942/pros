<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_users_keeps_only_super_admin_and_roles(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        $rolesBefore = Role::query()->count();
        $this->assertGreaterThan(1, User::query()->count());

        $this->artisan('prosthetics:purge-users --force')
            ->assertSuccessful()
            ->expectsOutputToContain('السوبر أدمن محفوظ');

        $remaining = User::query()->where('status', User::STATUS_ACTIVE)->pluck('username')->all();

        $this->assertSame(['superadmin'], $remaining);
        $this->assertSame($rolesBefore, Role::query()->count());
    }

    public function test_purge_users_refuses_without_super_admin(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        User::query()->where('username', 'superadmin')->delete();

        $this->artisan('prosthetics:purge-users --force')->assertFailed();

        $this->assertGreaterThan(0, User::query()->count());
    }
}
