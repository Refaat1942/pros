<?php

namespace Tests\Feature\Admin;

use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Regression guards for Offline/LAN catalog performance fixes —
 * Add Item must work before heavy init; picker DOM cap must not shrink search space.
 */
class CatalogOfflinePerformanceTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_catalog_exposes_immediate_add_item_before_deferred_init(): void
    {
        $admin = $this->userWithRole('admin');

        $html = $this->actingAs($admin)
            ->get('/admin/catalog')
            ->assertOk()
            ->getContent();

        $openPos = strpos($html, 'window.openSlimCatalogForm = function');
        $initPos = strpos($html, 'function initCatalogPage');

        $this->assertNotFalse($openPos, 'openSlimCatalogForm must be defined in page script');
        $this->assertNotFalse($initPos, 'initCatalogPage must exist');
        $this->assertLessThan($initPos, $openPos, 'Add Item handler must be registered before deferred catalog init');

        $this->assertStringContainsString('requestIdleCallback', $html);
        $this->assertStringContainsString('data-no-dash-table-search="1"', $html);
        $this->assertStringContainsString('scheduleApplySlimCatalogFilters', $html);
        $this->assertStringContainsString('applySlimCatalogFilters', $html);
        $this->assertStringContainsString('populateBrandFilter', $html);
        $this->assertStringContainsString('sortCatalogRows', $html);
        $this->assertStringContainsString('catalogImportForm', $html);
        $this->assertStringContainsString('catalog-slim-table', $html);
    }

    public function test_table_sort_filter_skips_catalog_slim_table(): void
    {
        $js = file_get_contents(public_path('assets/js/shared/table-sort-filter.js'));

        $this->assertStringContainsString('catalogSlimSearch', $js);
        $this->assertStringContainsString('shouldSkipTable', $js);
        $this->assertStringContainsString('.catalog-slim-table', $js);
    }

    public function test_adjustments_picker_filters_full_cache_before_dom_cap(): void
    {
        $js = file_get_contents(public_path('assets/js/pages/adjustments-dashboard.js'));

        $this->assertStringContainsString('PICKER_RENDER_MAX', $js);

        $renderPos = strpos($js, 'function renderItemPickerList');
        $this->assertNotFalse($renderPos);

        $slicePos = strpos($js, 'items.slice(0, PICKER_RENDER_MAX)', $renderPos);
        $this->assertNotFalse($slicePos, 'DOM render cap must slice filtered items');

        $renderBody = substr($js, $renderPos, $slicePos - $renderPos);
        $this->assertStringContainsString('catalogCache.filter', $renderBody);
        $this->assertStringNotContainsString('catalogCache.slice', $renderBody);
    }

    public function test_catalog_open_add_item_query_still_works(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/catalog?open=add-item')
            ->assertOk()
            ->assertSee('openSlimCatalogForm', false);
    }
}
