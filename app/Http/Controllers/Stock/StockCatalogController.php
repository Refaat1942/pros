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
    ) {}

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
            'data' => $items->map(fn (StockItem $item) => $this->catalogService->formatItem($item))->values(),
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
            'item' => $this->catalogService->formatItem($item),
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
            'item' => $this->catalogService->formatItem($item),
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
            item: $stockItem,
            qty: $request->qty,
            unitPrice: (float) $request->unit_price,
            supplier: $supplier,
            invoiceNo: $request->invoice_no,
            receivedAt: Carbon::parse($request->received_at),
        );

        $stockItem->refresh();

        return response()->json([
            'batch' => $batch,
            'item_wac' => $stockItem->wac,
        ], 201);
    }

    /**
     * تنزيل قالب Excel للرفع الجماعي (مع تبويبات الموردين والأقسام).
     */
    public function template(StockImportService $importService): StreamedResponse
    {
        $contents = $importService->templateBinary();
        $filename = 'قالب-الأصناف.xlsx';

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * تصدير الأصناف الحالية إلى ملف Excel (متوافق مع القالب والرفع الجماعي).
     */
    public function export(Request $request, StockImportService $importService): StreamedResponse
    {
        $contents = $importService->exportBinary(
            $this->catalogService->listForDashboard(
                $request->query('from'),
                $request->query('to'),
            )
        );
        $filename = 'الأصناف_والأسعار-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * الرفع الجماعي بالإكسيل/CSV — upsert حسب الكود.
     */
    public function import(Request $request, StockImportService $importService): RedirectResponse|JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ], [
            'file.required' => 'يرجى اختيار ملف Excel أو CSV.',
            'file.mimes' => 'الملف يجب أن يكون بصيغة Excel (.xlsx) أو CSV.',
        ]);

        $summary = $importService->import($request->file('file'));

        $message = "تم الاستيراد: {$summary['created']} صنف جديد، {$summary['updated']} محدَّث، {$summary['skipped']} متخطّى.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'summary' => $summary,
                'items' => $this->catalogService->listForDashboard()->values(),
            ]);
        }

        return back()->with('status', $message)->with('import_errors', $summary['errors']);
    }

    /**
     * صفحة طباعة باركود حراري لصنف واحد — ملصقان جنباً إلى جنب (38mm × 25mm).
     */
    public function labels(StockItem $stockItem, Request $request): Response
    {
        $copies = max(1, min(200, (int) $request->integer('copies', 2)));
        $settings = $this->labelSettings($request);

        return response()->view('admin.print.barcode-labels', [
            'labels' => $this->buildLabels([$stockItem], $copies, $settings['module_width'], $settings['barcode_height']),
            'settings' => $settings,
            'heading' => $stockItem->name,
        ]);
    }

    /**
     * طباعة باركود لعدة أصناف دفعة واحدة — ids[] + عدد النسخ + إحداثيات قابلة للضبط.
     */
    public function labelsBulk(Request $request): Response
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->merge((array) $request->query('id', []))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter()
            ->unique()
            ->values();

        $items = StockItem::query()->whereIn('id', $ids)->orderBy('name')->get();
        $copies = max(1, min(200, (int) $request->integer('copies', 1)));
        $settings = $this->labelSettings($request);

        return response()->view('admin.print.barcode-labels', [
            'labels' => $this->buildLabels($items->all(), $copies, $settings['module_width'], $settings['barcode_height']),
            'settings' => $settings,
            'heading' => $items->count().' صنف',
        ]);
    }

    /**
     * إعدادات الطباعة القابلة للضبط (بالمليمتر إلا حيث يُذكر).
     *
     * @return array{page_margin:float, gap:float, module_width:float, barcode_height:int, offset_x:float, offset_y:float, copies:int}
     */
    private function labelSettings(Request $request): array
    {
        return [
            'page_margin' => round((float) $request->query('page_margin', '4'), 2),
            'gap' => round((float) $request->query('gap', '2'), 2),
            'module_width' => max(0.5, min(3.0, round((float) $request->query('module_width', '1.1'), 2))),
            'barcode_height' => max(20, min(80, (int) $request->integer('barcode_height', 44))),
            'offset_x' => round((float) $request->query('offset_x', '0'), 2),
            'offset_y' => round((float) $request->query('offset_y', '0'), 2),
            'copies' => max(1, min(200, (int) $request->integer('copies', 2))),
        ];
    }

    /**
     * يبني قائمة الملصقات (اسم + باركود + SVG) مع تكرار النسخ.
     *
     * @param  list<StockItem>  $items
     * @return list<array{name:string, barcode:string, svg:string}>
     */
    private function buildLabels(array $items, int $copies, float $moduleWidth, int $height): array
    {
        $labels = [];

        foreach ($items as $item) {
            $svg = Code128::svg((string) $item->barcode, height: $height, moduleWidth: $moduleWidth);
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = [
                    'name' => (string) $item->name,
                    'barcode' => (string) $item->barcode,
                    'svg' => $svg,
                ];
            }
        }

        return $labels;
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
}
