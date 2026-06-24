<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Quote;
use App\Services\OperationsService;
use App\Services\QuoteService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OperationsQuotesAwaitingTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_issued_quotes_appear_in_operations_awaiting_list(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        app(QuoteService::class)->releaseToReception($quote);
        app(OperationsService::class)->approve($case->fresh(), 'اختبار');

        $response = $this->actingAs($ops)
            ->getJson('/operations/quotes-awaiting/list')
            ->assertOk();

        $response->assertJsonPath('data.0.quote_no', $quote->fresh()->quote_no);
        $response->assertJsonPath('data.0.status', Quote::STATUS_ISSUED);
        $response->assertJsonPath('data.0.stage_label', 'بالمخزن — بانتظار موافقة الجهة');
    }

    public function test_pending_internal_quotes_not_in_awaiting_list(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->getJson('/operations/quotes-awaiting/list')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_approved_quotes_removed_from_awaiting_list(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        app(QuoteService::class)->releaseToReception($quote);
        app(OperationsService::class)->approve($case->fresh(), 'اختبار');

        $quote->update(['status' => Quote::STATUS_APPROVED, 'status_label' => 'معتمد']);

        $this->actingAs($ops)
            ->getJson('/operations/quotes-awaiting/list')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_quotes_awaiting_page_renders_in_operations_dashboard(): void
    {
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get('/operations/quotes-awaiting')
            ->assertOk()
            ->assertSee('عروض الأسعار — بانتظار موافقة الجهة', false)
            ->assertSee('operations-quotes-awaiting-dashboard.js', false);
    }

    public function test_queue_service_counts_issued_quotes(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        app(QuoteService::class)->releaseToReception($quote);
        app(OperationsService::class)->approve($case->fresh(), 'اختبار');

        $service = app(\App\Services\Dashboard\DashboardQueueService::class);

        $this->assertSame(1, $service->operationsIssuedQuotesCount());
        $this->assertContains($quote->id, $service->operationsIssuedQuoteIds());
    }
}
