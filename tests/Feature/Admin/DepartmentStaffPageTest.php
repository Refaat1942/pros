<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Services\UserPageAccessService;
use Illuminate\Http\UploadedFile;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DepartmentStaffPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_staff_page_renders_without_blade_syntax_error(): void
    {
        $manager = $this->userWithRole(Role::SLUG_SPEC);
        $manager->update(['access_tier' => UserPageAccessService::TIER_DEPARTMENT_ADMIN]);

        $this->actingAs($manager)
            ->get(route('spec.staff'))
            ->assertOk()
            ->assertSee('dept-staff-page', false)
            ->assertSee('dept-staff-intro', false)
            ->assertSee('قائمة الموظفين', false)
            ->assertSee('window.__DEPT_STAFF', false)
            ->assertSee(route('spec.staff'), false);
    }

    public function test_operations_staff_page_renders_dept_staff_layout(): void
    {
        $manager = $this->userWithRole(Role::SLUG_OPERATIONS);
        $manager->update(['access_tier' => UserPageAccessService::TIER_DEPARTMENT_ADMIN]);

        $this->actingAs($manager)
            ->get(route('operations.staff'))
            ->assertOk()
            ->assertSee('dept-staff-page', false)
            ->assertSee('dept-staff-empty', false);
    }
}
