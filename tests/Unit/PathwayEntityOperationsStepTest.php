<?php

namespace Tests\Unit;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\PathwayStep;
use App\Models\Quote;
use App\Models\Role;
use App\Services\PathwayConfigService;
use App\Services\PathwayTransitionMessageService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class PathwayEntityOperationsStepTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_entity_case_with_pending_quote_maps_to_operations_quote_step(): void
    {
        $service = app(PathwayConfigService::class);
        $service->resetToDefaults(PathwayStep::PATHWAY_ENTITY);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        Quote::create([
            'quote_no' => 'QT-TEST-001',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_PENDING,
            'status_label' => 'بمكتب التشغيل — بانتظار الإصدار',
            'total' => 1000,
        ]);

        $case->load('quotes', 'patient');

        $this->assertSame('quote', $service->entityOperationsStepKey($case));

        $label = $service->stepLabelForStage($case, CaseRecord::STAGE_OPERATIONS);
        $this->assertStringContainsString('عرض سعر', $label);

        $index = $service->resolveCurrentIndexForPathway($case, PathwayStep::PATHWAY_ENTITY);
        $steps = $service->steps(PathwayStep::PATHWAY_ENTITY);
        $this->assertSame('quote', $steps[$index]['key']);

        $payload = app(PathwayTransitionMessageService::class)->notificationPayload(
            $case,
            WorkflowEvent::QuoteIssued->value,
            CaseRecord::STAGE_QUOTE,
        );

        $this->assertNotNull($payload);
        $this->assertSame(Role::SLUG_OPERATIONS, $payload['role']);
    }

    public function test_entity_issued_quote_awaiting_letter_maps_to_reception_step(): void
    {
        $service = app(PathwayConfigService::class);
        $service->resetToDefaults(PathwayStep::PATHWAY_ENTITY);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        Quote::create([
            'quote_no' => 'QT-TEST-002',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'status_label' => 'بانتظار خطاب موافقة الجهة',
            'total' => 1000,
        ]);

        $case->load('quotes', 'patient');

        $this->assertSame('entity_return', $service->entityOperationsStepKey($case));

        $payload = app(PathwayTransitionMessageService::class)->notificationPayload(
            $case,
            WorkflowEvent::QuoteIssued->value,
            CaseRecord::STAGE_QUOTE,
        );

        $this->assertNotNull($payload);
        $this->assertSame(Role::SLUG_RECEPTION, $payload['role']);
    }
}
