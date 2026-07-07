<?php

namespace Tests\Feature\Spec;

use App\Enums\SpecEditRequestStatus;
use App\Models\AppNotification;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordItem;
use App\Models\Role;
use App\Models\SpecEditRequest;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use App\Services\MedicalRecordService;
use App\Services\SpecService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecEditRequestTest extends TestCase
{
    use ProstheticTestHelper;

    private function submitSpecToAdjustments(): array
    {
        $this->stockItem('RM-EDIT-A', qty: 20);
        $this->stockItem('RM-EDIT-B', qty: 20);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $doctor = $this->userWithRole('doctor');
        $spec = $this->userWithRole('spec');

        $this->actingAs($doctor);

        $record = MedicalRecord::create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_type' => $patient->patient_type,
            'diagnosis' => 'بتر',
            'doctor_name' => $doctor->name,
            'record_date' => now()->toDateString(),
            'status' => MedicalRecord::STATUS_DRAFT,
            'locked' => false,
        ]);

        MedicalRecordItem::create([
            'medical_record_id' => $record->id,
            'stock_item_code' => 'RM-EDIT-A',
            'name' => 'صنف A',
            'qty' => 1,
        ]);

        app(MedicalRecordService::class)->lock($record);

        $case = CaseRecord::where('patient_id', $patient->id)->firstOrFail();

        $draft = TechOrderSpec::create([
            'order_ref' => $case->order_ref,
            'case_id' => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name' => $doctor->name,
            'locked' => false,
        ]);

        TechOrderSpecItem::create([
            'tech_order_spec_id' => $draft->id,
            'stock_item_code' => 'RM-EDIT-A',
            'name' => 'صنف A',
            'qty' => 1,
        ]);

        $this->actingAs($spec);
        app(SpecService::class)->submit($draft->fresh('items'));

        $case->refresh();
        $draft->refresh();

        return compact('case', 'draft', 'spec', 'patient');
    }

    public function test_spec_can_submit_edit_request_while_in_adjustments(): void
    {
        ['case' => $case, 'draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $this->assertSame(CaseRecord::STAGE_ADJUSTMENTS, $case->stage_key);

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'tech_notes' => 'تعديل الكمية',
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-A', 'name' => 'صنف A', 'qty' => 2],
                    ['stock_item_code' => 'RM-EDIT-B', 'name' => 'صنف B', 'qty' => 1],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', SpecEditRequestStatus::Pending->value);

        $this->assertDatabaseHas('spec_edit_requests', [
            'tech_order_spec_id' => $draft->id,
            'status' => SpecEditRequestStatus::Pending->value,
        ]);

        $this->assertSame(
            1,
            AppNotification::forRole(Role::SLUG_ADMIN)->where('event', 'spec_edit_requested')->count()
        );
    }

    public function test_admin_approve_applies_changes_and_notifies_spec(): void
    {
        ['case' => $case, 'draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-A', 'name' => 'صنف A', 'qty' => 3],
                ],
            ])
            ->assertCreated();

        $request = SpecEditRequest::where('tech_order_spec_id', $draft->id)->firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.spec-edit-requests.approve', $request))
            ->assertOk()
            ->assertJsonPath('request.status', SpecEditRequestStatus::Approved->value);

        $draft->refresh()->load('items');
        $this->assertSame(3, (int) $draft->items->firstWhere('stock_item_code', 'RM-EDIT-A')->qty);

        $bomSpecQty = BomItem::whereHas('bom', fn ($q) => $q->where('case_id', $case->id))
            ->where('source', BomItem::SOURCE_SPEC)
            ->where('stock_item_code', 'RM-EDIT-A')
            ->value('qty');

        $this->assertSame(3, (int) $bomSpecQty);

        $this->assertSame(
            1,
            AppNotification::forRole(Role::SLUG_SPEC)->where('event', 'spec_edit_approved')->count()
        );
    }

    public function test_admin_reject_without_reason_and_notifies_spec(): void
    {
        ['draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-A', 'name' => 'صنف A', 'qty' => 2],
                ],
            ])
            ->assertCreated();

        $request = SpecEditRequest::firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.spec-edit-requests.reject', $request), [])
            ->assertOk()
            ->assertJsonPath('request.status', SpecEditRequestStatus::Rejected->value)
            ->assertJsonPath('message', 'تم رفض طلب التعديل — أُرسل إشعار للفني.');

        $notification = AppNotification::forRole(Role::SLUG_SPEC)
            ->where('event', 'spec_edit_rejected')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('رفضت الإدارة', $notification->body);
    }

    public function test_admin_reject_with_notes_includes_notes_in_notification(): void
    {
        ['draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-A', 'name' => 'صنف A', 'qty' => 2],
                ],
            ])
            ->assertCreated();

        $request = SpecEditRequest::firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.spec-edit-requests.reject', $request), [
                'rejection_notes' => 'الكمية مبالغ فيها',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', SpecEditRequestStatus::Rejected->value);

        $notification = AppNotification::forRole(Role::SLUG_SPEC)
            ->where('event', 'spec_edit_rejected')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('الكمية مبالغ فيها', $notification->body);
    }

    public function test_admin_requester_receives_reject_notification_on_admin_role(): void
    {
        ['draft' => $draft] = $this->submitSpecToAdjustments();

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-B', 'name' => 'صنف B', 'qty' => 1],
                ],
            ])
            ->assertCreated();

        $request = SpecEditRequest::latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.spec-edit-requests.reject', $request), [
                'rejection_notes' => 'مرفوض من الاختبار',
            ])
            ->assertOk();

        $this->assertNotNull(
            AppNotification::forRole(Role::SLUG_SPEC)
                ->where('event', 'spec_edit_rejected')
                ->first()
        );

        $this->assertNotNull(
            AppNotification::forRole(Role::SLUG_ADMIN)
                ->where('event', 'spec_edit_rejected')
                ->first()
        );
    }

    public function test_cannot_submit_second_pending_request(): void
    {
        ['draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $payload = [
            'items' => [
                ['stock_item_code' => 'RM-EDIT-A', 'name' => 'صنف A', 'qty' => 2],
            ],
        ];

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), $payload)
            ->assertCreated();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), $payload)
            ->assertStatus(422);
    }

    public function test_cannot_submit_edit_after_admin_rejection(): void
    {
        ['draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-A', 'name' => 'صنف A', 'qty' => 2],
                ],
            ])
            ->assertCreated();

        $request = SpecEditRequest::firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.spec-edit-requests.reject', $request), [
                'rejection_notes' => 'غير مناسب',
            ])
            ->assertOk();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-B', 'name' => 'صنف B', 'qty' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'تم رفض طلب تعديل سابق من الإدارة — لا يمكن إرسال طلب جديد على هذا التوصيف.']);

        $this->actingAs($specUser)
            ->get('/spec/spec')
            ->assertOk()
            ->assertSee('رُفض طلب التعديل', false)
            ->assertSee('لا يمكن إرسال طلب جديد', false);
    }

    public function test_spec_preview_page_shows_original_and_proposed_edit_items(): void
    {
        ['draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'tech_notes' => 'استبدال الصنف',
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-B', 'name' => 'صنف B', 'qty' => 2],
                ],
            ])
            ->assertCreated();

        $response = $this->actingAs($specUser)->get('/spec/spec');

        $response->assertOk()
            ->assertSee('التوصيف الأساسي', false)
            ->assertSee('بنود طلب التعديل', false)
            ->assertSee('RM-EDIT-A', false)
            ->assertSee('RM-EDIT-B', false)
            ->assertSee('استبدال الصنف', false);
    }

    public function test_admin_sidebar_shows_pending_count_beside_spec_edit_requests_link(): void
    {
        ['draft' => $draft, 'spec' => $specUser] = $this->submitSpecToAdjustments();

        $this->actingAs($specUser)
            ->postJson(route('spec.spec.edit-request.store', $draft), [
                'tech_notes' => 'تعديل',
                'items' => [
                    ['stock_item_code' => 'RM-EDIT-B', 'name' => 'صنف B', 'qty' => 1],
                ],
            ])
            ->assertCreated();

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/spec-edit-requests')
            ->assertOk()
            ->assertSee('id="sidebarSpecEditReqBadge"', false)
            ->assertSee('>1</span>', false)
            ->assertSee('title="بانتظار الموافقة"', false);
    }
}
