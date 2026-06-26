<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OfficialPrintDocumentsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_operations_pending_exposes_quote_print_url(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        $response = $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertOk();

        $response->assertJsonPath('data.0.quote.print_url', route('operations.quote.print', $quote));
    }

    public function test_operations_can_print_price_quote(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get(route('operations.quote.print', $quote))
            ->assertOk()
            ->assertSee($quote->quote_no, false)
            ->assertSee('عرض سعر', false)
            ->assertSee('عند الرد يذكر رقم', false)
            ->assertSee('<svg', false)
            ->assertSee('onload="window.print()"', false);
    }

    public function test_technical_can_print_issue_voucher(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        app(\App\Services\OperationsService::class)->approve($case->fresh(), 'اختبار');
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $technical = $this->userWithRole('technical');

        $this->actingAs($technical)
            ->get(route('technical.quote.print-issue-voucher', $quote))
            ->assertOk()
            ->assertSee('إذن صرف', false)
            ->assertSee($quote->order_ref, false)
            ->assertSee($quote->patient_name, false)
            ->assertSee('رئيس المخازن', false)
            ->assertSee('onload="window.print()"', false);
    }

    public function test_technical_bom_list_exposes_issue_voucher_print_url(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        app(\App\Services\OperationsService::class)->approve($case->fresh(), 'اختبار');
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $technical = $this->userWithRole('technical');

        $response = $this->actingAs($technical)
            ->getJson('/technical/bom/list')
            ->assertOk();

        $response->assertJsonPath('data.0.issue_voucher_print_url', route('technical.quote.print-issue-voucher', $quote));
    }

    public function test_technical_can_print_issue_voucher_for_military_bom_without_quote(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->militaryPatient($this->militaryCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0099']);

        $bom = Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => 'BOM-MIL-001',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_RAW,
        ]);

        $technical = $this->userWithRole('technical');

        $this->actingAs($technical)
            ->get(route('technical.bom.print-issue-voucher', $bom))
            ->assertOk()
            ->assertSee('إذن صرف', false)
            ->assertSee($case->order_ref, false)
            ->assertSee($patient->name, false)
            ->assertSee('رئيس المخازن', false)
            ->assertSee('onload="window.print()"', false);

        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);
    }

    public function test_technical_bom_list_exposes_issue_voucher_for_military_without_quote(): void
    {
        $patient = $this->militaryPatient($this->militaryCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0100']);

        $bom = Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => 'BOM-MIL-002',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_RAW,
        ]);

        $technical = $this->userWithRole('technical');

        $this->actingAs($technical)
            ->getJson('/technical/bom/list')
            ->assertOk()
            ->assertJsonPath('data.0.issue_voucher_print_url', route('technical.bom.print-issue-voucher', $bom))
            ->assertJsonPath('data.0.path', 'military')
            ->assertJsonPath('data.0.path_label', '🪖 عسكري');
    }

    public function test_operations_can_print_workshop_work_order(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->dispensedManufacturingCase($patient);
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get(route('operations.work-order.print', $case))
            ->assertOk()
            ->assertSee('إذن شغل', false)
            ->assertSee($case->work_order_no, false)
            ->assertSee('المواصفات', false)
            ->assertSee('تاريخ التجربة الأولى', false)
            ->assertSee('تاريخ التجربة الثانية', false)
            ->assertSee('اسم القائم بالتشغيل', false)
            ->assertSee('onload="window.print()"', false);
    }

    public function test_operations_list_exposes_work_order_print_url(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->dispensedManufacturingCase($patient);
        $ops = $this->userWithRole('operations');

        $response = $this->actingAs($ops)
            ->getJson('/operations/operations/list')
            ->assertOk();

        $response->assertJsonPath('data.0.work_order_print_url', route('operations.work-order.print', $case));
    }

    public function test_operations_pending_page_renders_quote_print_label(): void
    {
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get('/operations/pending')
            ->assertOk()
            ->assertSee('operations-pending-dashboard.js', false);
    }

    public function test_technical_bom_page_renders_issue_voucher_print_label(): void
    {
        $technical = $this->userWithRole('technical');

        $this->actingAs($technical)
            ->get('/technical/bom')
            ->assertOk()
            ->assertSee('طباعة إذن الصرف', false)
            ->assertSee('printIssueVoucherLink', false);
    }

    public function test_operations_page_renders_workshop_print_label(): void
    {
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get('/operations/operations')
            ->assertOk()
            ->assertSee('operations-dashboard.js', false);
    }
}
