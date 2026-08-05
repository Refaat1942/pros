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
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = min(50, max(5, (int) $request->input('limit', 30)));

        $query = StockItem::query()
            ->orderBy('name')
            ->limit($limit);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('catalog_number', 'like', $like)
                    ->orWhere('alt_codes', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('page_number', 'like', $like);
            });
        }

        $items = $query->get(['id', 'code', 'catalog_number', 'name', 'alt_codes', 'page_number', 'uom'])
            ->map(fn (StockItem $item) => [
                'id' => $item->id,
                'code' => $item->operationalCode() ?: ($item->catalog_number ?? $item->code),
                'catalog_number' => $item->catalog_number ?? $item->code,
                'alt_codes' => $item->alt_codes ?? '',
                'name' => $item->name,
                'page_number' => $item->page_number ?? '',
                'uom' => $item->uom ?? 'قطعة',
            ]);

        return response()->json(['data' => $items->values()]);
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
