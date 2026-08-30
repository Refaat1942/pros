<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class EmployeeUsernameAndPasswordResetTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_employee_can_be_created_with_arabic_username(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $super = $this->userWithRole(Role::SLUG_SUPER_ADMIN);
        $receptionRole = $this->makeRole(Role::SLUG_RECEPTION);

        $response = $this->actingAs($super)->post(route('admin.employees.store'), [
            'form' => 'employee',
            'name' => 'موظف عربي',
            'username' => 'محمد_123',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
            'role_id' => $receptionRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response->assertRedirect(route('admin.employees'));
        $this->assertDatabaseHas('users', [
            'username' => 'محمد_123',
            'name' => 'موظف عربي',
        ]);
    }

    public function test_user_can_login_with_arabic_username(): void
    {
        $user = $this->userWithRole(Role::SLUG_RECEPTION);
        $user->update(['username' => 'أحمد', 'name' => 'مستخدم عربي']);

        $response = $this->post(route('login.submit'), [
            'username' => 'أحمد',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('reception.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_super_admin_can_reset_any_user_password(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $super = $this->userWithRole(Role::SLUG_SUPER_ADMIN);
        $receptionRole = $this->makeRole(Role::SLUG_RECEPTION);

        $target = User::query()->create([
            'name' => 'موظف للاختبار',
            'username' => 'emp_reset_test',
            'password' => Hash::make('oldpass'),
            'role_id' => $receptionRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($super)->postJson(
            route('admin.employees.reset-password', $target),
            [
                'password' => 'newpass',
                'password_confirmation' => 'newpass',
            ],
        );

        $response->assertOk()
            ->assertJson(['message' => 'تم إعادة تعيين كلمة المرور بنجاح.']);

        $this->assertTrue(Hash::check('newpass', $target->fresh()->password));
    }

    public function test_non_super_admin_cannot_reset_user_password(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $adminRole = $this->makeRole(Role::SLUG_ADMIN);
        $receptionRole = $this->makeRole(Role::SLUG_RECEPTION);

        $admin = User::query()->updateOrCreate(
            ['username' => 'admin-no-reset'],
            [
                'name' => 'أدمن',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'status' => User::STATUS_ACTIVE,
            ],
        );

        $target = User::query()->create([
            'name' => 'موظف',
            'username' => 'emp_no_reset',
            'password' => Hash::make('password'),
            'role_id' => $receptionRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->postJson(
            route('admin.employees.reset-password', $target),
            [
                'password' => 'newpass',
                'password_confirmation' => 'newpass',
            ],
        )->assertForbidden();
    }

    public function test_super_admin_cannot_reset_own_password_via_reset_endpoint(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $super = $this->userWithRole(Role::SLUG_SUPER_ADMIN);

        $this->actingAs($super)->postJson(
            route('admin.employees.reset-password', $super),
            [
                'password' => 'newpass',
                'password_confirmation' => 'newpass',
            ],
        )->assertStatus(422)
            ->assertJson(['message' => 'لا يمكن إعادة تعيين كلمة مرور حسابك من هنا.']);
    }
}
