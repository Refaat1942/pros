<?php

namespace Tests\Feature\Pipeline;

use App\Models\Quote;
use App\Services\Dashboard\DashboardQueueService;
use App\Services\QuoteService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OperationsQuotesAwaitingTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_issued_quotes_list_shows_discounted_display_total(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $company = $this->civilianCompany('التأمين الصحي');
        $company->update(['discount_percent' => 10]);

        $patient = $this->civilianPatient($company);
        $case = $this->operationsReadyCase($patient);
        $case->update(['contract_company_id' => $company->id, 'quote_total' => 2000]);

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $quote->update(['total' => 2000]);

        $ops = $this->userWithRole('operations');
        app(QuoteService::class)->releaseToReception($quote->fresh());

        $this->actingAs($ops)
            ->getJson('/operations/quotes-awaiting/list')
            ->assertOk()
            ->assertJsonPath('data.0.display_total', 1800)
            ->assertJsonPath('data.0.discount_percent', 10);
    }

    public function test_issued_quotes_appear_in_operations_awaiting_list(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        app(QuoteService::class)->releaseToReception($quote);

        $response = $this->actingAs($ops)
            ->getJson('/operations/quotes-awaiting/list')
            ->assertOk();

        $response->assertJsonPath('data.0.quote_no', $quote->fresh()->quote_no);
        $response->assertJsonPath('data.0.status', Quote::STATUS_ISSUED);
        $response->assertJsonPath('data.0.stage_label', 'بانتظار موافقة الجهة');
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
        $this->approveAtOperations($case);

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
            ->assertSee('عروض بانتظار الموافقة', false)
            ->assertSee('operations-quotes-awaiting-dashboard.js', false);
    }

    public function test_queue_service_counts_issued_quotes(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        app(QuoteService::class)->releaseToReception($quote);

        $service = app(DashboardQueueService::class);

        $this->assertSame(1, $service->operationsIssuedQuotesCount());
        $this->assertContains($quote->id, $service->operationsIssuedQuoteIds());
    }
}
