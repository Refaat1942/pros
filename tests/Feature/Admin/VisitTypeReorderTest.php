<?php

namespace Tests\Feature\Admin;

use App\Models\VisitType;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class VisitTypeReorderTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_reorder_visit_types(): void
    {
        $admin = $this->userWithRole('admin');

        $first = VisitType::create(['name' => 'كشف أولي', 'sort_order' => 10]);
        $second = VisitType::create(['name' => 'متابعة طبية', 'sort_order' => 20]);
        $third = VisitType::create(['name' => 'تسليم الطرف', 'sort_order' => 30]);

        $response = $this->actingAs($admin)->postJson(route('admin.visit-types.reorder'), [
            'ids' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'تم حفظ الترتيب بنجاح.']);

        $this->assertSame(10, $third->fresh()->sort_order);
        $this->assertSame(20, $first->fresh()->sort_order);
        $this->assertSame(30, $second->fresh()->sort_order);
    }

    public function test_reception_visit_types_follow_sort_order(): void
    {
        VisitType::create(['name' => 'تسليم', 'sort_order' => 30]);
        VisitType::create(['name' => 'كشف', 'sort_order' => 10]);
        VisitType::create(['name' => 'متابعة', 'sort_order' => 20]);

        $reception = $this->userWithRole('reception');

        $response = $this->actingAs($reception)->get('/reception/appointments');

        $response->assertOk();
        $response->assertSeeInOrder(['كشف', 'متابعة', 'تسليم']);
    }
}
