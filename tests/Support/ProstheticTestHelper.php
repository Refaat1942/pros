<?php

namespace Tests\Support;

use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VisitType;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

/**
 * Shared helpers for building test fixtures from scratch (no seeders).
 */
trait ProstheticTestHelper
{
    // ── User helpers ─────────────────────────────────────────────────────────

    protected function makeRole(string $slug): Role
    {
        $labels = [
            'admin'       => 'مسؤول النظام',
            'reception'   => 'موظف استقبال',
            'doctor'      => 'طبيب',
            'spec'        => 'فني مواصفات',
            'adjustments' => 'فني تعديلات',
            'costing'     => 'فني تكاليف',
            'operations'  => 'مكتب عمليات',
            'technical'   => 'مسؤول مخزن',
        ];

        return Role::firstOrCreate(['slug' => $slug], ['label_ar' => $labels[$slug] ?? $slug]);
    }

    protected function userWithRole(string $slug): User
    {
        $role = $this->makeRole($slug);
        $this->seedDefaultPermissions($role);

        return User::factory()->create([
            'role_id'  => $role->id,
            'email'    => "{$slug}@test.local",
            'password' => Hash::make('password'),
            'status'   => User::STATUS_ACTIVE,
        ]);
    }

    private function seedDefaultPermissions(Role $role): void
    {
        app(\App\Services\PermissionCatalogService::class)->syncToDatabase();

        if ($role->slug === Role::SLUG_ADMIN) {
            $viewIds = Permission::query()
                ->where('type', Permission::TYPE_VIEW)
                ->where('dashboard', '!=', Role::SLUG_ADMIN)
                ->pluck('id');
            $role->permissions()->syncWithoutDetaching($viewIds);

            return;
        }

        $viewIds = Permission::query()
            ->where('type', Permission::TYPE_VIEW)
            ->where('dashboard', $role->slug)
            ->pluck('id');

        $role->permissions()->syncWithoutDetaching($viewIds);

        $defaults = [
            Role::SLUG_COSTING     => ['view-costs'],
            Role::SLUG_OPERATIONS  => ['approve-pricing', 'view-costs', 'print-quote'],
            Role::SLUG_DOCTOR      => ['skip-diagnosis'],
            Role::SLUG_TECHNICAL   => ['manage-inventory', 'import-inventory', 'print-barcode', 'view-inventory-overview'],
            Role::SLUG_RECEPTION   => ['print-quote'],
        ];

        $slugs = $defaults[$role->slug] ?? [];
        if ($slugs !== []) {
            $ids = Permission::whereIn('slug', $slugs)->pluck('id');
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }

    // ── ContractCompany helpers ───────────────────────────────────────────────

    protected function civilianCompany(string $name = 'التأمين الصحي'): ContractCompany
    {
        $company = ContractCompany::create([
            'company_code' => 'CO-001',
            'name'         => $name,
            'is_military'  => false,
        ]);

        ContractCompanyDebt::create([
            'contract_company_id' => $company->id,
            'due'                 => 0,
            'collected'           => 0,
            'status'              => 'pending',
        ]);

        return $company;
    }

    protected function militaryCompany(string $name = 'الجهة السيادية'): ContractCompany
    {
        $company = ContractCompany::create([
            'company_code' => 'CO-002',
            'name'         => $name,
            'is_military'  => true,
        ]);

        ContractCompanyDebt::create([
            'contract_company_id' => $company->id,
            'due'                 => 0,
            'collected'           => 0,
            'status'              => 'pending',
        ]);

        return $company;
    }

    protected function defaultVisitType(string $name = 'كشف أولي'): VisitType
    {
        return VisitType::firstOrCreate(
            ['name' => $name],
        );
    }

    // ── Patient helpers ───────────────────────────────────────────────────────

    protected function civilianPatient(ContractCompany $company): Patient
    {
        return Patient::create([
            'patient_code'        => '100001',
            'patient_qr'          => 'QR-100001',
            'name'                => 'أحمد حسن',
            'phone'               => '01000000001',
            'national_id'         => '29901010100001',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);
    }

    protected function militaryPatient(ContractCompany $company): Patient
    {
        return Patient::create([
            'patient_code'        => '654321',
            'patient_qr'          => 'QR-654321',
            'name'                => 'العقيد محمود خالد',
            'phone'               => '01100000002',
            'national_id'         => '29801010200002',
            'patient_type'        => Patient::TYPE_MILITARY,
            'rank'                => 'عقيد',
            'sovereign_entity'    => 'القوات المسلحة',
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);
    }

    // ── Stock helpers ─────────────────────────────────────────────────────────

    protected function makeSupplier(): Supplier
    {
        return Supplier::create([
            'name'  => 'مورد الأطراف العالمية',
            'phone' => '0100000000',
        ]);
    }

    protected function stockItem(string $code = 'RM-001', int $qty = 20, float $wac = 100.00): StockItem
    {
        return StockItem::create([
            'code'          => $code,
            'name'          => "صنف {$code}",
            'spec'          => 'مواصفات قياسية',
            'store_class'   => 'A',
            'uom'           => 'piece',
            'barcode'       => "BC-{$code}",
            'qty'           => $qty,
            'reserved'      => 0,
            'wac'           => $wac,
            'status'        => $qty > StockItem::LOW_QTY_THRESHOLD ? 'ok' : 'low',
            'last_moved_at' => now()->toDateString(),
        ]);
    }

    // ── CaseRecord helpers ────────────────────────────────────────────────────

    private static int $caseSeq = 0;

    protected function caseAtStage(Patient $patient, string $stage, ?string $mfgStage = null): CaseRecord
    {
        self::$caseSeq++;
        $seq = str_pad((string) self::$caseSeq, 4, '0', STR_PAD_LEFT);

        return CaseRecord::create([
            'case_no'              => 'CASE-' . now()->year . '-' . $seq,
            'order_ref'            => str_pad((string) self::$caseSeq, 6, '0', STR_PAD_LEFT),
            'patient_id'           => $patient->id,
            'contract_company_id'  => $patient->contract_company_id,
            'company_name'         => $patient->company_name,
            'patient_type'         => $patient->patient_type,
            'path'                 => $patient->isMilitary() ? CaseRecord::PATH_MILITARY : CaseRecord::PATH_STANDARD,
            'stage_key'            => $stage,
            'manufacturing_stage'  => $mfgStage,
        ]);
    }

    /**
     * يقود الحالة عبر خط الأنابيب الجديد حتى مكتب التشغيل (الخطوة 7):
     * توصيف (BOM خام) → معدلات → تكاليف → عرض السعر → مكتب التشغيل.
     *
     * المدني يتوقف في STAGE_OPERATIONS بانتظار الاعتماد.
     * العسكري يُعتمَد صامتاً تلقائياً ويصل STAGE_MANUFACTURING/MFG_WAREHOUSE.
     *
     * @param  list<string>  $codes
     */
    protected function operationsReadyCase(Patient $patient, array $codes = ['RM-001']): CaseRecord
    {
        foreach ($codes as $code) {
            if (! StockItem::where('code', $code)->exists()) {
                $this->stockItem($code, 20, 100.00);
            }
        }

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        app(\App\Services\BomService::class)->createSpecRaw(
            $case,
            array_map(fn (string $c) => [
                'stock_item_code' => $c,
                'name'            => "صنف {$c}",
                'qty'             => 1,
            ], $codes),
        );

        app(\App\Services\WorkflowService::class)->advance(
            $case,
            \App\Enums\WorkflowEvent::SpecSaved->value,
        );

        $case = app(\App\Services\AdjustmentsService::class)->complete($case->fresh());

        return $this->costingConfirmedCase($case);
    }

    /**
     * يُكمّل مسار التكاليف ويُصدر العرض ويُحوّل لمكتب التشغيل (أو المخزن للعسكري).
     */
    protected function costingConfirmedCase(CaseRecord $case): CaseRecord
    {
        return app(\App\Services\CostingService::class)->confirmAndIssueQuote($case->fresh());
    }

    /**
     * يقود الحالة حتى دخول الورشة (manufacturing/issue) بعد صرف المخزن:
     * مكتب التشغيل (اعتماد) → المخزن (صرف) → الورشة.
     *
     * @param  list<string>  $codes
     */
    protected function dispensedManufacturingCase(Patient $patient, array $codes = ['RM-001']): CaseRecord
    {
        $case = $this->operationsReadyCase($patient, $codes);

        if ($case->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $case = app(\App\Services\OperationsService::class)->approve($case, 'اختبار');
        }

        $bom = \App\Models\Bom::with('items')->where('case_id', $case->id)->firstOrFail();

        app(\App\Services\BomService::class)->releaseToWip(
            $bom,
            $bom->items->map(fn ($i) => 'BC-' . $i->stock_item_code)->all(),
        );

        return $case->fresh();
    }

    protected function advanceCaseToFinishing(CaseRecord $case): CaseRecord
    {
        $bomService = app(\App\Services\BomService::class);
        $case       = $case->fresh();

        foreach ([
            CaseRecord::MFG_GENERATION,
            CaseRecord::MFG_ASSEMBLY,
            CaseRecord::MFG_CASTING,
            CaseRecord::MFG_FINISHING,
        ] as $stage) {
            if ($case->manufacturing_stage === $stage) {
                continue;
            }

            $case = $bomService->advanceManufacturingStage($case, $stage);
        }

        return $case->fresh();
    }

    protected function finishBomAfterQuality(CaseRecord $case): \App\Models\Bom
    {
        $bom = \App\Models\Bom::where('case_id', $case->id)->firstOrFail();

        return app(\App\Services\BomService::class)->finish($bom);
    }
}
