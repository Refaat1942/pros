<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectSpecEditRequestRequest;
use App\Models\SpecEditRequest;
use App\Services\SpecEditRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpecEditRequestController extends Controller
{
    public function __construct(private readonly SpecEditRequestService $editService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $this->editService->listForAdmin(
            $request->query('status'),
            $request->query('search'),
        );

        $pending = collect($rows)->where('status', 'pending')->count();

        return response()->json([
            'data'    => $rows,
            'total'   => count($rows),
            'pending' => $pending,
            'rejection_reasons' => config('spec_edit.rejection_reasons', []),
        ]);
    }

    public function approve(SpecEditRequest $specEditRequest): JsonResponse
    {
        $row = $this->editService->approve($specEditRequest, Auth::user());

        return response()->json([
            'message' => 'تم اعتماد التعديل وتطبيقه على التوصيف وقائمة المواد.',
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

        return response()->json([
            'message' => 'تم رفض طلب التعديل — أُرسل إشعار للفني.',
            'request' => $this->editService->format($row),
        ]);
    }
}
