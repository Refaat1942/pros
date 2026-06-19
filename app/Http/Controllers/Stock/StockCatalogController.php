<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AddPriceBatchRequest;
use App\Http\Requests\Stock\StoreStockItemRequest;
use App\Http\Requests\Stock\UpdateStockItemRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\StockPriceService;
use App\Traits\PaginationTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCatalogController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly StockPriceService $stockPriceService)
    {
    }

    /**
     * قائمة الأصناف — مرشَّحة ومُرقَّمة.
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->fetchForDashboard(
            StockItem::query()
                ->when($request->category, fn ($q, $c) => $q->where('category', $c))
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
            'data'  => $items,
            'total' => $items->count(),
        ]);
    }

    /**
     * إنشاء صنف جديد (بدون رصيد — الرصيد يأتي من Task 08 StockReceiveService).
     */
    public function store(StoreStockItemRequest $request): JsonResponse
    {
        $item = StockItem::create($request->validated());

        AuditService::log(
            action:      'create',
            description: "إضافة صنف جديد {$item->code} — {$item->name}",
            tag:         'warehouse',
            after:       $item->toArray(),
        );

        return response()->json($item, 201);
    }

    /**
     * تعديل الحقول غير المالية للصنف.
     * code و barcode غير قابلَين للتعديل بعد الإنشاء (سلامة الباركود والتسعير).
     */
    public function update(UpdateStockItemRequest $request, StockItem $stockItem): JsonResponse
    {
        $before = $stockItem->only(['name', 'spec', 'category', 'store_class', 'uom']);

        $stockItem->update($request->validated());

        AuditService::log(
            action:      'update',
            description: "تعديل صنف {$stockItem->code}",
            tag:         'warehouse',
            before:      $before,
            after:       $stockItem->only(['name', 'spec', 'category', 'store_class', 'uom']),
        );

        return response()->json($stockItem);
    }

    /**
     * إضافة دفعة سعر شراء → يُعيد حساب WAC تلقائياً عبر StockPriceService.
     */
    public function addPrice(AddPriceBatchRequest $request, StockItem $stockItem): JsonResponse
    {
        $supplier = Supplier::findOrFail($request->supplier_id);

        $batch = $this->stockPriceService->addBatch(
            item:       $stockItem,
            qty:        $request->qty,
            unitPrice:  (float) $request->unit_price,
            supplier:   $supplier,
            invoiceNo:  $request->invoice_no,
            receivedAt: Carbon::parse($request->received_at),
        );

        $stockItem->refresh();

        return response()->json([
            'batch'    => $batch,
            'item_wac' => $stockItem->wac,
        ], 201);
    }
}
