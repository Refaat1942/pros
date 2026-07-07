<?php

namespace Tests\Feature\Finance;

use App\Models\DebtCollectionEntry;
use App\Models\MilitaryDebt;
use App\Services\ContractDebtService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DebtCollectionHistoryTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_civilian_partial_collection_creates_multiple_entries(): void
    {
        $company = $this->civilianCompany('شركة التأمين');
        app(ContractDebtService::class)->increaseDue($company, 5000);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 2000])
            ->assertOk()
            ->assertJsonPath('debt.collection_summary.payment_count', 1)
            ->assertJsonPath('debt.collection_summary.mode', 'partial_once');

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 3000])
            ->assertOk()
            ->assertJsonPath('debt.collection_summary.payment_count', 2)
            ->assertJsonPath('debt.collection_summary.mode', 'full_multi')
            ->assertJsonPath('debt.collection_summary.mode_label', 'تحصيل كامل — 2 دفعات');

        $debt = $company->debt()->first();
        $this->assertEquals(2, DebtCollectionEntry::query()
            ->where('payable_type', 'contract_company_debt')
            ->where('payable_id', $debt->id)
            ->count());
    }

    public function test_civilian_collection_history_endpoint_returns_entries(): void
    {
        $company = $this->civilianCompany('جهة تاريخ');
        app(ContractDebtService::class)->increaseDue($company, 1000);
        app(ContractDebtService::class)->recordPayment($company, 400);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->getJson("/admin/civilian-debts/{$company->id}/collections")
            ->assertOk()
            ->assertJsonPath('collection_summary.payment_count', 1)
            ->assertJsonPath('collection_entries.0.amount', 400)
            ->assertJsonPath('collection_entries.0.remaining_after', 600);
    }

    public function test_military_multi_payment_history(): void
    {
        $debt = $this->makeMilitaryDebt(3000, 0);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 1000])
            ->assertOk()
            ->assertJsonPath('debt.collection_summary.mode', 'partial_once');

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 2000])
            ->assertOk()
            ->assertJsonPath('debt.collection_summary.payment_count', 2)
            ->assertJsonPath('debt.collection_summary.mode', 'full_multi');

        $this->actingAs($admin)
            ->getJson("/admin/military-debts/{$debt->id}/collections")
            ->assertOk()
            ->assertJsonCount(2, 'collection_entries');
    }

    private function makeMilitaryDebt(float $due, float $collected): MilitaryDebt
    {
        $company = $this->militaryCompany('جهة عسكرية');
        $patient = $this->militaryPatient($company);
        $case = $this->caseAtStage($patient, 'delivered');

        return MilitaryDebt::create([
            'case_id' => $case->id,
            'work_order_no' => 'WO-HIST-'.$case->id,
            'patient_name' => $patient->name,
            'patient_national_id' => $patient->national_id,
            'sovereign_entity' => 'القوات المسلحة',
            'total_cost' => $due,
            'collected' => $collected,
            'delivered_at' => now()->toDateString(),
            'status' => MilitaryDebt::STATUS_PENDING,
        ]);
    }
}
