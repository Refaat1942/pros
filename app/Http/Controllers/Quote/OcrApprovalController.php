<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\ProcessOcrApprovalRequest;
use App\Services\OcrApprovalService;
use Illuminate\Http\JsonResponse;

class OcrApprovalController extends Controller
{
    public function __construct(private readonly OcrApprovalService $ocrApprovalService) {}

    /**
     * تسجيل موافقة الجهة بعد رفع الخطاب والمراجعة اليدوية.
     */
    public function process(ProcessOcrApprovalRequest $request): JsonResponse
    {
        $case = $this->ocrApprovalService->process($request->validated());

        return response()->json([
            'message' => 'تم تسجيل موافقة الجهة — بانتظار إصدار أمر الشغل من مكتب التشغيل.',
            'case' => $case->only([
                'id', 'case_no', 'stage_key', 'manufacturing_stage',
                'work_order_no', 'approval_date', 'approval_confirmed_at',
            ]),
            'work_order_no' => $case->work_order_no,
            'unfrozen' => true,
        ]);
    }
}
