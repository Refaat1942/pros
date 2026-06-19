<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\Patient;

/**
 * ترحيل مالي عند التسليم — مدني (مديونية) أو عسكري (تكلفة سيادية).
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

        $this->postCivilian($case);
    }

    private function postCivilian(CaseRecord $case): void
    {
        if (! $case->contract_company_id) {
            abort(422, 'الحالة المدنية غير مرتبطة بجهة تعاقد.');
        }

        $company = ContractCompany::findOrFail($case->contract_company_id);
        $amount  = (float) ($case->quote_total ?? 0);

        if ($amount <= 0) {
            abort(422, 'مبلغ العرض غير صالح للترحيل المالي.');
        }

        $before = ['quote_total' => $amount];

        $this->contractDebtService->increaseDue($company, $amount);

        AuditService::log(
            action:      'post',
            description: 'ترحيل مستحق مدني',
            tag:         'financial',
            before:      $before,
            after:       [
                'company_id'  => $company->id,
                'company'     => $company->name,
                'quote_total' => $amount,
            ],
        );
    }

    private function postMilitary(CaseRecord $case): void
    {
        AuditService::log(
            action:      'post',
            description: 'ترحيل تكلفة عسكري',
            tag:         'financial',
            after:       ['total_cost' => (float) ($case->total_cost ?? 0)],
        );
    }
}
