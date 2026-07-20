<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Database\Factories\UserFactory;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SuperAdminPermissionsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_superadmin_has_full_access_while_limited_admin_is_restricted(): void
    {
        app(PermissionCatalogService::class)->seedRoleDefaults(fullSync: true);

        $superRole = $this->makeRole(Role::SLUG_SUPER_ADMIN);
        $adminRole = $this->makeRole(Role::SLUG_ADMIN);

        $super = User::updateOrCreate(
            ['username' => 'superadmin-test'],
            [
                'name' => 'سوبر أدمن',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $superRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $limited = User::updateOrCreate(
            ['username' => 'admin-limited-test'],
            [
                'name' => 'أدمن محدود',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $adminRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $adminRole->permissions()->sync(
            Permission::query()
                ->where('slug', 'admin.overview.view')
                ->pluck('id')
        );

        $this->assertTrue($super->fresh()->isSuperAdmin());
        $this->assertTrue($super->canViewDashboardPage('admin', 'permissions'));
        $this->assertFalse($limited->fresh()->isSuperAdmin());
        $this->assertTrue($limited->canViewDashboardPage('admin', 'overview'));
        $this->assertFalse($limited->canViewDashboardPage('admin', 'permissions'));
    }

    public function test_superadmin_login_redirects_to_admin_dashboard(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $superRole = $this->makeRole(Role::SLUG_SUPER_ADMIN);

        User::updateOrCreate(
            ['username' => 'superadmin-login'],
            [
                'name' => 'سوبر أدمن',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $superRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $this->post('/login', [
            'username' => 'superadmin-login',
            'password' => UserFactory::TEST_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));
    }
}
