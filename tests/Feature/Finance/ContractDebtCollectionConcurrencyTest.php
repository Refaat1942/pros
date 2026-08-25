<?php

namespace Tests\Feature\Finance;

use App\Models\ContractCompanyDebt;
use App\Models\DebtCollectionEntry;
use App\Services\ContractDebtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * P1-01: civilian contract debt collection must not over-collect.
 *
 * SQLite in-memory cannot reproduce PostgreSQL/MySQL row-lock contention between
 * two live transactions. We verify the authoritative service invariant under
 * lockForUpdate and document that parallel-process verification on PG/MySQL
 * remains pending for CI environments with a real server driver.
 */
class ContractDebtCollectionConcurrencyTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    private function debtWithBalances(float $due, float $collected): ContractCompanyDebt
    {
        $company = $this->civilianCompany('شركة تحصيل');
        $debt = $company->debt()->firstOrFail();
        $debt->update([
            'due' => $due,
            'collected' => $collected,
        ]);

        return $debt->fresh();
    }

    public function test_over_collection_is_rejected(): void
    {
        $debt = $this->debtWithBalances(1000, 800);
        $company = $debt->contractCompany;
        $service = app(ContractDebtService::class);

        try {
            $service->recordPayment($company, 300);
            $this->fail('Payment above remaining must be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('أكبر من المتبقي', $e->getMessage());
        }

        $debt->refresh();
        $this->assertSame(800.0, (float) $debt->collected);
        $this->assertSame(200.0, round((float) $debt->due - (float) $debt->collected, 2));
        $this->assertSame(0, DebtCollectionEntry::where('payable_id', $debt->id)->count());
    }

    public function test_exact_remaining_amount_succeeds(): void
    {
        $debt = $this->debtWithBalances(1000, 800);
        $company = $debt->contractCompany;
        $service = app(ContractDebtService::class);

        $service->recordPayment($company, 200);

        $debt->refresh();
        $this->assertSame(1000.0, (float) $debt->collected);
        $this->assertSame(0.0, round((float) $debt->due - (float) $debt->collected, 2));
        $this->assertSame(1, DebtCollectionEntry::where('payable_id', $debt->id)->count());
    }

    public function test_second_full_collection_is_rejected_after_debt_is_fully_collected(): void
    {
        $debt = $this->debtWithBalances(1000, 0);
        $company = $debt->contractCompany;
        $service = app(ContractDebtService::class);

        $service->recordPayment($company, 1000);

        $debt->refresh();
        $this->assertSame(1000.0, (float) $debt->collected);

        try {
            $service->recordPayment($company, 1000);
            $this->fail('Second full collection must be rejected once remaining is zero.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('لا يوجد متبقٍ', $e->getMessage());
        }

        $debt->refresh();
        $this->assertSame(1000.0, (float) $debt->collected);
        $this->assertLessThanOrEqual((float) $debt->due, (float) $debt->collected);
        $this->assertSame(1, DebtCollectionEntry::where('payable_id', $debt->id)->count());
    }

    public function test_http_collect_rejects_over_remaining_via_service_guard(): void
    {
        $debt = $this->debtWithBalances(1000, 800);
        $company = $debt->contractCompany;
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 300])
            ->assertStatus(422);

        $debt->refresh();
        $this->assertSame(800.0, (float) $debt->collected);
    }

    public function test_http_second_full_collect_fails_after_first_succeeds(): void
    {
        $debt = $this->debtWithBalances(1000, 0);
        $company = $debt->contractCompany;
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 1000])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 1000])
            ->assertStatus(422);

        $debt->refresh();
        $this->assertSame(1000.0, (float) $debt->collected);
        $this->assertLessThanOrEqual((float) $debt->due, (float) $debt->collected);
    }
}
