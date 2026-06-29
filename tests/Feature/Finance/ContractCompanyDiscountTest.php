<?php

namespace Tests\Feature\Finance;

use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Services\FinancialPostingService;
use App\Services\QuoteService;
use App\Support\ContractBillingSplit;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ContractCompanyDiscountTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_create_company_with_company_share_percent(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.companies.store'), [
                'name'             => 'جهة تحمّل اختبار',
                'is_military'      => false,
                'is_contracted'    => true,
                'discount_percent' => 15,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('contract_companies', [
            'name'             => 'جهة تحمّل اختبار',
            'discount_percent' => 15,
        ]);
    }

    public function test_admin_can_update_company_share_percent(): void
    {
        $admin   = $this->userWithRole('admin');
        $company = $this->civilianCompany('شركة تحديث تحمّل');
        $company->update(['discount_percent' => 5]);

        $this->actingAs($admin)
            ->putJson(route('admin.companies.update', $company), [
                'discount_percent' => 20,
            ])
            ->assertOk();

        $this->assertSame('20.00', $company->fresh()->discount_percent);
    }

    public function test_billing_split_helper_divides_gross_between_patient_and_company(): void
    {
        $company = ContractCompany::create([
            'company_code'     => 'CO-SPLIT',
            'name'             => 'تأمين صحي',
            'is_military'      => false,
            'is_contracted'    => true,
            'discount_percent' => 20,
        ]);

        $split = $company->billingSplit(1000);

        $this->assertSame(1000.0, $split['gross_total']);
        $this->assertSame(800.0, $split['patient_share']);
        $this->assertSame(200.0, $split['company_share']);
        $this->assertSame(20.0, $split['company_share_percent']);

        $company->update(['discount_percent' => 0]);
        $splitZero = $company->fresh()->billingSplit(1000);
        $this->assertSame(1000.0, $splitZero['patient_share']);
        $this->assertSame(0.0, $splitZero['company_share']);
    }

    public function test_quote_issue_keeps_full_gross_for_civilian_case(): void
    {
        $company = $this->civilianCompany('جهة 20%');
        $company->update(['discount_percent' => 20]);

        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_COST_CALC);
        $case->update(['contract_company_id' => $company->id]);

        $pricing = PricingRequest::create([
            'request_no'     => 'PR-SPLIT-001',
            'case_id'        => $case->id,
            'patient_type'   => 'civilian',
            'order_ref'      => $case->order_ref,
            'patient_name'   => $patient->name,
            'company_name'   => $company->name,
            'request_date'   => now()->toDateString(),
            'status_key'     => 'awaiting_admin_approval',
            'computed_total' => 1000,
        ]);

        $quote = app(QuoteService::class)->issue($pricing, 1000.0);

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertSame(1000.0, (float) $quote->total);
    }

    public function test_financial_posting_records_only_company_share_as_debt(): void
    {
        $company = $this->civilianCompany('تأمين 20%');
        $company->update(['discount_percent' => 20]);

        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING);
        $case->update([
            'contract_company_id' => $company->id,
            'quote_total'         => 1000,
        ]);

        app(FinancialPostingService::class)->post($case->fresh(['contractCompany']));

        $debt = ContractCompanyDebt::where('contract_company_id', $company->id)->first();

        $this->assertNotNull($debt);
        $this->assertSame(200.0, (float) $debt->due);

        $split = ContractBillingSplit::forCase($case->fresh(['contractCompany']), 1000);
        $this->assertSame(800.0, $split['patient_share']);
        $this->assertSame(200.0, $split['company_share']);
    }
}
