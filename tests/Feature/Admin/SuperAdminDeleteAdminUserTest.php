<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SuperAdminDeleteAdminUserTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_super_admin_can_delete_limited_admin_user(): void
    {
        $superRole = $this->makeRole(Role::SLUG_SUPER_ADMIN);
        $adminRole = $this->makeRole(Role::SLUG_ADMIN);

        $super = User::query()->create([
            'name' => 'سوبر أدمن',
            'username' => 'super-delete-admin-test',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $superRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $limitedAdmin = User::query()->create([
            'name' => 'أدمن محدود للحذف',
            'username' => 'admin-to-delete-test',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $adminRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($super)
            ->deleteJson(route('admin.employees.destroy', $limitedAdmin))
            ->assertOk()
            ->assertJson(['message' => 'تم حذف الموظف بنجاح.']);

        $this->assertDatabaseMissing('users', ['id' => $limitedAdmin->id]);
    }

    public function test_super_admin_cannot_delete_super_admin_account(): void
    {
        $superRole = $this->makeRole(Role::SLUG_SUPER_ADMIN);

        $super = User::query()->create([
            'name' => 'سوبر أدمن',
            'username' => 'super-no-self-delete',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $superRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $otherSuper = User::query()->create([
            'name' => 'سوبر آخر',
            'username' => 'other-super-admin',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $superRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($super)
            ->deleteJson(route('admin.employees.destroy', $otherSuper))
            ->assertStatus(422)
            ->assertJson(['message' => 'لا يمكن حذف حساب السوبر أدمن.']);

        $this->assertDatabaseHas('users', ['id' => $otherSuper->id]);
    }

    public function test_limited_admin_cannot_delete_another_admin(): void
    {
        $adminRole = $this->makeRole(Role::SLUG_ADMIN);

        $admin = User::query()->create([
            'name' => 'أدمن محدود',
            'username' => 'limited-admin-actor',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $adminRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $otherAdmin = User::query()->create([
            'name' => 'أدمن آخر',
            'username' => 'limited-admin-target',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $adminRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.employees.destroy', $otherAdmin))
            ->assertStatus(422)
            ->assertJson(['message' => 'لا يمكن حذف مسؤول النظام — السوبر أدمن فقط.']);

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_super_admin_can_toggle_limited_admin_status(): void
    {
        $superRole = $this->makeRole(Role::SLUG_SUPER_ADMIN);
        $adminRole = $this->makeRole(Role::SLUG_ADMIN);

        $super = User::query()->create([
            'name' => 'سوبر أدمن',
            'username' => 'super-toggle-admin',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $superRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $limitedAdmin = User::query()->create([
            'name' => 'أدمن للتعطيل',
            'username' => 'admin-to-toggle',
            'password' => Hash::make(UserFactory::TEST_PASSWORD),
            'role_id' => $adminRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($super)
            ->patch(route('admin.employees.toggle', $limitedAdmin))
            ->assertRedirect(route('admin.employees'))
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_INACTIVE, $limitedAdmin->fresh()->status);
    }
}
