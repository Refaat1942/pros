<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ReceiveStockRequest;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CatalogListVisibilityService;
use App\Services\StockCatalogService;
use App\Models\SupplyRequestLine;
use App\Services\StockReceiveService;
use App\Services\SupplyRequestService;
use App\Traits\PaginationTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $catalogService = app(StockCatalogService::class);
        $user = $request->user();
        $visibility = app(CatalogListVisibilityService::class);

        $items = $catalogService->allItemsForUnifiedLists();

        return response()->json([
            'data' => collect($catalogService->listForTechnicalInventory($user))->values(),
            'total' => $catalogService->countAll(),
            'columns' => $visibility->tableOrderForUser($user, 'technical_inventory'),
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

        $movement = DB::transaction(function () use ($request, $item, $supplier, $user) {
            $lineId = $request->validated('supply_request_line_id');

            $movement = $this->stockReceiveService->receive(
                item: $item,
                qty: (int) $request->validated('qty'),
                unitPrice: (float) $request->validated('unit_price'),
                supplier: $supplier,
                invoiceNo: $request->validated('invoice_no'),
                movedAt: Carbon::parse($request->validated('moved_at')),
                performedBy: $user,
                documentPath: $this->storeInboundDocument($request),
                documentOriginalName: $request->file('document')?->getClientOriginalName(),
                documentMime: $request->file('document')?->getClientMimeType(),
                supplyRequestLineId: $lineId ? (int) $lineId : null,
            );

            if ($lineId) {
                $line = SupplyRequestLine::query()->findOrFail($lineId);
                app(SupplyRequestService::class)->markLineReceived($line, $movement);
            }

            return $movement;
        });

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
        $catalogService = app(StockCatalogService::class);

        return [
            'id' => $item->id,
            'code' => $catalogService->displayCatalogCode($item),
            'internal_code' => $item->code,
            'name' => $item->name,
            'brand' => $item->brand,
            'spec' => $item->spec,
            'category_id' => $item->category_id,
            'store_class' => $item->store_class,
            'uom' => $item->uom,
            'barcode' => $item->barcode,
            'qty' => $item->qty,
            'reserved' => $item->reserved,
            'min_qty' => $item->min_qty,
            'last_moved_at' => $item->last_moved_at,
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
            'document_original_name',
            'moved_at',
        ]) + [
            'supplier' => $movement->supplier?->only(['id', 'name']),
            'performed_by' => $movement->performedBy?->only(['id', 'name']),
            'has_document' => (bool) $movement->document_path,
        ];
    }

    private function storeInboundDocument(ReceiveStockRequest $request): ?string
    {
        if (! config('inventory.inbound_document_upload', true)) {
            return null;
        }

        $file = $request->file('document');

        return $file ? $file->store('stock-invoices', 'local') : null;
    }
}
