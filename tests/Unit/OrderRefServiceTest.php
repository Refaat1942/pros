<?php

namespace Tests\Unit;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Services\OrderRefService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OrderRefServiceTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_generates_six_digit_unique_order_ref(): void
    {
        $service = app(OrderRefService::class);
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        $first = $service->generate();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $first);

        CaseRecord::create([
            'case_no'             => 'CASE-TEST-0001',
            'order_ref'           => $first,
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_RECEPTION,
        ]);

        $second = $service->generate();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $second);
        $this->assertNotSame($first, $second);
    }
}
