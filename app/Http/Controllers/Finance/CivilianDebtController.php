<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Models\ContractCompany;
use App\Services\AdminCivilianDebtService;
use App\Services\ContractDebtService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CivilianDebtController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly AdminCivilianDebtService $civilianDebtService,
        private readonly ContractDebtService $contractDebtService,
    ) {
    }

    /**
     * مديونيات جهات التعاقد المدنية — مع فلاتر وتجميع.
     */
    public function index(Request $request): JsonResponse
    {
        $debts = $this->fetchForDashboard($this->civilianDebtService->query($request));

        return response()->json([
            'data'  => collect($debts)->map(fn ($d) => $this->civilianDebtService->formatDebt($d))->values(),
            'stats' => $this->civilianDebtService->stats(collect($debts)),
            'total' => $debts->count(),
        ]);
    }

    /**
     * تسجيل تحصيل مبلغ محوّل لحساب الإدارة.
     */
    public function recordPayment(RecordPaymentRequest $request, ContractCompany $company): JsonResponse
    {
        if ($company->is_military) {
            abort(422, 'هذه الجهة عسكرية — استخدم شاشة المديونيات العسكرية.');
        }

        $debt      = $this->contractDebtService->forCompany($company);
        $remaining = max(0, round((float) $debt->due - (float) $debt->collected, 2));
        $amount    = round((float) $request->validated('amount'), 2);

        if ($remaining <= 0) {
            return response()->json(['message' => 'لا يوجد متبقٍ للتحصيل على هذه الجهة.'], 422);
        }

        if ($amount > $remaining) {
            return response()->json([
                'message' => 'المبلغ المُدخل أكبر من المتبقي للتحصيل (' . number_format($remaining, 2) . ' ج.م).',
            ], 422);
        }

        $this->contractDebtService->recordPayment($company, $amount);

        $debt = $company->fresh()->load('debt.contractCompany')->debt;

        return response()->json([
            'message' => $debt->status === \App\Enums\DebtStatus::Paid->value
                ? 'تم التحصيل بالكامل — تم تحديث المحصّل.'
                : 'تم تسجيل جزء من التحصيل — يمكنك إكمال الباقي لاحقاً.',
            'debt'    => $this->civilianDebtService->formatDebt($debt),
        ]);
    }
}
