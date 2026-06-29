<?php

namespace Tests\Feature\Stock;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\PricingRequest;
use App\Models\PricingRequestItem;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Services\StockItemSalesStatsService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class StockItemSalesStatsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_breakdown_counts_sales_per_price_tier_for_delivered_cases(): void
    {
        $item = $this->stockItem('RM-SALES', qty: 50, wac: 10);
        $item->update(['price' => 10]);
        StockItemPrice::create([
            'stock_item_id' => $item->id,
            'price_ref'     => 'PR-RM-SALES-1',
            'amount'        => 20,
            'qty'           => 1,
        ]);
        StockItemPrice::create([
            'stock_item_id' => $item->id,
            'price_ref'     => 'PR-RM-SALES-2',
            'amount'        => 30,
            'qty'           => 1,
        ]);

        $patient = $this->civilianPatient($this->civilianCompany());

        $caseAt30 = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $caseAt30->update(['delivered_at' => now()->subDays(2)]);
        $this->attachPricingLine($caseAt30, 'RM-SALES', 30, 1);

        $caseAt20 = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $caseAt20->update(['delivered_at' => now()->subDay()]);
        $this->attachPricingLine($caseAt20, 'RM-SALES', 20, 2);

        $pending = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);
        $this->attachPricingLine($pending, 'RM-SALES', 30, 5);

        $service  = app(StockItemSalesStatsService::class);
        $breakdown = $service->breakdownForItem($item->fresh('prices'));

        $this->assertSame(2, $breakdown['total_sale_times']);
        $this->assertSame(3, $breakdown['total_sold_qty']);

        $byPrice = collect($breakdown['rows'])->keyBy(fn (array $row) => number_format($row['unit_price'], 2, '.', ''));

        $this->assertSame(0, $byPrice->get('10.00')['sale_times']);
        $this->assertSame(0, $byPrice->get('10.00')['sold_qty']);
        $this->assertTrue($byPrice->get('10.00')['registered']);

        $this->assertSame(1, $byPrice->get('20.00')['sale_times']);
        $this->assertSame(2, $byPrice->get('20.00')['sold_qty']);

        $this->assertSame(1, $byPrice->get('30.00')['sale_times']);
        $this->assertSame(1, $byPrice->get('30.00')['sold_qty']);
    }

    public function test_admin_can_fetch_sales_stats_api(): void
    {
        $admin = $this->userWithRole('admin');
        $item  = $this->stockItem('RM-API', qty: 10, wac: 15);
        $item->update(['price' => 15]);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['delivered_at' => now()]);
        $this->attachPricingLine($case, 'RM-API', 15, 3);

        $this->actingAs($admin)
            ->getJson(route('admin.catalog.sales-stats', $item))
            ->assertOk()
            ->assertJsonPath('item_code', 'RM-API')
            ->assertJsonPath('total_sale_times', 1)
            ->assertJsonPath('total_sold_qty', 3)
            ->assertJsonPath('rows.0.unit_price', 15)
            ->assertJsonPath('rows.0.sale_times', 1);
    }

    public function test_admin_can_export_sales_by_price_csv(): void
    {
        $admin = $this->userWithRole('admin');
        $item  = $this->stockItem('RM-CSV', qty: 10, wac: 25);
        $item->update(['price' => 25]);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['delivered_at' => now()]);
        $this->attachPricingLine($case, 'RM-CSV', 25, 1);

        $response = $this->actingAs($admin)
            ->get(route('admin.catalog.sales-by-price.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('RM-CSV', $body);
        $this->assertStringContainsString('25.00', $body);
    }

    private function attachPricingLine(CaseRecord $case, string $code, float $unitPrice, int $qty): PricingRequest
    {
        static $seq = 0;
        $seq++;

        $req = PricingRequest::create([
            'request_no'   => "PR-STATS-{$seq}",
            'case_id'      => $case->id,
            'patient_type' => $case->patient_type ?? 'civilian',
            'order_ref'    => $case->order_ref,
            'patient_name' => 'مريض',
            'request_date' => now()->toDateString(),
            'status_key'   => 'awaiting_admin_approval',
        ]);

        PricingRequestItem::create([
            'pricing_request_id' => $req->id,
            'stock_item_code'    => $code,
            'name'               => "صنف {$code}",
            'qty'                => $qty,
            'unit_price'         => $unitPrice,
            'line_total'         => $unitPrice * $qty,
        ]);

        $case->update(['pricing_request_id' => $req->id]);

        return $req;
    }
}
