<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\ScanApprovalRequest;
use App\Models\CaseRecord;
use App\Models\Quote;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApprovalScanController extends Controller
{
    public function __construct(private readonly ApprovalService $approvalService) {}

    /**
     * مسح QR من خطاب الموافقة — يُؤكِّد الحالة ويُولِّد أمر الشغل.
     */
    public function scan(ScanApprovalRequest $request): JsonResponse
    {
        $quote = Quote::where('quote_no', $request->validated('scanned_qr'))->first();

        if (! $quote || ! $quote->case_id) {
            return response()->json([
                'message' => 'عرض السعر غير موجود.',
            ], 422);
        }

        $case = CaseRecord::findOrFail($quote->case_id);

        try {
            $case = $this->approvalService->confirm($case, $request->validated('scanned_qr'));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json([
            'message' => 'تم تأكيد الموافقة بنجاح — الحالة جاهزة للتصنيع.',
            'case' => $case->only([
                'id', 'case_no', 'stage_key', 'manufacturing_stage',
                'work_order_no', 'approval_date', 'approval_confirmed_at',
            ]),
            'quote_no' => $quote->fresh()->quote_no,
            'work_order_no' => $case->work_order_no,
        ]);
    }
}
