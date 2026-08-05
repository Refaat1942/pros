<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockItem;
use App\Models\StockKit;
use App\Services\StockKitService;
use App\Support\StockKitGroups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $limit = min(60, max(10, (int) $request->input('limit', 40)));

        $query = StockItem::query();

        if ($q !== '') {
            $like = '%'.$q.'%';
            $prefix = $q.'%';
            $query->where(function ($builder) use ($like, $prefix) {
                $builder->where('name', 'like', $like)
                    ->orWhere('name', 'like', $prefix)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('alt_codes', 'like', $like)
                    ->orWhere('page_number', 'like', $like);

                if (Schema::hasColumn('stock_items', 'catalog_number')) {
                    $builder->orWhere('catalog_number', 'like', $like);
                }
            });

            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->orderByRaw('CASE WHEN name ILIKE ? THEN 0 WHEN name ILIKE ? THEN 1 ELSE 2 END', [$prefix, $like]);
            } else {
                $query->orderBy('name');
            }
        } else {
            $query->orderBy('name');
        }

        $items = $query
            ->limit($limit)
            ->get($this->searchItemColumns())
            ->map(fn (StockItem $item) => [
                'id' => $item->id,
                'code' => $item->pickerCode(),
                'catalog_number' => $item->catalog_number ?? $item->code,
                'alt_codes' => $item->alt_codes ?? '',
                'name' => $item->name,
                'page_number' => $item->page_number ?? '',
                'uom' => $item->uom ?? 'قطعة',
            ]);

        return response()->json(['data' => $items->values()]);
    }

    public function templates(): JsonResponse
    {
        return response()->json([
            'groups' => StockKitGroups::forSelect(),
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:32'],
            'previous_key' => ['nullable', 'string', 'max:32'],
            'label' => ['required', 'string', 'max:64'],
            'icon' => ['nullable', 'string', 'max:8'],
            'default_type' => ['nullable', 'string', 'in:assembly,accessory'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:64'],
        ]);

        StockKitGroups::upsertGroup(
            $validated['key'],
            [
                'label' => $validated['label'],
                'icon' => $validated['icon'] ?? '📦',
                'default_type' => $validated['default_type'] ?? 'assembly',
                'keywords' => $validated['keywords'] ?? [],
            ],
            $validated['previous_key'] ?? null,
        );

        return response()->json([
            'message' => 'تم حفظ المجموعة.',
            'groups' => StockKitGroups::forSelect(),
        ]);
    }

    public function destroyGroup(string $groupKey): JsonResponse
    {
        StockKitGroups::deleteGroup($groupKey);

        return response()->json([
            'message' => 'تم حذف المجموعة.',
            'groups' => StockKitGroups::forSelect(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'in:assembly,accessory'],
            'spec_group' => ['nullable', 'string', 'max:32'],
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
            'spec_group' => ['nullable', 'string', 'max:32'],
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

    /**
     * @return list<string>
     */
    private function searchItemColumns(): array
    {
        $columns = ['id', 'code', 'name', 'alt_codes', 'page_number', 'uom'];
        if (Schema::hasColumn('stock_items', 'catalog_number')) {
            $columns[] = 'catalog_number';
        }

        return $columns;
    }
}
