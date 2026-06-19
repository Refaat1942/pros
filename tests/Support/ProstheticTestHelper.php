<?php

namespace Tests\Support;

use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\Patient;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
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
            'operations'  => 'مكتب عمليات',
            'technical'   => 'مسؤول مخزن',
        ];

        return Role::firstOrCreate(['slug' => $slug], ['label_ar' => $labels[$slug] ?? $slug]);
    }

    protected function userWithRole(string $slug): User
    {
        $role = $this->makeRole($slug);

        return User::factory()->create([
            'role_id'  => $role->id,
            'email'    => "{$slug}@test.local",
            'password' => Hash::make('password'),
            'status'   => User::STATUS_ACTIVE,
        ]);
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

    // ── Patient helpers ───────────────────────────────────────────────────────

    protected function civilianPatient(ContractCompany $company): Patient
    {
        return Patient::create([
            'patient_code'        => 'PT-CIV-0001',
            'patient_qr'          => 'QR-PT-CIV-0001',
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
            'patient_code'        => 'PT-MIL-0001',
            'patient_qr'          => 'QR-PT-MIL-0001',
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
            'name'      => 'مورد الأطراف العالمية',
            'phone'     => '0100000000',
            'is_active' => true,
        ]);
    }

    protected function stockItem(string $code = 'RM-001', int $qty = 20, float $wac = 100.00): StockItem
    {
        return StockItem::create([
            'code'          => $code,
            'name'          => "صنف {$code}",
            'spec'          => 'مواصفات قياسية',
            'category'      => 'raw',
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

    protected function caseAtStage(Patient $patient, string $stage, string $mfgStage = null): CaseRecord
    {
        self::$caseSeq++;
        $seq = str_pad((string) self::$caseSeq, 4, '0', STR_PAD_LEFT);

        return CaseRecord::create([
            'case_no'              => 'CASE-' . now()->year . '-' . $seq,
            'order_ref'            => 'ORD-' . $seq,
            'patient_id'           => $patient->id,
            'contract_company_id'  => $patient->contract_company_id,
            'company_name'         => $patient->company_name,
            'patient_type'         => $patient->patient_type,
            'path'                 => $patient->isMilitary() ? CaseRecord::PATH_MILITARY : CaseRecord::PATH_STANDARD,
            'stage_key'            => $stage,
            'manufacturing_stage'  => $mfgStage,
        ]);
    }
}
