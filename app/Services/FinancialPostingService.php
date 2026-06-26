<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\MilitaryDebt;
use App\Models\Patient;

/**
 * ترحيل مالي — مدني (مديونية عند الصرف) أو عسكري (تكلفة سيادية عند التسليم).
 */
class FinancialPostingService
{
    public function __construct(private readonly ContractDebtService $contractDebtService)
    {
    }

    public function post(CaseRecord $case): void
    {
        if ($case->patient_type === Patient::TYPE_MILITARY || $case->isMilitary()) {
            $this->postMilitary($case);

            return;
        }

        if ($case->ledger_posted_at !== null) {
            return;
        }

        $this->postCivilianAmount($case, (float) ($case->quote_total ?? 0));
    }

    /**
     * ترحيل مستحق مدني فور صرف BOM بالباركود — مرة واحدة لكل حالة.
     */
    public function postOnDispense(CaseRecord $case, Bom $bom): void
    {
        if ($case->patient_type === Patient::TYPE_MILITARY || $case->isMilitary()) {
            return;
        }

        if ($case->ledger_posted_at !== null) {
            return;
        }

        $amount = $this->resolveDispenseAmount($case, $bom);

        if ($amount <= 0) {
            return;
        }

        $this->postCivilianAmount($case, $amount);

        CaseRecord::where('id', $case->id)->update(['ledger_posted_at' => now()]);
    }

    private function resolveDispenseAmount(CaseRecord $case, Bom $bom): float
    {
        $quoteTotal = (float) ($case->quote_total ?? 0);

        if ($quoteTotal > 0) {
            return $quoteTotal;
        }

        $bom->loadMissing('items');

        return round($bom->items->sum(fn ($item) => $item->qty * (float) $item->unit_cost), 2);
    }

    private function postCivilianAmount(CaseRecord $case, float $amount): void
    {
        if (! $case->contract_company_id) {
            abort(422, 'الحالة المدنية غير مرتبطة بجهة تعاقد.');
        }

        if ($amount <= 0) {
            abort(422, 'مبلغ العرض غير صالح للترحيل المالي.');
        }

        $company = ContractCompany::findOrFail($case->contract_company_id);
        $before  = ['amount' => $amount];

        $this->contractDebtService->increaseDue($company, $amount);

        AuditService::log(
            action:      'post',
            description: 'ترحيل مستحق مدني',
            tag:         'financial',
            before:      $before,
            after:       [
                'company_id' => $company->id,
                'company'    => $company->name,
                'amount'     => $amount,
            ],
        );
    }

    private function postMilitary(CaseRecord $case): void
    {
        $totalCost = (float) ($case->total_cost ?? 0);

        // إنشاء قيد المديونية في سجل الجهات العسكرية — مرة واحدة لكل حالة.
        if (! MilitaryDebt::where('case_id', $case->id)->exists()) {
            $case->loadMissing('patient');

            MilitaryDebt::create([
                'case_id'             => $case->id,
                'work_order_no'       => $case->work_order_no,
                'patient_name'        => $case->patient?->name ?? $case->company_name ?? '—',
                'patient_national_id' => $case->patient?->national_id ?? null,
                'sovereign_entity'    => $case->sovereign_entity ?? $case->company_name ?? '—',
                'total_cost'          => $totalCost,
                'collected'           => 0,
                'delivered_at'        => $case->delivered_at?->toDateString() ?? now()->toDateString(),
                'status'              => MilitaryDebt::STATUS_PENDING,
            ]);
        }

        AuditService::log(
            action:      'post',
            description: 'ترحيل تكلفة عسكري — قيد مديونية سيادية',
            tag:         'financial',
            after:       [
                'case_id'          => $case->id,
                'work_order_no'    => $case->work_order_no,
                'sovereign_entity' => $case->sovereign_entity,
                'total_cost'       => $totalCost,
            ],
        );
    }
}
