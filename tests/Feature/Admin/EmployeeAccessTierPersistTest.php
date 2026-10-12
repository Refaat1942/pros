<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Tests\Support\ProstheticTestCase;

/** موظف بصفحات محددة يفتح في نموذج التعديل كـ«موظف» بصفحاته — لا يتحوّل لمدير قسم عند الحفظ. */
class EmployeeAccessTierPersistTest extends ProstheticTestCase
{
    public function test_staff_tier_and_pages_show_on_admin_edit_form(): void
    {
        $admin = $this->userWithRole('admin');
        $role = $this->userWithRole('technical')->role;

        $this->actingAs($admin)->post('/admin/employees', [
            'name' => 'موظف مخزن', 'username' => 'stock1', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role_id' => $role->id, 'access_tier' => 'department_staff', 'allowed_pages' => json_encode(['inventory']),
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $user = User::where('username', 'stock1')->firstOrFail();
        $this->assertSame('department_staff', $user->access_tier);

        $html = $this->actingAs($admin)->get('/admin/employees?edit='.$user->id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="department_staff"\s*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="department_admin"\s*checked/', $html);
        $this->assertStringContainsString('data-edit-access-tier="department_staff"', $html);
        $this->assertMatchesRegularExpression('/data-edit-allowed-pages=\'\[[^\]]*"inventory"/', $html);
    }
}
