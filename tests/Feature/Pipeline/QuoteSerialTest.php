<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Quote;
use App\Services\BomService;
use App\Services\StockPriceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class QuoteSerialTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_quote_no_is_unique_in_database(): void
    {
        $indexes = Schema::getIndexes('quotes');

        $this->assertTrue(
            collect($indexes)->contains(
                fn (array $index) => ($index['unique'] ?? false)
                    && in_array('quote_no', $index['columns'] ?? [], true)
            )
        );
    }

    public function test_quote_serial_accessor_matches_quote_no(): void
    {
        $case = $this->caseWaitingAtCosting();
        $quote = $this->confirmCostingAndIssueQuote($case);

        $this->assertSame($quote->quote_no, $quote->quote_serial);
        $this->assertMatchesRegularExpression('/^QT-\d{4}-\d{4}$/', $quote->quote_no);
    }

    public function test_reception_quote_api_exposes_quote_serial(): void
    {
        $case = $this->caseWaitingAtCosting();
        $quote = $this->confirmCostingAndIssueQuote($case);

        $this->actingAs($this->userWithRole('operations'))
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk();

        $this->actingAs($this->userWithRole('reception'))
            ->getJson('/reception/quote/list')
            ->assertOk()
            ->assertJsonPath('data.0.quote_serial', $quote->quote_no)
            ->assertJsonPath('data.0.quote_serial_label', Quote::SERIAL_LABEL);
    }

    public function test_duplicate_quote_no_is_rejected(): void
    {
        $case = $this->caseWaitingAtCosting();
        $quote = $this->confirmCostingAndIssueQuote($case);

        $this->expectException(UniqueConstraintViolationException::class);

        Quote::create([
            'quote_no' => $quote->quote_no,
            'order_ref' => $quote->order_ref,
            'case_id' => $case->id,
            'pricing_request_id' => null,
            'patient_name' => 'مريض',
            'company_name' => 'جهة',
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_PENDING,
            'total' => 1,
        ]);
    }

    private function caseWaitingAtCosting(): CaseRecord
    {
        $item = $this->stockItem('RM-001', qty: 20);
        app(StockPriceService::class)->addBatch(
            $item, 10, 200.00, $this->makeSupplier(), 'INV-QS', now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/complete")
            ->assertOk();

        return $case->fresh();
    }

    private function confirmCostingAndIssueQuote(CaseRecord $case): Quote
    {
        $this->actingAs($this->userWithRole('costing'))
            ->postJson("/costing/queue/{$case->id}/confirm")
            ->assertOk();

        return Quote::where('case_id', $case->id)->firstOrFail();
    }
}
