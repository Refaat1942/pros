<?php

namespace Tests\Feature\Finance;

use App\Models\Bom;
use App\Models\CaseRecord;
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

    public function test_admin_can_create_company_with_discount_percent(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.companies.store'), [
                'name'             => 'جهة خصم اختبار',
                'is_military'      => false,
                'is_contracted'    => true,
                'discount_percent' => 15,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('contract_companies', [
            'name'             => 'جهة خصم اختبار',
            'discount_percent' => 15,
        ]);
    }

    public function test_admin_can_update_company_discount_percent(): void
    {
        $admin   = $this->userWithRole('admin');
        $company = $this->civilianCompany('شركة تحديث خصم');
        $company->update(['discount_percent' => 5]);

        $this->actingAs($admin)
            ->putJson(route('admin.companies.update', $company), [
                'discount_percent' => 20,
            ])
            ->assertOk();

        $this->assertSame('20.00', $company->fresh()->discount_percent);
    }

    public function test_billing_split_applies_discount_to_patient_share(): void
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
        $this->assertSame(1000.0, $split['company_share']);
        $this->assertSame(20.0, $split['company_share_percent']);
        $this->assertSame(80.0, $split['patient_share_percent']);

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

    public function test_quote_print_shows_net_total_after_contract_discount(): void
    {
        $company = $this->civilianCompany('التأمين الصحي');
        $company->update(['discount_percent' => 10]);

        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_COST_CALC);
        $case->update(['contract_company_id' => $company->id]);

        $pricing = PricingRequest::create([
            'request_no'     => 'PR-PRINT-001',
            'case_id'        => $case->id,
            'patient_type'   => 'civilian',
            'order_ref'      => $case->order_ref,
            'patient_name'   => $patient->name,
            'company_name'   => $company->name,
            'request_date'   => now()->toDateString(),
            'status_key'     => 'awaiting_admin_approval',
            'computed_total' => 4000,
        ]);

        $quote = app(QuoteService::class)->issue($pricing, 4000.0);
        $quote->update(['status' => Quote::STATUS_ISSUED]);

        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get(route('operations.quote.print', $quote))
            ->assertOk()
            ->assertSee('خصم جهة التعاقد', false)
            ->assertSee('10%', false)
            ->assertSee('3,600', false)
            ->assertSee('4,000', false);
    }

    public function test_financial_posting_records_full_gross_as_entity_debt(): void
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
        $this->assertSame(1000.0, (float) $debt->due);

        $split = ContractBillingSplit::forCase($case->fresh(['contractCompany']), 1000);
        $this->assertSame(800.0, $split['patient_share']);
        $this->assertSame(1000.0, $split['company_share']);
    }

    public function test_work_order_print_shows_net_amount_after_contract_discount(): void
    {
        $company = $this->civilianCompany('التأمين الصحي');
        $company->update(['discount_percent' => 10]);

        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update([
            'contract_company_id' => $company->id,
            'work_order_no'       => 'WO-2026-0001',
            'quote_total'         => 4000,
            'quote_no'            => 'QT-2026-0001',
        ]);

        Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => 'BOM-WO-001',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_WIP,
        ]);

        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get(route('operations.work-order.print', $case))
            ->assertOk()
            ->assertSee('3,600', false)
            ->assertSee('WO-2026-0001', false);
    }
}
