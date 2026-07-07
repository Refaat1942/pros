<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\TechOrderSpec;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecPreviewSortTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_submitted_specs_preview_lists_newest_first(): void
    {
        $company = $this->civilianCompany();
        $specUser = $this->userWithRole('spec');

        $olderPatient = Patient::create([
            'patient_code' => '900001',
            'patient_qr' => 'QR-900001',
            'tracking_uid' => 'spec-preview-old',
            'name' => 'مريض قديم',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'registered_at' => now()->toDateString(),
            'status' => Patient::STATUS_ACTIVE,
        ]);

        $newerPatient = Patient::create([
            'patient_code' => '900002',
            'patient_qr' => 'QR-900002',
            'tracking_uid' => 'spec-preview-new',
            'name' => 'مريض حديث',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'registered_at' => now()->toDateString(),
            'status' => Patient::STATUS_ACTIVE,
        ]);

        $olderCase = CaseRecord::create([
            'case_no' => 'CASE-2026-0901',
            'order_ref' => 'ORD-2026-0901',
            'patient_id' => $olderPatient->id,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'patient_type' => Patient::TYPE_CIVILIAN,
            'path' => CaseRecord::PATH_STANDARD,
            'stage_key' => CaseRecord::STAGE_COST_CALC,
        ]);

        $newerCase = CaseRecord::create([
            'case_no' => 'CASE-2026-0902',
            'order_ref' => 'ORD-2026-0902',
            'patient_id' => $newerPatient->id,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'patient_type' => Patient::TYPE_CIVILIAN,
            'path' => CaseRecord::PATH_STANDARD,
            'stage_key' => CaseRecord::STAGE_COST_CALC,
        ]);

        $olderSpec = TechOrderSpec::create([
            'order_ref' => $olderCase->order_ref,
            'case_id' => $olderCase->id,
            'patient_name' => $olderPatient->name,
            'company_name' => $company->name,
            'locked' => true,
            'submitted_at' => now()->toDateString(),
        ]);
        $olderSpec->forceFill(['updated_at' => now()->subHours(2)])->save();

        $newerSpec = TechOrderSpec::create([
            'order_ref' => $newerCase->order_ref,
            'case_id' => $newerCase->id,
            'patient_name' => $newerPatient->name,
            'company_name' => $company->name,
            'locked' => true,
            'submitted_at' => now()->toDateString(),
        ]);
        $newerSpec->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        $response = $this->actingAs($specUser)->get('/spec/spec');
        $response->assertOk();

        $content = $response->getContent();
        $newerPos = strpos($content, 'ORD-2026-0902');
        $olderPos = strpos($content, 'ORD-2026-0901');

        $this->assertNotFalse($newerPos);
        $this->assertNotFalse($olderPos);
        $this->assertLessThan($olderPos, $newerPos, 'أحدث توصيف يجب أن يظهر قبل الأقدم');
    }
}
