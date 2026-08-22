<?php

namespace Tests\Unit;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\PathwayStep;
use App\Models\Quote;
use App\Models\Role;
use App\Models\StockItem;
use App\Services\PathwayConfigService;
use App\Services\PathwayTransitionMessageService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class PathwayTransitionMessageServiceTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_transfer_message_uses_pathway_step_labels(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $message = app(PathwayTransitionMessageService::class)->transferMessage(
            $case->load('patient'),
            WorkflowEvent::AdjustmentsCompleted->value,
            CaseRecord::STAGE_ADJUSTMENTS,
        );

        $this->assertStringContainsString('تم التحويل من', $message);
        $this->assertStringContainsString('إلى', $message);
        $this->assertStringNotContainsString('adjustments', $message);
    }

    public function test_ready_delivery_resolves_to_delivery_step(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);

        $label = app(PathwayConfigService::class)->currentStepLabelForCase($case);

        $this->assertStringContainsString('التسليم', $label);
    }

    public function test_military_costing_message_targets_operations_not_quote(): void
    {
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $service = app(PathwayTransitionMessageService::class);
        $message = $service->transferMessage(
            $case->load('patient'),
            WorkflowEvent::CostingCompleted->value,
            CaseRecord::STAGE_COST_CALC,
        );

        $this->assertStringContainsString('تم التحويل من', $message);
        $this->assertStringNotContainsString('عرض سعر', $message);

        $payload = $service->notificationPayload(
            $case,
            WorkflowEvent::CostingCompleted->value,
            CaseRecord::STAGE_COST_CALC,
        );

        $this->assertNotNull($payload);
        $this->assertSame(Role::SLUG_OPERATIONS, $payload['role']);
        $this->assertSame('/operations/pending', $payload['url']);
    }

    public function test_civilian_costing_notification_suppressed_quote_issued_notifies_operations(): void
    {
        app(PathwayConfigService::class)->resetToDefaults(PathwayStep::PATHWAY_CIVILIAN);

        $patient = $this->cashPatient();
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $service = app(PathwayTransitionMessageService::class);

        $this->assertNull($service->notificationPayload(
            $case->load('patient'),
            WorkflowEvent::CostingCompleted->value,
            CaseRecord::STAGE_COST_CALC,
        ));

        $payload = $service->notificationPayload(
            $case,
            WorkflowEvent::QuoteIssued->value,
            CaseRecord::STAGE_QUOTE,
        );

        $this->assertNotNull($payload);
        $this->assertSame(Role::SLUG_OPERATIONS, $payload['role']);
        $this->assertStringContainsString('تم التحويل', $payload['body']);
    }

    public function test_military_quote_issued_notification_suppressed(): void
    {
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_QUOTE);

        $payload = app(PathwayTransitionMessageService::class)->notificationPayload(
            $case->load('patient'),
            WorkflowEvent::QuoteIssued->value,
            CaseRecord::STAGE_QUOTE,
        );

        $this->assertNull($payload);
    }

    public function test_entity_costing_targets_quote_step(): void
    {
        app(PathwayConfigService::class)->resetToDefaults(PathwayStep::PATHWAY_ENTITY);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $service = app(PathwayTransitionMessageService::class);
        $target = $service->resolveTargetStage($case->load('patient'), WorkflowEvent::CostingCompleted->value);

        $this->assertSame(CaseRecord::STAGE_QUOTE, $target);

        $payload = $service->notificationPayload(
            $case,
            WorkflowEvent::CostingCompleted->value,
            CaseRecord::STAGE_COST_CALC,
        );

        $this->assertNotNull($payload);
        $this->assertSame(Role::SLUG_OPERATIONS, $payload['role']);
    }

    public function test_sent_to_cashier_targets_cashier_role(): void
    {
        app(PathwayConfigService::class)->resetToDefaults(PathwayStep::PATHWAY_CIVILIAN);

        $patient = $this->cashPatient();
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $payload = app(PathwayTransitionMessageService::class)->notificationPayload(
            $case->load('patient'),
            WorkflowEvent::SentToCashier->value,
            CaseRecord::STAGE_OPERATIONS,
        );

        $this->assertNotNull($payload);
        $this->assertSame(Role::SLUG_CASHIER, $payload['role']);
        $this->assertSame('/cashier/cashier', $payload['url']);
    }

    public function test_operations_approved_targets_warehouse(): void
    {
        app(PathwayConfigService::class)->resetToDefaults(PathwayStep::PATHWAY_CIVILIAN);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $message = app(PathwayTransitionMessageService::class)->transferMessage(
            $case->load('patient'),
            WorkflowEvent::OperationsApproved->value,
            CaseRecord::STAGE_OPERATIONS,
        );

        $this->assertStringContainsString('المخزن', $message);

        $payload = app(PathwayTransitionMessageService::class)->notificationPayload(
            $case,
            WorkflowEvent::OperationsApproved->value,
            CaseRecord::STAGE_OPERATIONS,
        );

        $this->assertSame(Role::SLUG_TECHNICAL, $payload['role']);
        $this->assertSame('/technical/bom', $payload['url']);
    }

    public function test_entity_quote_released_payload_targets_reception(): void
    {
        app(PathwayConfigService::class)->resetToDefaults(PathwayStep::PATHWAY_ENTITY);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        Quote::create([
            'quote_no' => 'QT-REL-001',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'status_label' => 'بانتظار خطاب موافقة الجهة',
            'total' => 1000,
        ]);

        $payload = app(PathwayTransitionMessageService::class)->entityQuoteReleasedPayload(
            $case->load('patient', 'quotes'),
        );

        $this->assertSame(Role::SLUG_RECEPTION, $payload['role']);
        $this->assertSame('/reception/quote', $payload['url']);
        $this->assertStringContainsString('خطاب', $payload['title']);
        $this->assertStringContainsString('عرض سعر', $payload['body']);
        $this->assertStringContainsString('خطاب', $payload['body']);
    }

    public function test_adjustments_complete_message_uses_step_labels(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $message = app(PathwayTransitionMessageService::class)->transferMessage(
            $case->load('patient'),
            WorkflowEvent::AdjustmentsCompleted->value,
            CaseRecord::STAGE_ADJUSTMENTS,
        );

        $this->assertStringContainsString('المعدلات', $message);
        $this->assertStringContainsString('الاعتماد', $message);
    }
}
