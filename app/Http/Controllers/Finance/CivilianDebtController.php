<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\AdminCivilianDebtService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CivilianDebtController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly AdminCivilianDebtService $civilianDebtService)
    {
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
}
