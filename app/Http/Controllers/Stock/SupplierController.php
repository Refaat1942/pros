<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreSupplierRequest;
use App\Http\Requests\Stock\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\SupplierService;
use App\Support\ExportCsvFormat;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly SupplierService $supplierService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $suppliers = Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['data' => $suppliers]);
        }

        $suppliers = $this->supplierService->listForAdmin(
            search: $request->string('search')->toString() ?: null,
            from: $request->string('from')->toString() ?: null,
            to: $request->string('to')->toString() ?: null,
            debtFilter: $request->string('debt')->toString() ?: null,
        );

        return response()->json([
            'data'  => $suppliers,
            'total' => $suppliers->count(),
        ]);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['debt', 'stockItems:id,code,name']);
        $this->supplierService->hydrateStats($supplier);

        return response()->json([
            'supplier' => $supplier,
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $data     = $request->validated();
        $itemIds  = $data['stock_item_ids'] ?? [];
        unset($data['stock_item_ids']);

        $supplier = Supplier::create($data);

        if ($itemIds !== []) {
            $this->supplierService->syncStockItems($supplier, $itemIds);
        }

        AuditService::log(
            action:      'create',
            description: "إضافة مورد {$supplier->name}",
            tag:         'admin',
            after:       $supplier->fresh(['debt'])?->toArray(),
        );

        if ($request->expectsJson()) {
            return response()->json($this->supplierService->hydrateStats($supplier->fresh(['debt', 'stockItems'])), 201);
        }

        return redirect()
            ->route('admin.suppliers')
            ->with('success', "تم إضافة المورد «{$supplier->name}» بنجاح.");
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $before = $supplier->only([
            'name', 'phone', 'fax', 'email', 'address',
            'tax_number', 'commercial_registry',
            'bank_name', 'bank_branch', 'bank_account', 'iban', 'notes',
        ]);

        $data    = $request->validated();
        $itemIds = array_key_exists('stock_item_ids', $data) ? ($data['stock_item_ids'] ?? []) : null;
        unset($data['stock_item_ids']);

        $supplier->update($data);

        if ($itemIds !== null) {
            $this->supplierService->syncStockItems($supplier, $itemIds);
        }

        AuditService::log(
            action:      'update',
            description: "تعديل مورد {$supplier->name}",
            tag:         'admin',
            before:      $before,
            after:       $supplier->fresh()->only(array_keys($before)),
        );

        $supplier = $this->supplierService->hydrateStats($supplier->fresh(['debt', 'stockItems']));

        return response()->json([
            'message'  => 'تم تحديث المورد بنجاح.',
            'supplier' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $reason = $this->supplierService->deleteReason($supplier);

        if ($reason !== null) {
            return response()->json(['message' => $reason], 422);
        }

        $before = $supplier->only(['name']);
        $supplier->delete();

        AuditService::log(
            action:      'delete',
            description: "حذف مورد (soft): {$before['name']}",
            tag:         'admin',
            before:      $before,
        );

        return response()->json(['message' => 'تم حذف المورد بنجاح.']);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->supplierService->exportReport(
            search: $request->string('search')->toString() ?: null,
            from: $request->string('from')->toString() ?: null,
            to: $request->string('to')->toString() ?: null,
            debtFilter: $request->string('debt')->toString() ?: null,
        );

        $filename = 'الموردون_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [$report['title']]);
            fputcsv($out, [$report['period_label']]);
            fputcsv($out, []);
            fputcsv($out, ExportCsvFormat::row($report['headers']));
            foreach ($report['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
