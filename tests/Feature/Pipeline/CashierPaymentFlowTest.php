<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Payment;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CashierPaymentFlowTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_operations_issue_quote_routes_cash_patient_to_cashier(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->cashPatient());

        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->fresh()->stage_key);

        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson('/operations/pending/' . $case->id . '/release-quote')
            ->assertOk()
            ->assertJsonPath('case.stage_key', CaseRecord::STAGE_CASHIER);

        $this->assertSame(CaseRecord::STAGE_CASHIER, $case->fresh()->stage_key);
    }

    public function test_contracted_civilian_still_goes_to_reception_not_cashier(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->civilianPatient($this->civilianCompany()));
        $ops  = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson('/operations/pending/' . $case->id . '/release-quote')
            ->assertOk()
            ->assertJsonPath('case.stage_key', CaseRecord::STAGE_OPERATIONS);

        $this->assertSame(Quote::STATUS_ISSUED, Quote::where('case_id', $case->id)->value('status'));
    }

    public function test_cashier_payments_page_renders(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $this->cashierAwaitingCase();
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->get('/cashier/payments')
            ->assertOk()
            ->assertSee('id="cashierTableBody"', false)
            ->assertSee('cashierPaymentModal', false)
            ->assertSee('تأكيد استلام المبلغ', false);
    }

    public function test_cashier_statistics_page_renders(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->get('/cashier/statistics')
            ->assertOk()
            ->assertSee('التحصيل حسب وسيلة الدفع', false);
    }

    public function test_cashier_queue_lists_awaiting_payment_cases(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->cashierAwaitingCase();
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->getJson('/cashier/payments/list')
            ->assertOk()
            ->assertJsonPath('data.0.id', $case->id)
            ->assertJsonPath('total', 1);
    }

    public function test_cashier_confirm_payment_moves_case_to_warehouse_and_records_payment(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->cashierAwaitingCase();
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->postJson('/cashier/payments/' . $case->id . '/confirm', [
                'method' => 'instapay',
                'amount' => 1500,
                'reference' => 'IP-12345',
            ])
            ->assertOk()
            ->assertJsonPath('payment.method', 'instapay');

        $fresh = $case->fresh();
        $this->assertSame(CaseRecord::STAGE_MANUFACTURING, $fresh->stage_key);
        $this->assertSame(CaseRecord::MFG_WAREHOUSE, $fresh->manufacturing_stage);
        $this->assertNotNull($fresh->work_order_no);

        $payment = Payment::where('case_id', $case->id)->firstOrFail();
        $this->assertSame('instapay', $payment->method);
        $this->assertGreaterThan(0, (float) $payment->amount);
    }

    public function test_cashier_confirm_rejects_invalid_method(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->cashierAwaitingCase();
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->postJson('/cashier/payments/' . $case->id . '/confirm', [
                'method' => 'bitcoin',
            ])
            ->assertStatus(422);

        $this->assertSame(CaseRecord::STAGE_CASHIER, $case->fresh()->stage_key);
    }

    public function test_admin_cash_income_report_lists_collected_payments(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->cashierAwaitingCase();
        app(\App\Services\CashierPaymentService::class)->confirmPayment($case, [
            'method' => 'cash',
            'amount' => 800,
        ]);

        $report = app(\App\Services\AdminReportsHubService::class)->build(
            'cash-income',
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $this->assertSame('التحصيل النقدي — الخزنة', $report['title']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame([], $report['summary']);
    }

    /** يقود مريض كاش حتى مرحلة الخزنة (بانتظار الدفع). */
    private function cashierAwaitingCase(): CaseRecord
    {
        $case = $this->operationsReadyCase($this->cashPatient());
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        app(\App\Services\OperationsService::class)->sendToCashier($case->fresh(), $quote);

        return $case->fresh();
    }
}
