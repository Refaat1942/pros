<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_roles_only_see_their_dashboard_pages(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        $spec = User::where('username', Role::SLUG_SPEC)->firstOrFail();
        $this->assertTrue($spec->canViewDashboardPage('spec', 'catalog'));
        $this->assertFalse($spec->canViewDashboardPage('technical', 'inventory'));
        $this->assertFalse($spec->canViewDashboardPage('admin', 'catalog'));

        $workshop = User::where('username', Role::SLUG_WORKSHOP)->firstOrFail();
        $this->assertTrue($workshop->canViewDashboardPage('workshop', 'sections'));
        $this->assertTrue($workshop->hasPermission('manage-workshop-sections'));
        $this->assertFalse($workshop->canViewDashboardPage('costing', 'costing'));
    }

    public function test_admin_does_not_inherit_all_operational_dashboards(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        $admin = User::where('username', Role::SLUG_ADMIN)->firstOrFail();
        $this->assertTrue($admin->canViewDashboardPage('admin', 'employees'));
        $this->assertFalse($admin->canViewDashboardPage('spec', 'orders'));
    }

    public function test_catalog_browse_pages_registered_in_permissions(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        $this->assertNotNull(Permission::query()->where('slug', 'spec.catalog.view')->first());
        $this->assertNotNull(Permission::query()->where('slug', 'workshop.sections.view')->first());
    }
}
