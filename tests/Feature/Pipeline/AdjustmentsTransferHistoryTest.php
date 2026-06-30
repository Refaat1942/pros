<?php

namespace Tests\Feature\Pipeline;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdjustmentsTransferHistoryTest extends TestCase
{
    use ProstheticTestHelper;

    private function completeAdjustmentsCase(): CaseRecord
    {
        $item = $this->stockItem('RM-HIST', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-HIST', now());

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-HIST', 'qty' => 1],
        ]);

        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/complete")
            ->assertOk();

        return $case->fresh(['patient']);
    }

    public function test_history_page_lists_transferred_cases(): void
    {
        $case = $this->completeAdjustmentsCase();
        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->get('/adjustments/adjustments')
            ->assertOk()
            ->assertSee('سجل المحوّلين للتكاليف', false)
            ->assertSee($case->patient->name, false)
            ->assertSee($case->case_no, false);

        $this->actingAs($user)
            ->get('/adjustments/history')
            ->assertRedirect();
    }

    public function test_history_api_filters_by_patient_name(): void
    {
        $case = $this->completeAdjustmentsCase();
        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->getJson('/adjustments/history/list?search=' . urlencode($case->patient->name))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.case_no', $case->case_no);

        $this->actingAs($user)
            ->getJson('/adjustments/history/list?search=غير_موجود_تماماً')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_history_export_returns_csv(): void
    {
        $case = $this->completeAdjustmentsCase();
        $user = $this->userWithRole('adjustments');

        $response = $this->actingAs($user)
            ->get('/adjustments/history/export?from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString());

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('سجل المحوّلين من المعدلات للتكاليف', $content);
        $this->assertStringContainsString($case->case_no, $content);
    }

    public function test_history_is_backed_by_pricing_receive_audit_log(): void
    {
        $case = $this->completeAdjustmentsCase();

        $this->assertTrue(
            AuditLog::query()
                ->where('tag', 'pricing')
                ->where('action', 'receive')
                ->where('description', 'like', 'استلام التكاليف%')
                ->where('payload_after->case_id', $case->id)
                ->exists()
        );
    }
}
