<?php

namespace Tests\Feature\Stock;

use App\Services\StockCatalogService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminCatalogDateFilterTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_catalog_page_filters_items_by_created_at_range(): void
    {
        $admin = $this->userWithRole('admin');

        $old = $this->stockItem('OLD-CAT');
        $old->forceFill(['created_at' => now()->subMonths(3)->startOfDay()])->save();

        $recent = $this->stockItem('NEW-CAT');
        $recent->forceFill(['created_at' => now()->subDays(2)])->save();

        $from = now()->subWeek()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin)
            ->get('/admin/catalog?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('reports-date-filter', false)
            ->assertSee('NEW-CAT', false)
            ->assertDontSee('OLD-CAT', false);
    }

    public function test_catalog_api_and_export_honor_date_range(): void
    {
        $admin = $this->userWithRole('admin');

        $old = $this->stockItem('API-OLD');
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $recent = $this->stockItem('API-NEW');
        $recent->forceFill(['created_at' => now()])->save();

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAs($admin)
            ->getJson('/admin/catalog/items?from=' . $from . '&to=' . $to);

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('API-NEW', $codes);
        $this->assertNotContains('API-OLD', $codes);

        $this->actingAs($admin)
            ->get('/admin/catalog/export?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_parse_date_range_swaps_inverted_bounds(): void
    {
        $service = app(StockCatalogService::class);

        $range = $service->parseDateRange('2026-06-20', '2026-06-01');

        $this->assertSame('2026-06-01', $range['from']->toDateString());
        $this->assertSame('2026-06-20', $range['to']->toDateString());
    }

    public function test_list_for_dashboard_without_dates_returns_all_items(): void
    {
        $this->stockItem('ALL-1');
        $this->stockItem('ALL-2');

        $items = app(StockCatalogService::class)->listForDashboard();

        $this->assertGreaterThanOrEqual(2, $items->count());
        $this->assertTrue($items->pluck('code')->contains('ALL-1'));
        $this->assertTrue($items->pluck('code')->contains('ALL-2'));
    }
}
