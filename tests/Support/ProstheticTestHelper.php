<?php

namespace Tests\Support;

use App\Enums\WorkflowEvent;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Quote;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VisitType;
use App\Services\AdjustmentsService;
use App\Services\BomService;
use App\Services\CostingService;
use App\Services\OperationsService;
use App\Services\PermissionCatalogService;
use App\Services\WorkflowService;
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
            'admin' => 'مسؤول النظام',
            'reception' => 'موظف استقبال',
            'doctor' => 'طبيب',
            'spec' => 'فني مواصفات',
            'adjustments' => 'فني تعديلات',
            'costing' => 'فني تكاليف',
            'operations' => 'مكتب عمليات',
            'cashier' => 'موظف الخزنة',
            'workshop' => 'ورشة التصنيع',
            'technical' => 'مسؤول مخزن',
        ];

        return Role::firstOrCreate(['slug' => $slug], ['label_ar' => $labels[$slug] ?? $slug]);
    }

    protected function userWithRole(string $slug): User
    {
        $role = $this->makeRole($slug);
        $this->seedDefaultPermissions($role);

        return User::query()->updateOrCreate(
            ['username' => $slug],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => $role->label_ar,
            ]
        );
    }

    private function seedDefaultPermissions(Role $role): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        // كل دور يحصل على جميع الصلاحيات التشغيلية (= الحالة الافتراضية بعد seed)
        $ids = Permission::query()
            ->where('dashboard', '!=', Role::SLUG_ADMIN)
            ->pluck('id');

        $role->permissions()->syncWithoutDetaching($ids);
    }

    // ── ContractCompany helpers ───────────────────────────────────────────────

    protected function civilianCompany(string $name = 'التأمين الصحي'): ContractCompany
    {
        $company = ContractCompany::create([
            'company_code' => 'CO-001',
            'name' => $name,
            'is_military' => false,
            'is_contracted' => true,
        ]);

        ContractCompanyDebt::create([
            'contract_company_id' => $company->id,
            'due' => 0,
            'collected' => 0,
            'status' => 'pending',
        ]);

        return $company;
    }

    protected function militaryCompany(string $name = 'الجهة السيادية'): ContractCompany
    {
        $company = ContractCompany::create([
            'company_code' => 'CO-002',
            'name' => $name,
            'is_military' => true,
        ]);

        ContractCompanyDebt::create([
            'contract_company_id' => $company->id,
            'due' => 0,
            'collected' => 0,
            'status' => 'pending',
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
            'patient_code' => '100001',
            'patient_qr' => 'QR-100001',
            'tracking_uid' => 'case-test0001',
            'name' => 'أحمد حسن',
            'phone' => '01000000001',
            'national_id' => '29901010100001',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'registered_at' => now()->toDateString(),
            'status' => Patient::STATUS_ACTIVE,
        ]);
    }

    /** مريض مدني على نفقته الشخصية (كاش) — بلا جهة تعاقد. */
    protected function cashPatient(): Patient
    {
        return Patient::create([
            'patient_code' => '200002',
            'patient_qr' => 'QR-200002',
            'tracking_uid' => 'case-cash0002',
            'name' => 'سارة كاش',
            'phone' => '01000000009',
            'national_id' => '29901010100009',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => null,
            'company_name' => null,
            'registered_at' => now()->toDateString(),
            'status' => Patient::STATUS_ACTIVE,
        ]);
    }

    protected function militaryPatient(ContractCompany $company): Patient
    {
        return Patient::create([
            'patient_code' => '654321',
            'patient_qr' => 'QR-654321',
            'tracking_uid' => 'case-test6543',
            'name' => 'العقيد محمود خالد',
            'phone' => '01100000002',
            'national_id' => '29801010200002',
            'patient_type' => Patient::TYPE_MILITARY,
            'rank' => 'عقيد',
            'sovereign_entity' => 'القوات المسلحة',
            'contract_company_id' => $company->id,
            'company_name' => $company->name,
            'registered_at' => now()->toDateString(),
            'status' => Patient::STATUS_ACTIVE,
        ]);
    }

    // ── Stock helpers ─────────────────────────────────────────────────────────

    protected function makeSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'مورد الأطراف العالمية',
            'phone' => '0100000000',
        ]);
    }

    protected function stockItem(string $code = 'RM-001', int $qty = 20, float $wac = 100.00, bool $quick = false): StockItem
    {
        return StockItem::create([
            'code' => $code,
            'name' => "صنف {$code}",
            'spec' => 'مواصفات قياسية',
            'store_class' => 'A',
            'is_quick_dispense' => $quick,
            'uom' => 'piece',
            'barcode' => "BC-{$code}",
            'qty' => $qty,
            'reserved' => 0,
            'wac' => $wac,
            'status' => $qty > StockItem::LOW_QTY_THRESHOLD ? 'ok' : 'low',
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
            'case_no' => 'CASE-'.now()->year.'-'.$seq,
            'order_ref' => str_pad((string) self::$caseSeq, 6, '0', STR_PAD_LEFT),
            'patient_id' => $patient->id,
            'contract_company_id' => $patient->contract_company_id,
            'company_name' => $patient->company_name,
            'patient_type' => $patient->patient_type,
            'path' => $patient->isMilitary() ? CaseRecord::PATH_MILITARY : CaseRecord::PATH_STANDARD,
            'stage_key' => $stage,
            'manufacturing_stage' => $mfgStage,
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

        app(BomService::class)->createSpecRaw(
            $case,
            array_map(fn (string $c) => [
                'stock_item_code' => $c,
                'name' => "صنف {$c}",
                'qty' => 1,
            ], $codes),
        );

        app(WorkflowService::class)->advance(
            $case,
            WorkflowEvent::SpecSaved->value,
        );

        $case = app(AdjustmentsService::class)->complete($case->fresh());

        return $this->costingConfirmedCase($case);
    }

    /**
     * يُكمّل مسار التكاليف ويُصدر العرض ويُحوّل لمكتب التشغيل (أو المخزن للعسكري).
     */
    protected function costingConfirmedCase(CaseRecord $case): CaseRecord
    {
        return app(CostingService::class)->confirmAndIssueQuote($case->fresh());
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
            // محاكاة موافقة الجهة (مسح خطاب الموافقة في الاستقبال) قبل إصدار أمر الشغل.
            if (! $case->isMilitary() && ! $case->isCashCivilian()) {
                Quote::where('case_id', $case->id)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['status' => Quote::STATUS_APPROVED, 'status_label' => 'معتمد من الجهة']);
            }

            $case = app(OperationsService::class)->approve($case, 'اختبار');
        }

        $bom = Bom::with('items')->where('case_id', $case->id)->firstOrFail();

        // مسح باركود لكل وحدة (مطابقة صارمة: عدد المسحات = الكمية لكل صنف).
        $scans = [];
        foreach ($bom->items as $item) {
            for ($n = 0; $n < (int) $item->qty; $n++) {
                $scans[] = 'BC-'.$item->stock_item_code;
            }
        }

        app(BomService::class)->releaseToWip($bom, $scans);

        return $case->fresh();
    }

    /**
     * يسجّل موافقة الجهة على آخر عرض سعر (محاكاة مسح خطاب الموافقة في الاستقبال).
     */
    protected function markEntityApproved(CaseRecord $case): void
    {
        Quote::where('case_id', $case->id)
            ->orderByDesc('id')
            ->limit(1)
            ->update(['status' => Quote::STATUS_APPROVED, 'status_label' => 'معتمد من الجهة']);
    }

    /**
     * يسجّل موافقة الجهة (للمدني التعاقدي) ثم يعتمد الحالة في مكتب التشغيل (إصدار أمر الشغل).
     */
    protected function approveAtOperations(CaseRecord $case, string $note = 'اختبار'): CaseRecord
    {
        $case = $case->fresh();

        if (! $case->isMilitary() && ! $case->isCashCivilian()) {
            $this->markEntityApproved($case);
        }

        return app(OperationsService::class)->approve($case, $note);
    }

    protected function advanceCaseToFinishing(CaseRecord $case): CaseRecord
    {
        $bomService = app(BomService::class);
        $case = $case->fresh();

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

    protected function finishBomAfterQuality(CaseRecord $case): Bom
    {
        $bom = Bom::where('case_id', $case->id)->firstOrFail();

        return app(BomService::class)->finish($bom);
    }
}
