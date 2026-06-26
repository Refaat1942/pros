<?php

namespace Tests\Feature\Finance;

use App\Models\MilitaryDebt;
use App\Models\Patient;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminMilitaryDebtsPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_military_debts_page_shows_due_collected_remaining_columns(): void
    {
        $company = $this->militaryCompany('القوات المسلحة');
        $patient = $this->militaryPatient($company);

        MilitaryDebt::create([
            'case_id'             => $this->caseAtStage($patient, 'delivered')->id,
            'work_order_no'       => 'WO-TEST-001',
            'patient_name'        => $patient->name,
            'patient_national_id' => $patient->national_id,
            'sovereign_entity'    => 'القوات المسلحة',
            'total_cost'          => 3000,
            'collected'           => 0,
            'delivered_at'        => now()->toDateString(),
            'status'              => MilitaryDebt::STATUS_PENDING,
        ]);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/military-debts')
            ->assertOk()
            ->assertSee('مديونيات الجهات العسكرية')
            ->assertSee('المستحق (ج.م)')
            ->assertSee('المحصّل (ج.م)')
            ->assertSee('المتبقي (ج.م)')
            ->assertSee('المبلغ المحوّل')
            ->assertSee('WO-TEST-001');
    }

    public function test_admin_can_record_partial_military_debt_collection(): void
    {
        $debt = $this->makeMilitaryDebt(5000, 0);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 2000])
            ->assertOk()
            ->assertJsonPath('debt.status', MilitaryDebt::STATUS_PARTIAL)
            ->assertJsonPath('debt.collected', 2000)
            ->assertJsonPath('debt.remaining', 3000);

        $fresh = $debt->fresh();
        $this->assertEquals(2000.00, (float) $fresh->collected);
        $this->assertEquals(MilitaryDebt::STATUS_PARTIAL, $fresh->status);
    }

    public function test_admin_can_record_full_military_debt_collection(): void
    {
        $debt = $this->makeMilitaryDebt(3000, 1000);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 2000])
            ->assertOk()
            ->assertJsonPath('debt.status', MilitaryDebt::STATUS_COLLECTED)
            ->assertJsonPath('debt.remaining', 0)
            ->assertJsonPath('debt.status_label', 'تم التحصيل');

        $fresh = $debt->fresh();
        $this->assertEquals(3000.00, (float) $fresh->collected);
        $this->assertEquals(MilitaryDebt::STATUS_COLLECTED, $fresh->status);
        $this->assertNotNull($fresh->collected_at);
    }

    public function test_collect_rejects_amount_above_remaining(): void
    {
        $debt = $this->makeMilitaryDebt(500, 200);

        $this->actingAs($this->userWithRole('admin'))
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 400])
            ->assertStatus(422);
    }

    public function test_frozen_military_debt_cannot_collect_again(): void
    {
        $debt = $this->makeMilitaryDebt(1000, 1000, MilitaryDebt::STATUS_COLLECTED);

        $this->actingAs($this->userWithRole('admin'))
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 100])
            ->assertStatus(422);
    }

    private function makeMilitaryDebt(float $due, float $collected, string $status = MilitaryDebt::STATUS_PENDING): MilitaryDebt
    {
        $company = $this->militaryCompany('جهة عسكرية');
        $patient = $this->militaryPatient($company);
        $case    = $this->caseAtStage($patient, 'delivered');

        return MilitaryDebt::create([
            'case_id'             => $case->id,
            'work_order_no'       => 'WO-' . $case->id,
            'patient_name'        => $patient->name,
            'patient_national_id' => $patient->national_id,
            'sovereign_entity'    => 'القوات المسلحة',
            'total_cost'          => $due,
            'collected'           => $collected,
            'delivered_at'        => now()->toDateString(),
            'status'              => $status,
            'collected_at'        => $status === MilitaryDebt::STATUS_COLLECTED ? now() : null,
        ]);
    }
}
