<?php

namespace Tests\Unit;

use App\Enums\CaseStage;
use App\Enums\PricingRequestStatus;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Support\CaseDisplayStatus;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CaseDisplayStatusTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_pricing_request_shows_case_stage_when_case_is_in_manufacturing(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_CASTING);

        $request = PricingRequest::create([
            'request_no'    => '974673',
            'order_ref'     => $case->order_ref,
            'case_id'       => $case->id,
            'patient_name'  => $patient->name,
            'company_name'  => $patient->company_name,
            'request_date'  => now()->toDateString(),
            'items_count'   => 2,
            'patient_type'  => $patient->patient_type,
            'status_key'    => PricingRequestStatus::SentToReception->value,
            'step'          => PricingRequest::STEP_QUOTE_READY,
        ]);

        $request->load('caseRecord');

        $display = CaseDisplayStatus::forPricingRequest($request);

        $this->assertSame('case', $display->source);
        $this->assertSame(CaseStage::Manufacturing->label().' — صب', $display->label);
        $this->assertNotSame(PricingRequestStatus::SentToReception->label(), $display->label);
    }

    public function test_pricing_request_falls_back_to_pricing_status_without_case(): void
    {
        $request = PricingRequest::create([
            'request_no'    => 'QT-PENDING-001',
            'order_ref'     => 'ORD-9999',
            'case_id'       => null,
            'patient_name'  => 'مريض تجريبي',
            'request_date'  => now()->toDateString(),
            'items_count'   => 1,
            'patient_type'  => 'civilian',
            'status_key'    => PricingRequestStatus::AwaitingAdminApproval->value,
        ]);

        $display = CaseDisplayStatus::forPricingRequest($request);

        $this->assertSame('pricing', $display->source);
        $this->assertSame(PricingRequestStatus::AwaitingAdminApproval->label(), $display->label);
    }
}
