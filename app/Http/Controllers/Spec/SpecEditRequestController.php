<?php

namespace App\Http\Controllers\Spec;

use App\Exceptions\InvalidSpecItemException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Spec\StoreSpecEditRequestRequest;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Services\SpecEditRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SpecEditRequestController extends Controller
{
    public function __construct(private readonly SpecEditRequestService $editService)
    {
    }

    /**
     * سياق التعديل — البنود الحالية + الكatalog + حالة الطلب.
     */
    public function show(TechOrderSpec $spec): JsonResponse
    {
        abort_unless($spec->locked, 422, 'التوصيف لم يُرسَل بعد.');

        $spec->load(['items', 'caseRecord', 'pendingEditRequest', 'rejectedSpecEditRequest']);

        $stockCatalog = StockItem::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'spec', 'qty', 'reserved', 'uom'])
            ->map(fn ($item) => [
                'code'          => $item->code,
                'name'          => $item->name,
                'spec'          => $item->spec,
                'uom'           => $item->uom,
                'available_max' => $item->availableQty(),
            ]);

        return response()->json([
            'spec'              => $spec->only(['id', 'order_ref', 'case_id', 'patient_name', 'tech_notes', 'locked']),
            'items'             => $spec->items->map->only(['stock_item_code', 'name', 'qty']),
            'can_request_edit'  => $this->editService->canRequestEdit($spec),
            'pending_request'   => $spec->pendingEditRequest
                ? $this->editService->format($spec->pendingEditRequest)
                : null,
            'rejected_request'  => $spec->rejectedSpecEditRequest
                ? $this->editService->format($spec->rejectedSpecEditRequest)
                : null,
            'case_stage'        => $spec->caseRecord?->stage_key,
            'stock_catalog'     => $stockCatalog,
            'rejection_reasons' => config('spec_edit.rejection_reasons', []),
        ]);
    }

    public function store(StoreSpecEditRequestRequest $request, TechOrderSpec $spec): JsonResponse
    {
        try {
            $editRequest = $this->editService->submit(
                $spec,
                Auth::user(),
                $request->validated('items'),
                $request->validated('tech_notes'),
            );
        } catch (InvalidSpecItemException $e) {
            return response()->json([
                'message'         => $e->getMessage(),
                'stock_item_code' => $e->stockItemCode,
            ], 422);
        }

        return response()->json([
            'message' => 'تم إرسال طلب التعديل للإدارة — بانتظار الموافقة.',
            'request' => $this->editService->format($editRequest),
        ], 201);
    }
}
