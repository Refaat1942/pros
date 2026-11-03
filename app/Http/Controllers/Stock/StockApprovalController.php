<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\StockReceiptRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\StockApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** «اعتمادات المخزن» — طلبات التوريد واستلام الوارد بانتظار أدمن المخازن. */
class StockApprovalController extends Controller
{
    public function __construct(private readonly StockApprovalService $approvals) {}

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json($this->approvals->pending() + [
            'can_approve_supply' => $user->can(StockApprovalService::PERM_SUPPLY),
            'can_approve_receipts' => $user->can(StockApprovalService::PERM_RECEIPT),
        ]);
    }

    public function approveSupply(SupplyRequest $supplyRequest): JsonResponse
    {
        $request = $this->approvals->approveSupply($supplyRequest, Auth::user());

        return response()->json(['message' => "تم اعتماد طلب التوريد {$request->request_no}."]);
    }

    public function rejectSupply(Request $request, SupplyRequest $supplyRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $supply = $this->approvals->rejectSupply($supplyRequest, Auth::user(), $data['reason']);

        return response()->json(['message' => "تم رفض طلب التوريد {$supply->request_no}."]);
    }

    public function approveReceipt(StockReceiptRequest $receiptRequest): JsonResponse
    {
        $receipt = $this->approvals->approveReceipt($receiptRequest, Auth::user());

        return response()->json(['message' => 'تم اعتماد الاستلام وإضافة الرصيد — '.$receipt->stockItem?->name.'.']);
    }

    public function rejectReceipt(Request $request, StockReceiptRequest $receiptRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->approvals->rejectReceipt($receiptRequest, Auth::user(), $data['reason']);

        return response()->json(['message' => 'تم رفض طلب الاستلام.']);
    }
}
