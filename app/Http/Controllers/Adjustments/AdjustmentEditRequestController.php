<?php

namespace App\Http\Controllers\Adjustments;

use App\Exceptions\InvalidSpecItemException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adjustments\StoreAdjustmentEditRequestRequest;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Services\SpecEditRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdjustmentEditRequestController extends Controller
{
    public function __construct(private readonly SpecEditRequestService $editService) {}

    public function show(CaseRecord $case): JsonResponse
    {
        abort_unless($case->isInCostCalc(), 422, 'تعديل بنود المعدلات متاح فقط قبل تأكيد التكاليف.');

        $case->load(['bom.items', 'pendingAdjustmentEditRequest']);

        $adjustmentItems = collect($case->bom?->items ?? [])
            ->where('source', BomItem::SOURCE_ADJUSTMENT)
            ->map(fn (BomItem $i) => $i->only(['stock_item_code', 'name', 'qty']))
            ->values();

        $stockCatalog = StockItem::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'qty', 'reserved'])
            ->map(fn (StockItem $item) => [
                'code' => $item->code,
                'name' => $item->name,
                'qty' => (int) $item->qty,
                'reserved' => (int) $item->reserved,
                'available' => $item->availableQty(),
            ]);

        return response()->json([
            'case_id' => $case->id,
            'items' => $adjustmentItems,
            'can_request_edit' => $this->editService->canRequestAdjustmentEdit($case),
            'pending_request' => $case->pendingAdjustmentEditRequest
                ? $this->editService->format($case->pendingAdjustmentEditRequest)
                : null,
            'stock_catalog' => $stockCatalog,
        ]);
    }

    public function store(StoreAdjustmentEditRequestRequest $request, CaseRecord $case): JsonResponse
    {
        try {
            $editRequest = $this->editService->submitAdjustmentEdit(
                $case,
                Auth::user(),
                $request->validated('items'),
            );
        } catch (InvalidSpecItemException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'stock_item_code' => $e->stockItemCode,
            ], 422);
        }

        return response()->json([
            'message' => 'تم إرسال طلب تعديل بنود المعدلات للإدارة — بانتظار الموافقة.',
            'request' => $this->editService->format($editRequest),
        ], 201);
    }
}
