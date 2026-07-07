<?php

namespace App\Services;

use App\Enums\DebtStatus;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use Illuminate\Support\Facades\DB;

/**
 * الخدمة الوحيدة المسموح لها بالكتابة على جدول contract_company_debts.
 *
 * - initialise()     → يُنشئ صف الدفتر عند إنشاء الجهة
 * - increaseDue()    → يزيد المستحق (يُستدعى من Task 10 عند التسليم)
 * - recordPayment()  → يسجّل دفعة وصول (من صفحة المديونيات)
 */
class ContractDebtService
{
    /**
     * يُنشئ صف المديونية الأولي عند إنشاء جهة تعاقد.
     * يُستدعى حصراً من ContractCompanyController::store().
     */
    public function initialise(ContractCompany $company): ContractCompanyDebt
    {
        return ContractCompanyDebt::create([
            'contract_company_id' => $company->id,
            'due' => 0,
            'collected' => 0,
            'status' => DebtStatus::Pending->value,
        ]);
    }

    /**
     * يُرجع صف المديونية — يُنشئه تلقائياً إن لم يكن موجوداً.
     */
    public function forCompany(ContractCompany $company, bool $lock = false): ContractCompanyDebt
    {
        $query = ContractCompanyDebt::where('contract_company_id', $company->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        $debt = $query->first();
        if ($debt) {
            return $debt;
        }

        $this->initialise($company);

        $query = ContractCompanyDebt::where('contract_company_id', $company->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /**
     * يزيد المبلغ المستحق على الجهة (عند تسليم طرف للمريض — Task 10).
     */
    public function increaseDue(ContractCompany $company, float $amount): void
    {
        DB::transaction(function () use ($company, $amount) {
            $debt = $this->forCompany($company, lock: true);

            $before = $this->snapshot($debt);

            $debt->due = (float) $debt->due + $amount;
            $debt->status = $this->computeStatus($debt)->value;
            $debt->save();

            AuditService::log(
                action: 'update',
                description: "زيادة مستحق جهة {$company->name} بمقدار {$amount}",
                tag: 'financial',
                before: $before,
                after: $this->snapshot($debt),
            );
        });
    }

    /**
     * يسجّل دفعة من الجهة (من صفحة المديونيات في لوحة الإدارة).
     */
    public function recordPayment(ContractCompany $company, float $amount): void
    {
        DB::transaction(function () use ($company, $amount) {
            $debt = $this->forCompany($company, lock: true);

            $before = $this->snapshot($debt);

            $debt->collected = (float) $debt->collected + $amount;
            $debt->status = $this->computeStatus($debt)->value;
            $debt->save();

            app(DebtCollectionEntryService::class)->record($debt, $amount, (float) $debt->due);

            AuditService::log(
                action: 'payment',
                description: "تسجيل تحصيل من جهة {$company->name} بمقدار {$amount}",
                tag: 'financial',
                before: $before,
                after: $this->snapshot($debt),
            );
        });
    }

    /**
     * يُنقص المبلغ المستحق — عند تطبيق إشعار دائن (Task 10).
     */
    public function decreaseDue(ContractCompany $company, float $amount): void
    {
        DB::transaction(function () use ($company, $amount) {
            $debt = $this->forCompany($company, lock: true);

            $before = $this->snapshot($debt);

            $debt->due = max(0, (float) $debt->due - $amount);
            $debt->status = $this->computeStatus($debt)->value;
            $debt->save();

            AuditService::log(
                action: 'debt',
                description: "تخفيض مستحق جهة {$company->name} بمقدار {$amount}",
                tag: 'financial',
                before: $before,
                after: $this->snapshot($debt),
            );
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function computeStatus(ContractCompanyDebt $debt): DebtStatus
    {
        $due = (float) $debt->due;
        $collected = (float) $debt->collected;

        if ($due <= 0) {
            return DebtStatus::Pending;
        }

        if ($collected >= $due) {
            return DebtStatus::Paid;
        }

        if ($collected > 0) {
            return DebtStatus::Partial;
        }

        return DebtStatus::Pending;
    }

    private function snapshot(ContractCompanyDebt $debt): array
    {
        return [
            'due' => (float) $debt->due,
            'collected' => (float) $debt->collected,
            'status' => $debt->status,
        ];
    }
}
