<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ReceiveStockRequest;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockReceiveService;
use App\Traits\PaginationTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * استلام مخزون — لوحة التقنية (بدون أسعار شراء أو WAC في الاستجابة).
 */
class StockReceiveController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly StockReceiveService $stockReceiveService) {}

    /**
     * كتالوج المخزون — الكميات والتوفر فقط (بدون WAC أو أسعار).
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->fetchForDashboard(
            StockItem::query()
                ->with('category:id,name')
                ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
                ->when($request->category, fn ($q, $c) => $q->whereHas('category', fn ($q) => $q->where('name', $c)))
                ->when($request->store_class, fn ($q, $s) => $q->where('store_class', $s))
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                }))
                ->orderBy('code')
        );

        return response()->json([
            'data' => collect($items)->map(fn ($item) => $this->formatItem($item))->values(),
            'total' => $items->count(),
        ]);
    }

    /**
     * استلام بضاعة وارد — يستدعي StockReceiveService::receive().
     */
    public function receive(ReceiveStockRequest $request): JsonResponse
    {
        $item = StockItem::findOrFail($request->validated('stock_item_id'));
        $supplier = Supplier::findOrFail($request->validated('supplier_id'));

        /** @var User $user */
        $user = Auth::user();

        $movement = $this->stockReceiveService->receive(
            item: $item,
            qty: (int) $request->validated('qty'),
            unitPrice: (float) $request->validated('unit_price'),
            supplier: $supplier,
            invoiceNo: $request->validated('invoice_no'),
            movedAt: Carbon::parse($request->validated('moved_at')),
            performedBy: $user,
        );

        return response()->json([
            'message' => 'تم استلام البضاعة بنجاح.',
            'movement' => $this->formatMovement($movement),
            'item' => $this->formatItem($movement->stockItem),
        ], 201);
    }

    /**
     * سجل حركات صنف — بدون unit_cost (عزل بيانات التقنية).
     */
    public function movements(Request $request, StockItem $stockItem): JsonResponse
    {
        $movements = $this->fetchForDashboard(
            StockMovement::with(['supplier:id,name', 'performedBy:id,name'])
                ->where('stock_item_id', $stockItem->id)
                ->when($request->movement_type, fn ($q, $t) => $q->where('movement_type', $t))
                ->orderByDesc('moved_at')
                ->orderByDesc('id')
        );

        return response()->json([
            'item' => $this->formatItem($stockItem),
            'data' => collect($movements)->map(fn ($m) => $this->formatMovement($m))->values(),
            'total' => $movements->count(),
        ]);
    }

    private function formatItem(StockItem $item): array
    {
        return $item->only([
            'id',
            'code',
            'name',
            'spec',
            'category_id',
            'store_class',
            'uom',
            'barcode',
            'qty',
            'reserved',
            'min_qty',
            'last_moved_at',
        ]) + [
            'category' => $item->category?->name,
            'available' => $item->availableQty(),
            'backorder' => $item->backorderQty(),
            'status' => $item->isBackorder() ? 'backorder' : $item->status,
        ];
    }

    private function formatMovement(StockMovement $movement): array
    {
        return $movement->only([
            'id',
            'stock_item_id',
            'movement_type',
            'quantity',
            'balance_after',
            'invoice_no',
            'moved_at',
        ]) + [
            'supplier' => $movement->supplier?->only(['id', 'name']),
            'performed_by' => $movement->performedBy?->only(['id', 'name']),
        ];
    }
}
