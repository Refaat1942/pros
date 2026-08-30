<?php

namespace Tests\Feature\Authorization;

use App\Models\CaseRecord;
use App\Models\MilitaryDebt;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * P2-AUTH-02 — legacy PATCH status route removed; live collect path unchanged.
 */
class MilitaryDebtLegacyStatusRouteTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_legacy_status_patch_returns_not_found_without_mutation(): void
    {
        $debt = $this->makePartialMilitaryDebt(totalCost: 1000, collected: 400);
        $before = $debt->only(['status', 'collected', 'collected_at']);

        app(PermissionCatalogService::class)->syncToDatabase();
        $admin = $this->limitedAdminWithPages([
            'admin.military-debts.view',
            'collect-military-debt',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/admin/military-debts/{$debt->id}/status", [
                'status' => MilitaryDebt::STATUS_PENDING,
            ]);

        $this->assertContains($response->status(), [404, 405], 'Legacy PATCH must not be routable');
        $this->assertFalse($response->isSuccessful());

        $fresh = $debt->fresh();
        $this->assertSame($before['status'], $fresh->status);
        $this->assertEqualsWithDelta((float) $before['collected'], (float) $fresh->collected, 0.001);
        $this->assertEquals($before['collected_at'], $fresh->collected_at);
    }

    public function test_live_collect_route_remains_functional_for_authorized_user(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $debt = $this->makePartialMilitaryDebt(totalCost: 1500, collected: 0);
        $admin = $this->limitedAdminWithPages([
            'admin.military-debts.view',
            'collect-military-debt',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 500])
            ->assertOk()
            ->assertJsonPath('debt.status', MilitaryDebt::STATUS_PARTIAL)
            ->assertJsonPath('debt.collected', 500);

        $this->assertEqualsWithDelta(500.0, (float) $debt->fresh()->collected, 0.001);
    }

    public function test_live_military_debt_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('admin.military-debts.collect'));
        $this->assertTrue(Route::has('admin.military-debts.collections'));
        $this->assertTrue(Route::has('admin.military-debts.destroy'));
        $this->assertFalse(Route::has('admin.military-debts.status'));
    }

    private function makePartialMilitaryDebt(float $totalCost, float $collected): MilitaryDebt
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);

        $status = $collected > 0
            ? MilitaryDebt::STATUS_PARTIAL
            : MilitaryDebt::STATUS_PENDING;

        return MilitaryDebt::create([
            'case_id' => $case->id,
            'work_order_no' => 'WO-LEG-'.uniqid(),
            'patient_name' => $patient->name,
            'sovereign_entity' => 'القوات المسلحة',
            'total_cost' => $totalCost,
            'collected' => $collected,
            'delivered_at' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    /** @param list<string> $permissionSlugs */
    private function limitedAdminWithPages(array $permissionSlugs): User
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $role = Role::firstOrCreate(
            ['slug' => 'limited_admin_mil_legacy_test'],
            ['label_ar' => 'أدمن اختبار مسار عسكري legacy'],
        );

        $ids = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);

        return User::query()->updateOrCreate(
            ['username' => 'limited_admin_mil_legacy_test'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن اختبار مسار عسكري',
            ],
        );
    }
}
