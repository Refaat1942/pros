<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AddPriceBatchRequest;
use App\Http\Requests\Stock\StoreCatalogItemRequest;
use App\Http\Requests\Stock\UpdateCatalogItemRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\CatalogListVisibilityService;
use App\Services\StockCatalogService;
use App\Services\StockImportService;
use App\Services\StockItemSalesStatsService;
use App\Services\StockPriceService;
use App\Support\CatalogImportValidator;
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
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            }))
            ->orderByDesc('id');

        $items = $this->fetchForDashboard($query);
        $user = $request->user();
        $visibility = app(CatalogListVisibilityService::class);

        return response()->json([
            'data' => $items->map(function (StockItem $item) use ($user, $visibility) {
                $formatted = $this->catalogService->formatItem($item);

                return $user
                    ? $visibility->filterItemFields($formatted, $user, 'admin_catalog')
                    : $formatted;
            })->values(),
            'total' => $items->count(),
            'columns' => $visibility->tableOrderForUser($user, 'admin_catalog'),
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
        $from = $request->query('from');
        $to = $request->query('to');

        $contents = $importService->exportBinary(
            $this->catalogService->listForExport($from, $to),
            includeInstructions: false,
        );
        $total = $this->catalogService->countAll($from, $to);
        $filename = 'الأصناف_والأسعار-'.now()->format('Y-m-d')."-{$total}.xlsx";

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
            'file' => ['required', 'file', 'max:5120'],
        ], [
            'file.required' => 'يرجى اختيار ملف Excel أو CSV.',
        ]);

        $uploaded = $request->file('file');
        if ($uploaded === null || ! CatalogImportValidator::isAllowed($uploaded)) {
            return $this->importValidationFailure($request, 'الملف يجب أن يكون بصيغة Excel (.xlsx) أو CSV.');
        }

        $summary = $importService->import($uploaded);

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
     * صفحة طباعة باركود حراري لصنف واحد — ملصق 25mm × 38mm (ملصق واحد لكل طباعة).
     */
    public function labels(StockItem $stockItem, Request $request): Response
    {
        $copies = max(1, min(200, (int) $request->integer('copies', 1)));
        $settings = $this->labelSettings($request);

        return response()->view('admin.print.barcode-labels', [
            'labels' => $this->buildLabels([$stockItem], $copies, $settings),
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
            'labels' => $this->buildLabels($items->all(), $copies, $settings),
            'settings' => $settings,
            'heading' => $items->count().' صنف',
        ]);
    }

    /**
     * إعدادات الطباعة القابلة للضبط (بالمليمتر إلا حيث يُذكر).
     *
     * @return array{
     *     page_margin:float, gap:float, module_width:float, barcode_height:int,
     *     offset_x:float, offset_y:float, copies:int,
     *     label_width_mm:float, label_height_mm:float,
     *     margin_left_mm:float, margin_top_mm:float,
     *     label_width_in:float, label_height_in:float,
     *     barcode_width_pct:float, barcode_height_pct:float,
     *     field_help: array<string, string>
     * }
     */
    private function labelSettings(Request $request): array
    {
        $defaults = config('label-print', []);

        $widthMm = max(10.0, round((float) $request->query(
            'label_width_mm',
            (string) ($defaults['label_width_mm'] ?? 38),
        ), 2));
        $heightMm = max(10.0, round((float) $request->query(
            'label_height_mm',
            (string) ($defaults['label_height_mm'] ?? 25),
        ), 2));

        return [
            'page_margin' => round((float) $request->query('page_margin', '0'), 2),
            'gap' => round((float) $request->query('gap', '0'), 2),
            'module_width' => max(0.5, min(3.0, round((float) $request->query(
                'module_width',
                (string) ($defaults['module_width'] ?? 1.5),
            ), 2))),
            'barcode_height' => max(16, min(80, (int) $request->integer(
                'barcode_height',
                (int) ($defaults['barcode_height'] ?? 34),
            ))),
            'barcode_width_pct' => max(20.0, min(95.0, round((float) $request->query(
                'barcode_width_pct',
                (string) ($defaults['barcode_width_pct'] ?? 65),
            ), 1))),
            'barcode_height_pct' => max(15.0, min(70.0, round((float) $request->query(
                'barcode_height_pct',
                (string) ($defaults['barcode_height_pct'] ?? 42),
            ), 1))),
            'offset_x' => round((float) $request->query('offset_x', '0'), 2),
            'offset_y' => round((float) $request->query('offset_y', '0'), 2),
            'copies' => max(1, min(200, (int) $request->integer('copies', 1))),
            'label_width_mm' => $widthMm,
            'label_height_mm' => $heightMm,
            'margin_left_mm' => round((float) $request->query(
                'margin_left_mm',
                (string) ($defaults['margin_left_mm'] ?? 0),
            ), 2),
            'margin_top_mm' => round((float) $request->query(
                'margin_top_mm',
                (string) ($defaults['margin_top_mm'] ?? 0),
            ), 2),
            'label_width_in' => round($widthMm / 25.4, 3),
            'label_height_in' => round($heightMm / 25.4, 3),
            'field_help' => (array) ($defaults['field_help'] ?? []),
        ];
    }

    /**
     * @param  list<StockItem>  $items
     * @param  array<string, mixed>  $settings
     * @return list<array{name:string, barcode:string, svg:string, svg_data_uri:string}>
     */
    private function buildLabels(array $items, int $copies, array $settings): array
    {
        $labels = [];
        $moduleWidth = (float) ($settings['module_width'] ?? 1.0);
        $widthPct = (float) ($settings['barcode_width_pct'] ?? 65);
        $heightPct = (float) ($settings['barcode_height_pct'] ?? 42);
        $labelWidthMm = (float) ($settings['label_width_mm'] ?? 38);
        $labelHeightMm = (float) ($settings['label_height_mm'] ?? 25);
        $dpi = 203;
        $maxWidthPx = ($labelWidthMm * ($widthPct / 100)) * ($dpi / 25.4);
        $maxHeightPx = (int) round(($labelHeightMm * ($heightPct / 100)) * ($dpi / 25.4));
        $barcodeHeight = min((int) ($settings['barcode_height'] ?? 28), max(16, $maxHeightPx));

        foreach ($items as $item) {
            $svg = Code128::svgFit(
                (string) $item->barcode,
                height: $barcodeHeight,
                moduleWidth: $moduleWidth,
                maxWidthPx: $maxWidthPx,
            );
            $dataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = [
                    'name' => (string) $item->name,
                    'barcode' => (string) $item->barcode,
                    'svg' => $svg,
                    'svg_data_uri' => $dataUri,
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

    private function importValidationFailure(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 422);
        }

        return back()->withErrors(['file' => $message]);
    }
}
