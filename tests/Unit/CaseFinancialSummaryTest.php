<?php

namespace Tests\Unit;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Support\CaseFinancialSummary;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CaseFinancialSummaryTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_total_cost_prefers_invoice_then_quote(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'invoice_total' => 500.00,
            'quote_total' => 400.00,
            'total_cost' => 300.00,
        ]);

        $this->assertSame(500.0, CaseFinancialSummary::totalCost($case->fresh()));
    }

    public function test_total_cost_falls_back_to_pricing_request(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);

        PricingRequest::create([
            'case_id' => $case->id,
            'request_no' => 'PR-2026-0099',
            'computed_total' => 2750.00,
            'status_key' => 'awaiting_admin_approval',
            'patient_type' => 'civilian',
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'request_date' => now()->toDateString(),
        ]);

        $this->assertSame(2750.0, CaseFinancialSummary::totalCost($case->fresh(['pricingRequest'])));
    }

    public function test_total_cost_falls_back_to_bom_items(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);

        $bom = Bom::create([
            'case_id' => $case->id,
            'bom_no' => 'BOM-FIN-01',
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'stage' => Bom::STAGE_FINISHED,
        ]);

        BomItem::create([
            'bom_id' => $bom->id,
            'stock_item_code' => 'RM-001',
            'name' => 'صنف RM-001',
            'qty' => 2,
            'unit_cost' => 150.00,
        ]);

        $this->assertSame(300.0, CaseFinancialSummary::totalCost($case->fresh(['bom.items'])));
    }

    public function test_paid_amount_for_delivered_case_defaults_to_total(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => 1200.00, 'paid' => 0]);

        $this->assertSame(1200.0, CaseFinancialSummary::paidAmount($case->fresh()));
    }

    public function test_sync_on_delivery_persists_total_and_paid(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => 850.00, 'total_cost' => 0, 'paid' => 0]);

        CaseFinancialSummary::syncOnDelivery($case->fresh());

        $case->refresh();
        $this->assertSame(850.0, (float) $case->total_cost);
        $this->assertSame(850.0, (float) $case->paid);
    }
}
