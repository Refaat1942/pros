<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AddPriceBatchRequest;
use App\Http\Requests\Stock\StoreCatalogItemRequest;
use App\Http\Requests\Stock\UpdateCatalogItemRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\StockCatalogService;
use App\Services\StockPriceService;
use App\Traits\PaginationTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCatalogController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly StockPriceService $stockPriceService,
        private readonly StockCatalogService $catalogService,
    ) {
    }

    /**
     * قائمة الأصناف — مرشَّحة ومُنسَّقة للوحة الإدارة.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockItem::query()
            ->with(['category:id,name', 'prices.supplier:id,name'])
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            }))
            ->orderByDesc('id');

        $items = $this->fetchForDashboard($query);

        return response()->json([
            'data'  => $items->map(fn (StockItem $item) => $this->catalogService->formatItem($item))->values(),
            'total' => $items->count(),
        ]);
    }

    /**
     * إنشاء صنف جديد مع أسعار الموردين.
     */
    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $item = $this->catalogService->create($request->validated());

        return response()->json([
            'message' => 'تم حفظ الصنف — يظهر في لوحة الإدارة والمخزون وتوصيات الطبيب',
            'item'    => $this->catalogService->formatItem($item),
        ], 201);
    }

    /**
     * تعديل الصنف وأسعاره.
     */
    public function update(UpdateCatalogItemRequest $request, StockItem $stockItem): JsonResponse
    {
        $item = $this->catalogService->update($stockItem, $request->validated());

        return response()->json([
            'message' => 'تم تحديث الصنف بنجاح',
            'item'    => $this->catalogService->formatItem($item),
        ]);
    }

    /**
     * حذف صنف من الكatalog.
     */
    public function destroy(StockItem $stockItem): JsonResponse
    {
        try {
            $this->catalogService->delete($stockItem);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'تم حذف الصنف بنجاح']);
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
