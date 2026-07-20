<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Services\ServicesApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServicesApprovalController extends Controller
{
    public function __construct(
        private readonly ServicesApprovalService $servicesApprovals,
    ) {}

    public function index(): JsonResponse
    {
        $rows = $this->servicesApprovals->listPending();

        return response()->json([
            'data' => $rows,
            'total' => count($rows),
        ]);
    }

    public function approve(Request $request, CaseRecord $case): JsonResponse
    {
        $case = $this->servicesApprovals->approve(
            $case,
            Auth::user(),
            $request->file('document'),
            $request->input('notes'),
        );

        return response()->json([
            'message' => 'تم التصديق — تم اعتماد الحالة وإصدار أمر الشغل.',
            'case' => $case->only(['id', 'case_no', 'stage_key', 'manufacturing_stage', 'work_order_no']),
        ]);
    }
}
