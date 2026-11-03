<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\StockReceiptRequest;
use App\Models\StockSwapRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\StockApprovalService;
use App\Services\StockSwapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** «اعتمادات المخزن» — طلبات التوريد واستلام الوارد بانتظار أدمن المخازن. */
class StockApprovalController extends Controller
{
    public function __construct(
        private readonly StockApprovalService $approvals,
        private readonly StockSwapService $swaps,
    ) {}

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json($this->approvals->pending() + [
            'swaps' => $this->swaps->pending(),
            'can_manage_swaps' => $user->can(StockSwapService::PERMISSION),
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

    public function storeSwap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'out_stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'out_qty' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'in_stock_item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'new_item' => ['nullable', 'array'],
            'new_item.name' => ['nullable', 'string', 'max:255'],
            'new_item.uom' => ['nullable', 'string', 'max:50'],
            'new_item.brand' => ['nullable', 'string', 'max:255'],
            'new_item.spec' => ['nullable', 'string', 'max:1000'],
            'in_qty' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'price_difference' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $swap = $this->swaps->request($data, Auth::user());

        return response()->json([
            'message' => "تم تسجيل طلب التبديل {$swap->swap_no} — بانتظار اعتماد أدمن المخازن (لسه ماتخصمش ولا اتضاف حاجة).",
            'swap_no' => $swap->swap_no,
        ], 201);
    }

    public function approveSwap(StockSwapRequest $swapRequest): JsonResponse
    {
        $swap = $this->swaps->approve($swapRequest, Auth::user());

        return response()->json(['message' => "تم اعتماد التبديل {$swap->swap_no}: اتخصم «{$swap->outItem?->name}» واتضاف «{$swap->inItem?->name}» للمخزون."]);
    }

    public function rejectSwap(Request $request, StockSwapRequest $swapRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $swap = $this->swaps->reject($swapRequest, Auth::user(), $data['reason']);

        return response()->json(['message' => "تم رفض طلب التبديل {$swap->swap_no}."]);
    }
}
