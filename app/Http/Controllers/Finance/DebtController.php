<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Services\ContractDebtService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly ContractDebtService $contractDebtService)
    {
    }

    /**
     * جميع مديونيات الجهات — مستحق / محصَّل / متبقٍ.
     */
    public function index(Request $request): JsonResponse
    {
        $debts = $this->fetchForDashboard(
            ContractCompanyDebt::with('contractCompany:id,company_code,name,is_military')
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->search, fn ($q, $s) => $q->whereHas(
                    'contractCompany',
                    fn ($q) => $q->where('name', 'like', "%{$s}%")
                        ->orWhere('company_code', 'like', "%{$s}%")
                ))
                ->orderByDesc('due')
        );

        return response()->json([
            'data'  => collect($debts)->map(fn ($d) => $this->formatDebt($d))->values(),
            'total' => $debts->count(),
        ]);
    }

    /**
     * تسجيل دفعة من جهة التعاقد.
     */
    public function recordPayment(RecordPaymentRequest $request, ContractCompany $company): JsonResponse
    {
        $this->contractDebtService->recordPayment(
            $company,
            (float) $request->validated('amount'),
        );

        $company->load('debt.contractCompany');

        return response()->json([
            'message' => 'تم تسجيل الدفعة بنجاح.',
            'debt'    => $this->formatDebt($company->debt),
        ]);
    }

    private function formatDebt(ContractCompanyDebt $debt): array
    {
        $due       = (float) $debt->due;
        $collected = (float) $debt->collected;

        return $debt->only(['id', 'contract_company_id', 'due', 'collected', 'status']) + [
            'remaining' => max(0, $due - $collected),
            'company'   => $debt->relationLoaded('contractCompany') && $debt->contractCompany
                ? $debt->contractCompany->only(['id', 'company_code', 'name', 'is_military'])
                : null,
        ];
    }
}
