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

    public function test_page_view_grants_linked_admin_action_permission(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $adminRole = $this->makeRole(Role::SLUG_ADMIN);
        $viewSlug = Permission::viewSlug('admin', 'workshop-tracking');

        $adminRole->permissions()->sync(
            Permission::query()->where('slug', $viewSlug)->pluck('id')
        );

        $user = User::updateOrCreate(
            ['username' => 'admin-workshop-view-only'],
            [
                'name' => 'أدمن متابعة قسم الإنتاج',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $adminRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $user = $user->fresh();

        $this->assertTrue($user->canViewDashboardPage('admin', 'workshop-tracking'));
        $this->assertFalse($user->role->hasPermission('view-workshop-tracking'));
        $this->assertTrue($user->hasPermission('view-workshop-tracking'));

        $this->actingAs($user)->getJson(route('admin.workshop-tracking.list'))->assertOk();
    }

    public function test_patient_tracks_list_requires_patient_tracks_page_not_overview(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $adminRole = $this->makeRole(Role::SLUG_ADMIN);
        $adminRole->permissions()->sync(
            Permission::query()->where('slug', Permission::viewSlug('admin', 'patient-tracks'))->pluck('id')
        );

        $user = User::updateOrCreate(
            ['username' => 'admin-patient-tracks-only'],
            [
                'name' => 'أدمن مسار المرضى',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $adminRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $this->actingAs($user->fresh())
            ->getJson(route('admin.patient-tracks.list'))
            ->assertOk();
    }

    public function test_superadmin_can_save_permissions_matrix(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $superRole = $this->makeRole(Role::SLUG_SUPER_ADMIN);
        $receptionRole = $this->makeRole(Role::SLUG_RECEPTION);
        $statsId = Permission::where('slug', 'reception.statistics.view')->value('id');
        $appointmentsId = Permission::where('slug', 'reception.appointments.view')->value('id');

        $this->assertNotNull($statsId);
        $this->assertNotNull($appointmentsId);

        $super = User::updateOrCreate(
            ['username' => 'superadmin-matrix-save'],
            [
                'name' => 'سوبر أدمن',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $superRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $matrixJson = json_encode([
            (string) $receptionRole->id => [(int) $appointmentsId],
        ]);

        $this->actingAs($super)
            ->post(route('admin.permissions.update'), [
                '_token' => csrf_token(),
                'matrix_json' => $matrixJson,
            ])
            ->assertRedirect(route('admin.permissions'))
            ->assertSessionHas('status')
            ->assertSessionHas('success');

        $receptionUser = User::updateOrCreate(
            ['username' => 'reception-matrix-test'],
            [
                'name' => 'استقبال',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $receptionRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $this->actingAs($receptionUser->fresh())
            ->get(route('reception.appointments'))
            ->assertOk();

        $this->actingAs($receptionUser->fresh())
            ->get(route('reception.statistics'))
            ->assertStatus(403);
    }

    public function test_limited_admin_cannot_save_permissions_matrix(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $adminRole = $this->makeRole(Role::SLUG_ADMIN);
        $adminRole->permissions()->sync(
            Permission::query()->where('slug', 'admin.permissions.view')->pluck('id')
        );

        $admin = User::updateOrCreate(
            ['username' => 'admin-matrix-save-test'],
            [
                'name' => 'أدمن محدود',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => $adminRole->id,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $this->actingAs($admin)
            ->post(route('admin.permissions.update'), [
                '_token' => csrf_token(),
                'matrix_json' => '{}',
            ])
            ->assertRedirect(route('admin.permissions'))
            ->assertSessionHas('error');
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
