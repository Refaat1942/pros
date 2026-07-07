<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SpecEditRequestSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectSpecEditRequestRequest;
use App\Models\SpecEditRequest;
use App\Services\SpecEditRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpecEditRequestController extends Controller
{
    public function __construct(private readonly SpecEditRequestService $editService) {}

    public function index(Request $request): JsonResponse
    {
        $rows = $this->editService->listForAdmin(
            $request->query('status'),
            $request->query('search'),
        );

        return response()->json([
            'data' => $rows,
            'total' => count($rows),
            'pending' => $this->editService->pendingCount(),
            'rejection_reasons' => config('spec_edit.rejection_reasons', []),
        ]);
    }

    public function approve(SpecEditRequest $specEditRequest): JsonResponse
    {
        $row = $this->editService->approve($specEditRequest, Auth::user());

        $message = $row->source === SpecEditRequestSource::Adjustments
            ? 'تم اعتماد تعديل بنود المعدلات وتطبيقها على قائمة المواد.'
            : 'تم اعتماد تعديل التوصيف وتطبيقه على قائمة المواد.';

        return response()->json([
            'message' => $message,
            'request' => $this->editService->format($row),
        ]);
    }

    public function reject(RejectSpecEditRequestRequest $request, SpecEditRequest $specEditRequest): JsonResponse
    {
        $row = $this->editService->reject(
            $specEditRequest,
            Auth::user(),
            $request->validated('rejection_reason_key'),
            $request->validated('rejection_notes'),
        );

        $message = $row->source === SpecEditRequestSource::Adjustments
            ? 'تم رفض طلب التعديل — أُرسل إشعار للمعدلات.'
            : 'تم رفض طلب التعديل — أُرسل إشعار للفني.';

        return response()->json([
            'message' => $message,
            'request' => $this->editService->format($row),
        ]);
    }
}
