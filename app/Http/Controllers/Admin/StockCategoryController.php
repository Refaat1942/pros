<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockCategoryRequest;
use App\Http\Requests\Admin\UpdateStockCategoryRequest;
use App\Models\StockCategory;
use App\Services\AuditService;
use App\Services\StockCategorySchemaService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockCategoryController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly StockCategorySchemaService $schema)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockCategory::query()->with('fields')->orderByDesc('id');

        if ($request->boolean('all') || $request->boolean('with_fields')) {
            $categories = $query->get()->map(fn (StockCategory $c) => $this->schema->formatCategory($c));

            return response()->json(['data' => $categories]);
        }

        $categories = $this->fetchForDashboard(
            StockCategory::when(
                $request->search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
            )
                ->with('fields')
                ->orderByDesc('id')
        )->map(fn (StockCategory $c) => $this->schema->formatCategory($c));

        return response()->json([
            'data'  => $categories,
            'total' => $categories->count(),
        ]);
    }

    public function store(StoreStockCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $category = StockCategory::create(['name' => $data['name']]);
        $this->schema->syncFields($category, $data['fields'] ?? []);

        AuditService::log(
            action:      'create',
            description: "إضافة قسم صنف: {$category->name}",
            tag:         'admin',
            after:       $this->schema->formatCategory($category->fresh('fields')),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message'        => "تم إضافة القسم «{$category->name}» بنجاح.",
                'stock_category' => $this->schema->formatCategory($category->fresh('fields')),
            ], 201);
        }

        return redirect()
            ->route('admin.stock-categories')
            ->with('success', "تم إضافة القسم «{$category->name}» بنجاح.");
    }

    public function update(UpdateStockCategoryRequest $request, StockCategory $stockCategory): JsonResponse
    {
        $data = $request->validated();
        $before = $this->schema->formatCategory($stockCategory->load('fields'));

        if (array_key_exists('name', $data)) {
            $stockCategory->update(['name' => $data['name']]);
        }

        if (array_key_exists('fields', $data)) {
            $this->schema->syncFields($stockCategory, $data['fields'] ?? []);
        }

        $fresh = $this->schema->formatCategory($stockCategory->fresh('fields'));

        AuditService::log(
            action:      'update',
            description: "تعديل قسم صنف: {$stockCategory->name}",
            tag:         'admin',
            before:      $before,
            after:       $fresh,
        );

        return response()->json([
            'message'         => 'تم تحديث القسم بنجاح.',
            'stock_category'  => $fresh,
        ]);
    }

    public function destroy(StockCategory $stockCategory): JsonResponse
    {
        if ($stockCategory->stockItems()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف القسم — مرتبط بأصناف مسجّلة.',
            ], 422);
        }

        $before = $stockCategory->only(['name']);
        $stockCategory->delete();

        AuditService::log(
            action:      'delete',
            description: "حذف قسم صنف: {$before['name']}",
            tag:         'admin',
            before:      $before,
        );

        return response()->json(['message' => 'تم حذف القسم بنجاح.']);
    }
}
