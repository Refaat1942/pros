<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Patient;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecOrdersFilterTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_orders_filters_by_date_range(): void
    {
        $company  = $this->civilianCompany();
        $specUser = $this->userWithRole('spec');

        $oldPatient = Patient::create([
            'patient_code'        => '920001',
            'patient_qr'          => 'QR-920001',
            'tracking_uid'        => 'spec-filter-old',
            'name'                => 'مريض قديم',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->subDays(10)->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);

        $newPatient = Patient::create([
            'patient_code'        => '920002',
            'patient_qr'          => 'QR-920002',
            'tracking_uid'        => 'spec-filter-new',
            'name'                => 'مريض حديث',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);

        $oldCase = CaseRecord::create([
            'case_no'             => 'CASE-2026-9201',
            'order_ref'           => 'ORD-2026-9201',
            'patient_id'          => $oldPatient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_TECHNICAL,
        ]);
        $oldCase->forceFill(['created_at' => now()->subDays(5)])->save();

        $newCase = CaseRecord::create([
            'case_no'             => 'CASE-2026-9202',
            'order_ref'           => 'ORD-2026-9202',
            'patient_id'          => $newPatient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_TECHNICAL,
        ]);
        $newCase->forceFill(['created_at' => now()->subDay()])->save();

        $from = now()->subDays(2)->toDateString();
        $to   = now()->toDateString();

        $this->actingAs($specUser)
            ->getJson('/spec/orders/list?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.order_ref', 'ORD-2026-9202');
    }

    public function test_spec_orders_export_respects_date_filter(): void
    {
        $company  = $this->civilianCompany();
        $specUser = $this->userWithRole('spec');

        $patient = Patient::create([
            'patient_code'        => '920003',
            'patient_qr'          => 'QR-920003',
            'tracking_uid'        => 'spec-export-one',
            'name'                => 'مريض تصدير',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);

        CaseRecord::create([
            'case_no'             => 'CASE-2026-9203',
            'order_ref'           => 'ORD-2026-9203',
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_TECHNICAL,
        ]);

        $from = now()->subDay()->toDateString();
        $to   = now()->toDateString();

        $response = $this->actingAs($specUser)
            ->get('/spec/orders/export?from=' . $from . '&to=' . $to);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ORD-2026-9203', $response->streamedContent());
    }
}
