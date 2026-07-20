<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockDispenseRequest;
use App\Services\StockDispenseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockDispenseApprovalController extends Controller
{
    public function __construct(
        private readonly StockDispenseRequestService $dispenseRequests,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->dispenseRequests->listPending(),
            'total' => count($this->dispenseRequests->listPending()),
        ]);
    }

    public function show(StockDispenseRequest $stockDispenseRequest): JsonResponse
    {
        $stockDispenseRequest->load([
            'caseRecord.patient:id,name,patient_code',
            'bom.items:id,bom_id,stock_item_code,name,qty,issued_qty',
            'requestedBy:id,name',
        ]);

        return response()->json([
            'request' => [
                'id' => $stockDispenseRequest->id,
                'status' => $stockDispenseRequest->status,
                'work_order_no' => $stockDispenseRequest->work_order_no,
                'lines' => $stockDispenseRequest->lines ?? [],
                'created_at' => $stockDispenseRequest->created_at?->toIso8601String(),
                'case' => $stockDispenseRequest->caseRecord?->only(['id', 'case_no', 'work_order_no']),
                'patient' => $stockDispenseRequest->caseRecord?->patient?->only(['id', 'name', 'patient_code']),
                'bom' => $stockDispenseRequest->bom?->only(['id', 'bom_no', 'stage']),
                'bom_items' => $stockDispenseRequest->bom?->items?->map(fn ($i) => $i->only([
                    'stock_item_code', 'name', 'qty', 'issued_qty',
                ]))->values(),
                'requested_by' => $stockDispenseRequest->requestedBy?->only(['id', 'name']),
            ],
        ]);
    }

    public function approve(StockDispenseRequest $stockDispenseRequest): JsonResponse
    {
        $request = $this->dispenseRequests->approve($stockDispenseRequest, Auth::user());

        return response()->json([
            'message' => 'تم اعتماد الصرف — تم خصم المخزون.',
            'request' => $request->only(['id', 'status', 'approved_at']),
        ]);
    }

    public function reject(Request $request, StockDispenseRequest $stockDispenseRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = $this->dispenseRequests->reject(
            $stockDispenseRequest,
            Auth::user(),
            $validated['reason'] ?? null,
        );

        return response()->json([
            'message' => 'تم رفض طلب الصرف.',
            'request' => $row->only(['id', 'status', 'rejection_reason']),
        ]);
    }
}
