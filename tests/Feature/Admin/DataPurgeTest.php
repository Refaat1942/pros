<?php

namespace Tests\Feature\Admin;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\StockItem;
use App\Models\StockMovement;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/** «حذف البيانات» — مدير النظام فقط، بكلمة المرور، وحذف مرضى محددين بكل ما يرتبط بهم. */
class DataPurgeTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_only_super_admin_with_correct_password_can_purge(): void
    {
        $patient = $this->cashPatient();

        // مسؤول بصلاحيات محدودة (دور admin) حتى لو اتمنح صفحة الحذف نفسها → ممنوع؛ مدير النظام بس.
        $role = $this->makeRole(\App\Models\Role::SLUG_ADMIN);
        app(\App\Services\PermissionCatalogService::class)->syncToDatabase();
        $role->permissions()->sync(\App\Models\Permission::where('slug', 'admin.data-purge.view')->pluck('id'));
        $limited = \App\Models\User::create(['name' => 'مسؤول', 'username' => 'limited_admin', 'password' => 'password', 'role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($limited)->getJson('/admin/data-purge/patients')->assertForbidden();
        $this->actingAs($limited)->postJson('/admin/data-purge/everything', ['password' => 'password', 'backup' => false])->assertForbidden();

        $boss = $this->userWithRole('super_admin');
        $this->actingAs($boss)->getJson('/admin/data-purge/patients')->assertOk()->assertJsonPath('total', 1);

        // كلمة مرور غلط → ولا حاجة تتمسح.
        $this->actingAs($boss)->postJson('/admin/data-purge/patients', ['ids' => [$patient->id], 'password' => 'wrong', 'backup' => false])
            ->assertStatus(422);
        $this->assertTrue(Patient::whereKey($patient->id)->exists());

        $this->actingAs($boss)->postJson('/admin/data-purge/patients', ['ids' => [$patient->id], 'password' => 'password', 'backup' => false])
            ->assertOk();
        $this->assertFalse(Patient::whereKey($patient->id)->exists());
    }

    public function test_selected_patients_are_deleted_with_related_data_and_stock_restored(): void
    {
        $item = $this->stockItem('RM-001', qty: 10);
        // مريض بحالة اتصرف لها من المخزن (صنف واحد).
        $dispensed = $this->dispensedManufacturingCase($this->civilianPatient($this->civilianCompany()), ['RM-001']);
        $this->assertEquals(9.0, (float) $item->fresh()->qty);

        // مريض نقدي تاني دفع — لازم يفضل زي ما هو.
        $keep = $this->operationsReadyCase($this->cashPatient(), ['RM-001']);
        app(\App\Services\CashierPaymentService::class)->confirmPayment($keep, ['method' => 'cash']);
        $this->assertSame(1, Payment::count());

        $boss = $this->userWithRole('super_admin');
        $this->actingAs($boss)->postJson('/admin/data-purge/patients', [
            'ids' => [$dispensed->patient_id], 'password' => 'password', 'backup' => false,
        ])->assertOk();

        $this->assertFalse(CaseRecord::whereKey($dispensed->id)->exists());
        $this->assertFalse(Patient::whereKey($dispensed->patient_id)->exists());
        $this->assertSame(0, StockMovement::where('reference_type', 'bom')->where('reference_id', $dispensed->bom?->id ?? 0)->count());
        // الصرف اتعكس: الكمية رجعت.
        $this->assertEquals(10.0, (float) $item->fresh()->qty);

        // المريض التاني ومدفوعاته موجودين.
        $this->assertTrue(CaseRecord::whereKey($keep->id)->exists());
        $this->assertSame(1, Payment::count());
    }

    public function test_bulk_targets_require_password_and_run(): void
    {
        $this->stockItem('RM-001', qty: 5);
        $this->cashPatient();
        $boss = $this->userWithRole('super_admin');

        $this->actingAs($boss)->postJson('/admin/data-purge/everything', ['password' => 'nope', 'backup' => false])->assertStatus(422);
        $this->assertSame(1, StockItem::count());

        $this->actingAs($boss)->postJson('/admin/data-purge/everything', ['password' => 'password', 'backup' => false])->assertOk();
        $this->assertSame(0, StockItem::count());
        $this->assertSame(0, Patient::count());

        $this->actingAs($boss)->postJson('/admin/data-purge/notifications', ['password' => 'password', 'backup' => false])->assertOk();
        // هدف غير معروف مش متسجّل كمسار أصلاً.
        $this->assertNotSame(200, $this->actingAs($boss)->postJson('/admin/data-purge/unknown', ['password' => 'password'])->status());
    }

    public function test_page_renders_for_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get('/admin/data-purge')
            ->assertOk()->assertSee('حذف مرضى', false)->assertSee('dpConfirmModal', false);
    }
}
