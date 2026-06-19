<?php

namespace App\Http\Controllers\Quote;

use App\Exceptions\OcrMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\ProcessOcrApprovalRequest;
use App\Services\OcrApprovalService;
use Illuminate\Http\JsonResponse;

class OcrApprovalController extends Controller
{
    public function __construct(private readonly OcrApprovalService $ocrApprovalService)
    {
    }

    /**
     * معالجة خطاب الموافقة — OCR + مطابقة + فك التجميد + WO-*.
     */
    public function process(ProcessOcrApprovalRequest $request): JsonResponse
    {
        try {
            $case = $this->ocrApprovalService->process($request->validated());
        } catch (OcrMismatchException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'blocked' => true,
                'ocr'     => true,
            ], 422);
        }

        return response()->json([
            'message'       => 'تمت مطابقة OCR — تم فك التجميد وتوليد أمر التشغيل.',
            'case'          => $case->only([
                'id', 'case_no', 'stage_key', 'manufacturing_stage',
                'work_order_no', 'approval_date', 'approval_confirmed_at',
            ]),
            'work_order_no' => $case->work_order_no,
            'unfrozen'      => true,
        ]);
    }
}
