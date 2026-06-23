<?php

namespace App\Services;

use App\Models\ApprovalContract;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Support\CaseDisplayStatus;
use App\Support\CaseFinancialSummary;
use Illuminate\Support\Facades\Storage;

/**
 * تفاصيل الحالة لعرض الأدمن — مريض، عرض سعر، خطاب موافقة.
 */
class AdminCaseDetailService
{
    /** @return array<string, mixed> */
    public function build(CaseRecord $case): array
    {
        $case->loadMissing([
            'patient.militaryRank:id,name',
            'quotes' => fn ($q) => $q->with('items')->orderByDesc('id'),
            'pricingRequest:id,case_id,request_no',
            'bom:id,case_id,bom_no,stage',
        ]);

        $quote    = $this->resolveQuote($case);
        $contract = ApprovalContract::query()
            ->where('case_id', $case->id)
            ->orderByDesc('id')
            ->first();

        $display   = CaseDisplayStatus::forCase($case);
        $totalCost = CaseFinancialSummary::totalCost($case);
        $patient   = $case->patient;
        $isMil     = $case->isMilitary();
        $sovereign = $isMil
            ? ($patient?->displayEntity() ?? Patient::MILITARY_SOVEREIGN_ENTITY)
            : ($patient?->sovereign_entity ?? $case->sovereign_entity);

        return [
            'case' => [
                'id'                  => $case->id,
                'case_no'             => $case->case_no,
                'order_ref'           => $case->order_ref,
                'work_order_no'       => $case->work_order_no,
                'stage_key'           => $case->stage_key,
                'stage_label'         => $display->label,
                'manufacturing_stage' => $case->manufacturing_stage,
                'quote_date'          => $case->quote_date?->format('d/m/Y'),
                'approval_date'       => $case->approval_date?->format('d/m/Y')
                    ?? $case->approval_confirmed_at?->format('d/m/Y'),
                'delivered_at'        => $case->delivered_at?->format('d/m/Y'),
                'total_cost'          => $totalCost,
                'paid'                => CaseFinancialSummary::paidAmount($case, $totalCost),
                'pricing_ref'         => $case->pricingRequest?->request_no,
                'bom'                 => $case->bom?->only(['bom_no', 'stage']),
            ],
            'patient' => $patient ? [
                'name'         => $patient->name,
                'patient_code' => $patient->patient_code,
                'phone'        => $patient->phone,
                'national_id'  => $patient->national_id,
                'type'         => $patient->patient_type,
                'type_label'   => $isMil ? 'عسكري' : 'مدني',
                'company'      => $isMil ? null : ($case->company_name ?? $patient->company_name),
                'rank'         => $patient->militaryRank?->name ?? $patient->rank,
                'sovereign'    => $sovereign,
            ] : null,
            'quote' => $quote ? [
                'id'           => $quote->id,
                'quote_no'     => $quote->quote_no,
                'status'       => $quote->status,
                'status_label' => $quote->status_label,
                'quote_date'   => $quote->quote_date?->format('d/m/Y'),
                'total'        => (float) $quote->total,
                'company_name' => $quote->company_name,
                'items'          => $quote->items->map(fn ($i) => [
                    'name'            => $i->name,
                    'stock_item_code' => $i->stock_item_code,
                    'qty'             => $i->qty,
                    'amount'          => (float) $i->amount,
                ])->values()->all(),
                'print_url'    => route('admin.cases.quote', $case),
            ] : null,
            'approval' => $contract ? [
                'contract_no'    => $contract->contract_no,
                'letter_ref'     => $contract->letter_ref,
                'approval_date'  => $contract->approval_date?->format('d/m/Y'),
                'approved_amount'=> (float) $contract->approved_amount,
                'has_letter'     => (bool) $contract->letter_path,
                'letter_url'     => $contract->letter_path
                    ? asset('storage/' . $contract->letter_path)
                    : null,
                'letter_ext'     => $contract->letter_path
                    ? strtolower(pathinfo($contract->letter_path, PATHINFO_EXTENSION))
                    : null,
                'download_url'   => $contract->letter_path
                    && Storage::disk('public')->exists($contract->letter_path)
                    ? route('admin.contracts.download', $contract)
                    : null,
            ] : null,
            'is_military' => $isMil,
        ];
    }

    private function resolveQuote(CaseRecord $case): ?Quote
    {
        if ($case->quote_no) {
            $byNo = $case->quotes->firstWhere('quote_no', $case->quote_no);
            if ($byNo) {
                return $byNo;
            }
        }

        return $case->quotes->first();
    }
}
