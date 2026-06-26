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
            'name'         => 'جهة أ',
            'is_military'  => false,
        ]);
        $b = ContractCompany::create([
            'company_code' => 'CIV-B',
            'name'         => 'جهة ب',
            'is_military'  => false,
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
            ->getJson('/admin/civilian-debts/list?status=' . DebtStatus::Paid->value)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.company.name', 'جهة ب');
    }
}
