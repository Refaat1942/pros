<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Patient;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecOrdersSortTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_orders_lists_newest_first(): void
    {
        $company = $this->civilianCompany();
        $specUser = $this->userWithRole('spec');

        $olderPatient = Patient::create([
            'patient_code' => '910001',
            'patient_qr' => 'QR-910001',
            'tracking_uid' => 'spec-orders-old',
            'name' => 'مريض قديم',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'registered_at' => now()->toDateString(),
            'status' => Patient::STATUS_ACTIVE,
        ]);

        $newerPatient = Patient::create([
            'patient_code' => '910002',
            'patient_qr' => 'QR-910002',
            'tracking_uid' => 'spec-orders-new',
            'name' => 'مريض حديث',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'registered_at' => now()->toDateString(),
            'status' => Patient::STATUS_ACTIVE,
        ]);

        $olderCase = CaseRecord::create([
            'case_no' => 'CASE-2026-9101',
            'order_ref' => 'ORD-2026-9101',
            'patient_id' => $olderPatient->id,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'patient_type' => Patient::TYPE_CIVILIAN,
            'path' => CaseRecord::PATH_STANDARD,
            'stage_key' => CaseRecord::STAGE_TECHNICAL,
        ]);
        $olderCase->forceFill(['updated_at' => now()->subHours(3)])->save();

        $newerCase = CaseRecord::create([
            'case_no' => 'CASE-2026-9102',
            'order_ref' => 'ORD-2026-9102',
            'patient_id' => $newerPatient->id,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'patient_type' => Patient::TYPE_CIVILIAN,
            'path' => CaseRecord::PATH_STANDARD,
            'stage_key' => CaseRecord::STAGE_TECHNICAL,
        ]);
        $newerCase->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        $pageResponse = $this->actingAs($specUser)->get('/spec/orders');
        $pageResponse->assertOk();

        $content = $pageResponse->getContent();
        $newerPos = strpos($content, 'ORD-2026-9102');
        $olderPos = strpos($content, 'ORD-2026-9101');

        $this->assertNotFalse($newerPos);
        $this->assertNotFalse($olderPos);
        $this->assertLessThan($olderPos, $newerPos, 'أحدث طلب توصيف يجب أن يظهر قبل الأقدم');

        $apiResponse = $this->actingAs($specUser)->getJson('/spec/orders/list');
        $apiResponse->assertOk();

        $refs = collect($apiResponse->json('data'))->pluck('order_ref')->all();
        $this->assertSame(['ORD-2026-9102', 'ORD-2026-9101'], array_slice($refs, 0, 2));
    }
}
