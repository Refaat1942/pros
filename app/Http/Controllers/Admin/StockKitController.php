<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockItem;
use App\Models\StockKit;
use App\Services\StockKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockKitController extends Controller
{
    public function __construct(
        private readonly StockKitService $kits,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->kits->listForAdmin()->values(),
            'stock_items' => StockItem::query()
                ->whereNotNull('alt_codes')
                ->where('alt_codes', '!=', '')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'alt_codes', 'uom'])
                ->map(fn (StockItem $item) => [
                    'id' => $item->id,
                    'code' => $item->operationalCode(),
                    'catalog_code' => $item->code,
                    'name' => $item->name,
                    'uom' => $item->uom,
                ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'in:assembly,accessory'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $kit = $this->kits->create($validated);

        return response()->json([
            'message' => 'تم حفظ الطقم.',
            'kit' => $this->kits->listForAdmin()->firstWhere('id', $kit->id),
        ], 201);
    }

    public function update(Request $request, StockKit $stockKit): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'type' => ['nullable', 'string', 'in:assembly,accessory'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.stock_item_id' => ['required_with:items', 'integer', 'exists:stock_items,id'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $kit = $this->kits->update($stockKit, $validated);

        return response()->json([
            'message' => 'تم تحديث الطقم.',
            'kit' => $this->kits->listForAdmin()->firstWhere('id', $kit->id),
        ]);
    }

    public function destroy(StockKit $stockKit): JsonResponse
    {
        $this->kits->delete($stockKit);

        return response()->json(['message' => 'تم حذف الطقم.']);
    }

    public function expand(string $code): JsonResponse
    {
        return response()->json([
            'items' => $this->kits->expandForSpec($code),
        ]);
    }
}
