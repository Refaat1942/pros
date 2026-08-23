<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\UserPageAccessService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DepartmentStaffManagementTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_department_manager_can_access_staff_page(): void
    {
        $manager = $this->userWithRole(Role::SLUG_RECEPTION);
        $manager->update(['access_tier' => UserPageAccessService::TIER_DEPARTMENT_ADMIN]);

        $this->assertTrue($manager->canViewDashboardPage('reception', 'staff'));
        $this->assertFalse($manager->canViewDashboardPage('doctor', 'staff'));
    }

    public function test_department_staff_cannot_access_staff_page(): void
    {
        $staff = $this->userWithRole(Role::SLUG_RECEPTION);
        $staff->update([
            'access_tier' => UserPageAccessService::TIER_DEPARTMENT_STAFF,
            'allowed_pages' => ['appointments', 'notifications'],
        ]);

        $this->assertFalse($staff->canViewDashboardPage('reception', 'staff'));
    }

    public function test_department_manager_can_create_staff_member(): void
    {
        $manager = $this->userWithRole(Role::SLUG_OPERATIONS);
        $manager->update(['access_tier' => UserPageAccessService::TIER_DEPARTMENT_ADMIN]);

        $this->actingAs($manager)
            ->post(route('operations.staff.store'), [
                'name' => 'موظف تشغيل',
                'username' => 'ops_staff_01',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status' => User::STATUS_ACTIVE,
                'allowed_pages' => ['pending', 'notifications'],
            ])
            ->assertRedirect(route('operations.staff'));

        $this->assertDatabaseHas('users', [
            'username' => 'ops_staff_01',
            'role_id' => $manager->role_id,
            'access_tier' => UserPageAccessService::TIER_DEPARTMENT_STAFF,
        ]);
    }

    public function test_department_manager_can_reset_staff_password(): void
    {
        $manager = $this->userWithRole(Role::SLUG_TECHNICAL);
        $manager->update(['access_tier' => UserPageAccessService::TIER_DEPARTMENT_ADMIN]);

        $staff = User::factory()->create([
            'role_id' => $manager->role_id,
            'access_tier' => UserPageAccessService::TIER_DEPARTMENT_STAFF,
            'allowed_pages' => ['bom', 'notifications'],
        ]);

        $this->actingAs($manager)
            ->postJson(route('technical.staff.reset-password', $staff), [
                'password' => 'newpass99',
                'password_confirmation' => 'newpass99',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'تم إعادة تعيين كلمة المرور بنجاح.');
    }
}
