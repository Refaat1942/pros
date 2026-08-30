<?php

namespace Tests\Feature\Authorization;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\DebtCollectionEntry;
use App\Models\MilitaryDebt;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractDebtService;
use App\Services\PermissionCatalogService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DebtCollectionAuthorizationTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Civilian debt ───────────────────────────────────────────────────────

    public function test_civilian_debt_collect_denied_without_collect_action_permission(): void
    {
        $company = $this->civilianCompany('شركة تحصيل محدود');
        app(ContractDebtService::class)->increaseDue($company, 1000.00);
        $debt = $company->debt()->firstOrFail();
        $financialAuditsBefore = AuditLog::query()->where('tag', 'financial')->count();

        $admin = $this->limitedAdminWithPages(['admin.civilian-debts.view']);

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 500])
            ->assertStatus(403);

        $debt->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $debt->collected, 0.001);
        $this->assertSame(0, DebtCollectionEntry::query()->count());
        $this->assertSame(
            $financialAuditsBefore,
            AuditLog::query()->where('tag', 'financial')->count(),
        );
    }

    public function test_civilian_debt_collect_allowed_with_collect_action_permission(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $company = $this->civilianCompany('شركة تحصيل مصرح');
        app(ContractDebtService::class)->increaseDue($company, 800.00);

        $admin = $this->limitedAdminWithPages([
            'admin.civilian-debts.view',
            'collect-civilian-debt',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 800])
            ->assertOk();

        $this->assertEqualsWithDelta(800.0, (float) $company->debt()->first()->fresh()->collected, 0.001);
    }

    // ── Military debt ───────────────────────────────────────────────────────

    public function test_military_debt_collect_denied_without_collect_action_permission(): void
    {
        $debt = $this->makeMilitaryDebtRecord(totalCost: 2000);
        $financialAuditsBefore = AuditLog::query()->where('tag', 'financial')->count();

        $admin = $this->limitedAdminWithPages(['admin.military-debts.view']);

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 1000])
            ->assertStatus(403);

        $debt->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $debt->collected, 0.001);
        $this->assertSame(0, DebtCollectionEntry::query()->count());
        $this->assertSame(
            $financialAuditsBefore,
            AuditLog::query()->where('tag', 'financial')->count(),
        );
    }

    public function test_military_debt_collect_allowed_with_collect_action_permission(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $debt = $this->makeMilitaryDebtRecord(totalCost: 1500);

        $admin = $this->limitedAdminWithPages([
            'admin.military-debts.view',
            'collect-military-debt',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 1500])
            ->assertOk();

        $this->assertEqualsWithDelta(1500.0, (float) $debt->fresh()->collected, 0.001);
    }

    // ── Cross-permission separation ─────────────────────────────────────────

    public function test_civilian_collect_permission_does_not_grant_military_collection(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $debt = $this->makeMilitaryDebtRecord(totalCost: 1000);

        $admin = $this->limitedAdminWithPages([
            'admin.military-debts.view',
            'collect-civilian-debt',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 500])
            ->assertStatus(403);

        $this->assertEqualsWithDelta(0.0, (float) $debt->fresh()->collected, 0.001);
    }

    public function test_military_collect_permission_does_not_grant_civilian_collection(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $company = $this->civilianCompany('شركة فصل صلاحيات');
        app(ContractDebtService::class)->increaseDue($company, 600.00);

        $admin = $this->limitedAdminWithPages([
            'admin.civilian-debts.view',
            'collect-military-debt',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 300])
            ->assertStatus(403);

        $this->assertEqualsWithDelta(0.0, (float) $company->debt()->first()->fresh()->collected, 0.001);
    }

    public function test_view_only_admin_cannot_collect_civilian_or_military_debt(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $company = $this->civilianCompany('شركة عرض فقط');
        app(ContractDebtService::class)->increaseDue($company, 400.00);
        $debt = $this->makeMilitaryDebtRecord(totalCost: 900);

        $admin = $this->limitedAdminWithPages([
            'admin.civilian-debts.view',
            'admin.military-debts.view',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/civilian-debts/{$company->id}/collect", ['amount' => 200])
            ->assertStatus(403);

        $this->actingAs($admin)
            ->postJson("/admin/military-debts/{$debt->id}/collect", ['amount' => 100])
            ->assertStatus(403);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeMilitaryDebtRecord(float $totalCost = 1000): MilitaryDebt
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);

        return MilitaryDebt::create([
            'case_id' => $case->id,
            'work_order_no' => 'WO-DC-AUTH-'.uniqid(),
            'patient_name' => $patient->name,
            'sovereign_entity' => 'القوات المسلحة',
            'total_cost' => $totalCost,
            'collected' => 0,
            'delivered_at' => now()->toDateString(),
            'status' => MilitaryDebt::STATUS_PENDING,
        ]);
    }

    /** @param list<string> $permissionSlugs */
    private function limitedAdminWithPages(array $permissionSlugs): User
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $role = Role::firstOrCreate(
            ['slug' => 'limited_admin_debt_test'],
            ['label_ar' => 'أدمن محدود لاختبار المديونيات'],
        );

        $ids = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);

        return User::query()->updateOrCreate(
            ['username' => 'limited_admin_debt_test'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن محدود مديونيات',
            ],
        );
    }
}
