<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockCategoryRequest;
use App\Http\Requests\Admin\UpdateStockCategoryRequest;
use App\Models\StockCategory;
use App\Services\AuditService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * إدارة فئات الأصناف — تُستخدم في catalog الأصناف والأسعار.
 */
class StockCategoryController extends Controller
{
    use PaginationTrait;

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $categories = StockCategory::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['data' => $categories]);
        }

        $categories = $this->fetchForDashboard(
            StockCategory::when(
                $request->search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
            )
                ->orderByDesc('id')
        );

        return response()->json([
            'data'  => $categories,
            'total' => $categories->count(),
        ]);
    }

    public function store(StoreStockCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $category = StockCategory::create([
            'name' => $data['name'],
        ]);

        AuditService::log(
            action:      'create',
            description: "إضافة فئة صنف: {$category->name}",
            tag:         'admin',
            after:       $category->toArray(),
        );

        if ($request->expectsJson()) {
            return response()->json($category, 201);
        }

        return redirect()
            ->route('admin.stock-categories')
            ->with('success', "تم إضافة الفئة «{$category->name}» بنجاح.");
    }

    public function update(UpdateStockCategoryRequest $request, StockCategory $stockCategory): JsonResponse
    {
        $data = $request->validated();

        $before = $stockCategory->only(['name']);
        $stockCategory->update($data);

        AuditService::log(
            action:      'update',
            description: "تعديل فئة صنف: {$stockCategory->name}",
            tag:         'admin',
            before:      $before,
            after:       $stockCategory->fresh()->only(['name']),
        );

        return response()->json([
            'message'         => 'تم تحديث الفئة بنجاح.',
            'stock_category'  => $stockCategory->fresh(),
        ]);
    }

    public function destroy(StockCategory $stockCategory): JsonResponse
    {
        if ($stockCategory->stockItems()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الفئة — مرتبطة بأصناف مسجّلة.',
            ], 422);
        }

        $before = $stockCategory->only(['name']);
        $stockCategory->delete();

        AuditService::log(
            action:      'delete',
            description: "حذف فئة صنف: {$before['name']}",
            tag:         'admin',
            before:      $before,
        );

        return response()->json(['message' => 'تم حذف الفئة بنجاح.']);
    }
}
