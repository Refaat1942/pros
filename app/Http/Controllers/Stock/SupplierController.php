<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreSupplierRequest;
use App\Http\Requests\Stock\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use PaginationTrait;

    /**
     * قائمة الموردين — مرشَّحة بالحالة.
     */
    public function index(Request $request): JsonResponse
    {
        $suppliers = $this->fetchForDashboard(
            Supplier::query()
                ->when(
                    $request->has('is_active'),
                    fn ($q) => $q->where('is_active', $request->boolean('is_active'))
                )
                ->when(
                    $request->search,
                    fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                )
                ->orderBy('name')
        );

        return response()->json([
            'data'  => $suppliers,
            'total' => $suppliers->count(),
        ]);
    }

    /**
     * إنشاء مورد جديد (نشط افتراضياً).
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data     = $request->validated();
        $supplier = Supplier::create(array_merge($data, ['is_active' => true]));

        AuditService::log(
            action:      'create',
            description: "إضافة مورد {$supplier->name}",
            tag:         'warehouse',
            after:       $supplier->toArray(),
        );

        return response()->json($supplier, 201);
    }

    /**
     * تعديل بيانات المورد.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $before = $supplier->toArray();

        $supplier->update($request->validated());

        AuditService::log(
            action:      'update',
            description: "تعديل مورد {$supplier->name}",
            tag:         'warehouse',
            before:      $before,
            after:       $supplier->toArray(),
        );

        return response()->json($supplier);
    }

    /**
     * تفعيل / تعطيل مورد.
     */
    public function toggleActive(Supplier $supplier): JsonResponse
    {
        $before = ['is_active' => $supplier->is_active];

        $supplier->update(['is_active' => ! $supplier->is_active]);

        AuditService::log(
            action:      'update',
            description: $supplier->is_active
                ? "تفعيل مورد {$supplier->name}"
                : "تعطيل مورد {$supplier->name}",
            tag:         'warehouse',
            before:      $before,
            after:       ['is_active' => $supplier->is_active],
        );

        return response()->json(['is_active' => $supplier->is_active]);
    }
}
