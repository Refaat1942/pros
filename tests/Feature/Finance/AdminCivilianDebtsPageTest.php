<?php

namespace Tests\Feature\Finance;

use App\Enums\DebtStatus;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Services\ContractDebtService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminCivilianDebtsPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_civilian_debts_page_lists_civilian_companies_only(): void
    {
        $civilian = $this->civilianCompany('شركة مدنية للمديونية');
        $military = $this->militaryCompany('جهة عسكرية');

        app(ContractDebtService::class)->increaseDue($civilian, 5000.00);
        $military->debt()->first()?->update(['due' => 9000, 'status' => DebtStatus::Pending->value]);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/civilian-debts')
            ->assertOk()
            ->assertSee('مديونيات مدنية')
            ->assertSee('شركة مدنية للمديونية')
            ->assertDontSee('جهة عسكرية');

        $this->actingAs($admin)
            ->getJson('/admin/civilian-debts/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.company.name', 'شركة مدنية للمديونية')
            ->assertJsonPath('data.0.remaining', 5000);
    }

    public function test_civilian_debts_list_filters_by_status_and_balance(): void
    {
        $a = ContractCompany::create([
            'company_code' => 'CIV-A',
            'name' => 'جهة أ',
            'is_military' => false,
        ]);
        $b = ContractCompany::create([
            'company_code' => 'CIV-B',
            'name' => 'جهة ب',
            'is_military' => false,
        ]);

        ContractCompanyDebt::create([
            'contract_company_id' => $a->id, 'due' => 1000, 'collected' => 0, 'status' => DebtStatus::Pending->value,
        ]);
        ContractCompanyDebt::create([
            'contract_company_id' => $b->id, 'due' => 2000, 'collected' => 2000, 'status' => DebtStatus::Paid->value,
        ]);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->getJson('/admin/civilian-debts/list?balance=outstanding')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.company.name', 'جهة أ');

        $this->actingAs($admin)
            ->getJson('/admin/civilian-debts/list?status='.DebtStatus::Paid->value)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.company.name', 'جهة ب');
    }

    public function test_admin_can_record_full_civilian_debt_collection(): void
    {
        $company = $this->civilianCompany('شركة مصر للتأمين');
        app(ContractDebtService::class)->increaseDue($company, 1000.00);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 1000])
            ->assertOk()
            ->assertJsonPath('debt.status', DebtStatus::Paid->value)
            ->assertJsonPath('debt.status_label', 'تم التحصيل')
            ->assertJsonPath('debt.remaining', 0);

        $this->assertEquals(1000.00, (float) $company->debt()->first()->fresh()->collected);

        $this->assertDatabaseHas('contract_company_debts', [
            'contract_company_id' => $company->id,
            'collected' => 1000,
            'status' => DebtStatus::Paid->value,
        ]);
    }

    public function test_collect_rejects_amount_above_remaining(): void
    {
        $company = $this->civilianCompany('جهة متبقي');
        $company->debt()->first()->update(['due' => 500, 'collected' => 200, 'status' => DebtStatus::Partial->value]);

        $this->actingAs($this->userWithRole('admin'))
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 400])
            ->assertStatus(422);
    }

    public function test_legacy_civilian_collected_without_entries_shows_collection_summary(): void
    {
        $company = $this->civilianCompany('شركة ترحيل قديم');
        ContractCompanyDebt::query()->updateOrCreate(
            ['contract_company_id' => $company->id],
            ['due' => 1500, 'collected' => 1500, 'status' => DebtStatus::Paid->value],
        );

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/civilian-debts')
            ->assertOk()
            ->assertSee('تحصيل كامل — دفعة واحدة', false)
            ->assertDontSee('لم يُحصَّل بعد', false);

        $this->actingAs($admin)
            ->getJson('/admin/civilian-debts/list')
            ->assertOk()
            ->assertJsonPath('data.0.collection_summary.mode', 'full_once')
            ->assertJsonPath('data.0.collection_summary.mode_label', 'تحصيل كامل — دفعة واحدة')
            ->assertJsonCount(1, 'data.0.collection_entries');
    }

    public function test_civilian_debts_page_shows_collect_input_for_outstanding_balance(): void
    {
        $company = $this->civilianCompany('جهة متبقي تحصيل');
        app(ContractDebtService::class)->increaseDue($company, 2500);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/civilian-debts')
            ->assertOk()
            ->assertSee('المبلغ المحوّل', false)
            ->assertSee('تم التحصيل', false)
            ->assertSee('جهة متبقي تحصيل', false);
    }
}
