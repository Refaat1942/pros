<?php

namespace App\Http\Controllers\Adjustments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adjustments\StoreAdjustmentItemGroupRequest;
use App\Http\Requests\Adjustments\UpdateAdjustmentItemGroupRequest;
use App\Models\AdjustmentItemGroup;
use App\Models\StockItem;
use App\Services\AdjustmentItemGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdjustmentItemGroupController extends Controller
{
    public function __construct(
        private readonly AdjustmentItemGroupService $groups,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->groups->listForDesk()->values(),
        ]);
    }

    public function store(StoreAdjustmentItemGroupRequest $request): JsonResponse
    {
        $group = $this->groups->create($request->validated());

        return response()->json([
            'message' => 'تم حفظ المجموعة.',
            'group' => $this->groups->formatGroup($group),
        ], 201);
    }

    public function update(UpdateAdjustmentItemGroupRequest $request, AdjustmentItemGroup $adjustmentItemGroup): JsonResponse
    {
        $group = $this->groups->update($adjustmentItemGroup, $request->validated());

        return response()->json([
            'message' => 'تم تحديث المجموعة.',
            'group' => $this->groups->formatGroup($group),
        ]);
    }

    public function destroy(AdjustmentItemGroup $adjustmentItemGroup): JsonResponse
    {
        $this->groups->delete($adjustmentItemGroup);

        return response()->json([
            'message' => 'تم حذف المجموعة.',
        ]);
    }

    /**
     * بحث أصناف لبناء مجموعة (نفس منطق الكاتلوج التشغيلي — بدون أطقم).
     */
    public function searchItems(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = min(40, max(5, (int) $request->input('limit', 25)));

        $query = StockItem::query()->orderBy('name');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like, $q) {
                $builder->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('alt_codes', 'like', $like);
                if (strlen($q) >= 3) {
                    $builder->orWhere('barcode', $q);
                }
            });
        }

        $rows = $query->limit($limit)->get(['id', 'code', 'name', 'uom']);

        return response()->json([
            'data' => $rows->map(fn (StockItem $item) => [
                'code' => $item->code,
                'name' => $item->name,
                'uom' => $item->uom ?? 'قطعة',
            ])->values(),
        ]);
    }
}
