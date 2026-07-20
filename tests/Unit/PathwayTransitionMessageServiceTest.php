<?php

namespace Tests\Unit;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
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
}
