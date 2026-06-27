<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use App\Services\WorkshopAnalyticsService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class WorkshopStatisticsPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_statistics_page_loads_for_workshop_user(): void
    {
        $user = $this->userWithRole('workshop');
        $this->actingAs($user);

        $this->get(route('workshop.statistics'))
            ->assertOk()
            ->assertSee('لوحة إحصائيات ورشة التصنيع')
            ->assertSee('workshopStatsRoot', false)
            ->assertSee('workshopCompletedTable', false);
    }

    public function test_statistics_page_blocked_without_permission(): void
    {
        $user = $this->userWithRole('workshop');
        $user->role->permissions()->detach();
        $this->actingAs($user->fresh());

        $this->get(route('workshop.statistics'))->assertStatus(403);
    }

    public function test_analytics_service_counts_finished_manufacturing(): void
    {
        $item = $this->stockItem('RM-STAT', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 200.0, $this->makeSupplier(), 'INV-STAT', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('workshop');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-STAT-01']);
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-STAT', 'qty' => 1]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-STAT']);
        $this->postJson("/workshop/workshop/{$case->id}/finish-quality")->assertOk();

        $data = app(WorkshopAnalyticsService::class)->build();

        $this->assertSame('1', $data['stats'][0]['value']);
        $this->assertSame('1', $data['stats'][1]['value']);
        $this->assertSame(Bom::STAGE_FINISHED, Bom::first()->stage);
        $this->assertCount(1, $data['reports']['completed_rows']);
    }
}
