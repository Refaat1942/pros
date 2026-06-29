<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AddPriceBatchRequest;
use App\Http\Requests\Stock\StoreCatalogItemRequest;
use App\Http\Requests\Stock\UpdateCatalogItemRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\StockCatalogService;
use App\Services\StockImportService;
use App\Services\StockItemSalesStatsService;
use App\Services\StockPriceService;
use App\Support\ExportCsvFormat;
use App\Support\Barcode\Code128;
use App\Traits\PaginationTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockCatalogController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly StockPriceService $stockPriceService,
        private readonly StockCatalogService $catalogService,
        private readonly StockItemSalesStatsService $salesStatsService,
    ) {
    }

    /**
     * قائمة الأصناف — مرشَّحة ومُنسَّقة للوحة الإدارة.
     */
    public function index(Request $request): JsonResponse
    {
        $range = $this->catalogService->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        $query = StockItem::query()
            ->with(['category:id,name', 'prices.supplier:id,name', 'suppliers:id,name'])
            ->when($range['from'], fn ($q, Carbon $start) => $q->where('created_at', '>=', $start))
            ->when($range['to'], fn ($q, Carbon $end) => $q->where('created_at', '<=', $end))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            }))
            ->orderByDesc('id');

        $items = $this->fetchForDashboard($query);

        return response()->json([
            'data'  => $items->map(fn (StockItem $item) => $this->catalogService->formatItem($item))->values(),
            'total' => $items->count(),
        ]);
    }

    /**
     * إنشاء صنف جديد مع أسعار الموردين.
     */
    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $item = $this->catalogService->create($request->validated());

        return response()->json([
            'message' => 'تم حفظ الصنف — يظهر في لوحة الإدارة والمخزون وتوصيات الطبيب',
            'item'    => $this->catalogService->formatItem($item),
        ], 201);
    }

    /**
     * تعديل الصنف وأسعاره.
     */
    public function update(UpdateCatalogItemRequest $request, StockItem $stockItem): JsonResponse
    {
        $item = $this->catalogService->update($stockItem, $request->validated());

        return response()->json([
            'message' => 'تم تحديث الصنف بنجاح',
            'item'    => $this->catalogService->formatItem($item),
        ]);
    }

    /**
     * حذف صنف من الكatalog.
     */
    public function destroy(StockItem $stockItem): JsonResponse
    {
        try {
            $this->catalogService->delete($stockItem);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'تم حذف الصنف بنجاح']);
    }

    /**
     * إضافة دفعة سعر شراء → يُعيد حساب WAC تلقائياً عبر StockPriceService.
     */
    public function addPrice(AddPriceBatchRequest $request, StockItem $stockItem): JsonResponse
    {
        $supplier = Supplier::findOrFail($request->supplier_id);

        $batch = $this->stockPriceService->addBatch(
            item:       $stockItem,
            qty:        $request->qty,
            unitPrice:  (float) $request->unit_price,
            supplier:   $supplier,
            invoiceNo:  $request->invoice_no,
            receivedAt: Carbon::parse($request->received_at),
        );

        $stockItem->refresh();

        return response()->json([
            'batch'    => $batch,
            'item_wac' => $stockItem->wac,
        ], 201);
    }

    /**
     * تنزيل قالب CSV للرفع الجماعي (السمات الأساسية فقط).
     */
    public function template(StockImportService $importService): StreamedResponse
    {
        $contents = $importService->templateContents();
        $filename = 'قالب-الأصناف.csv';

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * تصدير الأصناف الحالية إلى CSV (متوافق مع القالب والرفع الجماعي).
     */
    public function export(Request $request, StockImportService $importService): StreamedResponse
    {
        $contents = $importService->exportContents(
            $this->catalogService->listForDashboard(
                $request->query('from'),
                $request->query('to'),
            )
        );
        $filename = 'الأصناف_والأسعار-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * الرفع الجماعي بالإكسيل/CSV — upsert حسب الكود.
     */
    public function import(Request $request, StockImportService $importService): RedirectResponse|JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.required' => 'يرجى اختيار ملف CSV.',
            'file.mimes'    => 'الملف يجب أن يكون بصيغة CSV.',
        ]);

        $summary = $importService->import($request->file('file'));

        $message = "تم الاستيراد: {$summary['created']} صنف جديد، {$summary['updated']} محدَّث، {$summary['skipped']} متخطّى.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'summary' => $summary,
                'items'   => $this->catalogService->listForDashboard()->values(),
            ]);
        }

        return back()->with('status', $message)->with('import_errors', $summary['errors']);
    }

    /**
     * صفحة طباعة باركود حراري — ملصقان جنباً إلى جنب (38mm × 25mm).
     */
    public function labels(StockItem $stockItem, Request $request): Response
    {
        $copies = max(1, min(200, (int) $request->integer('copies', 2)));

        return response()->view('admin.print.barcode-labels', [
            'item'      => $stockItem,
            'copies'    => $copies,
            'barcodeSvg' => Code128::svg($stockItem->barcode, height: 44, moduleWidth: 1.1),
        ]);
    }

    /**
     * إحصائيات البيع حسب مستوى السعر لصنف واحد (حالات مُسلَّمة).
     */
    public function salesStats(StockItem $stockItem, Request $request): JsonResponse
    {
        $range = $this->salesStatsService->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        return response()->json(
            $this->salesStatsService->breakdownForItem($stockItem, $range['from'], $range['to'])
        );
    }

    /**
     * تقرير شامل — كل الأصناف × مستويات الأسعار.
     */
    public function salesByPrice(Request $request): JsonResponse
    {
        $range = $this->salesStatsService->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        $rows = $this->salesStatsService->report(
            $range['from'],
            $range['to'],
            $request->query('search'),
        );

        return response()->json([
            'data'          => $rows,
            'total'         => count($rows),
            'period_label'  => $this->salesStatsService->exportReport($range['from'], $range['to'])['period_label'],
            'date_from'     => $range['from']?->toDateString(),
            'date_to'       => $range['to']?->toDateString(),
        ]);
    }

    /**
     * تصدير CSV — إحصائيات البيع حسب مستوى السعر.
     */
    public function exportSalesByPrice(Request $request): StreamedResponse
    {
        $range = $this->salesStatsService->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        $report = $this->salesStatsService->exportReport(
            $range['from'],
            $range['to'],
            $request->query('search'),
        );

        $filename = 'sales-by-price-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($report) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [$report['title']]);
            fputcsv($out, [$report['period_label']]);
            fputcsv($out, []);
            fputcsv($out, ExportCsvFormat::row($report['headers']));
            foreach ($report['rows'] as $row) {
                fputcsv($out, ExportCsvFormat::row($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
