<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreSupplierRequest;
use App\Http\Requests\Stock\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use PaginationTrait;

    /**
     * قائمة الموردين — للوحة الأدمن أو للـ select (?all=1).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $suppliers = Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['data' => $suppliers]);
        }

        $suppliers = $this->fetchForDashboard(
            Supplier::query()
                ->when(
                    $request->search,
                    fn ($q, $s) => $q->where(function ($q) use ($s) {
                        $q->where('name', 'like', "%{$s}%")
                          ->orWhere('phone', 'like', "%{$s}%")
                          ->orWhere('email', 'like', "%{$s}%");
                    })
                )
                ->orderByDesc('id')
        );

        return response()->json([
            'data'  => $suppliers,
            'total' => $suppliers->count(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $data     = $request->validated();
        $supplier = Supplier::create($data);

        AuditService::log(
            action:      'create',
            description: "إضافة مورد {$supplier->name}",
            tag:         'admin',
            after:       $supplier->toArray(),
        );

        if ($request->expectsJson()) {
            return response()->json($supplier, 201);
        }

        return redirect()
            ->route('admin.suppliers')
            ->with('success', "تم إضافة المورد «{$supplier->name}» بنجاح.");
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $before = $supplier->only(['name', 'phone', 'email', 'address', 'notes']);

        $supplier->update($request->validated());

        AuditService::log(
            action:      'update',
            description: "تعديل مورد {$supplier->name}",
            tag:         'admin',
            before:      $before,
            after:       $supplier->fresh()->only(['name', 'phone', 'email', 'address', 'notes']),
        );

        return response()->json([
            'message'  => 'تم تحديث المورد بنجاح.',
            'supplier' => $supplier->fresh(),
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->stockItemPrices()->exists() || $supplier->stockMovements()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف المورد — مرتبط بأسعار أو حركات مخزنية.',
            ], 422);
        }

        $before = $supplier->only(['name']);
        $supplier->delete();

        AuditService::log(
            action:      'delete',
            description: "حذف مورد: {$before['name']}",
            tag:         'admin',
            before:      $before,
        );

        return response()->json(['message' => 'تم حذف المورد بنجاح.']);
    }
}
