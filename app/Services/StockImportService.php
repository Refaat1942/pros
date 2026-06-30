<?php

namespace App\Services;

use App\Models\StockCategory;
use App\Models\StockCategoryField;
use App\Models\StockItem;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * الرفع الجماعي للأصناف — قالب Excel متعدد التبويبات + CSV للتوافق.
 *
 * الأعمدة (بالترتيب):
 * كود الصنf، اسم الصنf، الكمية، الحد الأدنى، السعر، معرف المورد، معرف القسم، قيم الخصائص.
 *
 * عمود السعر: أكثر من قيمة مفصولة بـ «;» أو «و» — الأول أساسي والباقي إضافي.
 * عمود قيم الخصائص: قيم مفصولة بـ «|» حسب ترتيب الحقول — اكتب الاسم المعروض من تبويب «خيارات الحقول».
 *   مثال: قماش Lamination|متر|#1E40AF
 *
 * تبويبات القالب: الأصناf، الموردون، الأقسام، حقول الأقسام، خيارات الحقول.
 */
class StockImportService
{
    public const SHEET_ITEMS = 'الأصناف';

    public const SHEET_SUPPLIERS = 'الموردون';

    public const SHEET_CATEGORIES = 'الأقسام';

    public const SHEET_FIELDS = 'حقول الأقسام';

    public const SHEET_FIELD_OPTIONS = 'خيارات الحقول';

    /** ترويسة ورقة الأصناف — تُستخدم للتنزيل والاستيراد. */
    public const HEADERS = [
        'كود الصنف',
        'اسم الصنف',
        'الكمية',
        'الحد الأدنى للصنف',
        'السعر',
        'معرف المورد',
        'معرف القسم',
        'قيم الخصائص',
    ];

    /** @var list<string> */
    private const LEGACY_HEADER_ALIASES = [
        'كود الصنف',
        'اسم الصنف',
        'المورد',
        'القسم',
        'خصائص القسم',
    ];

    public function __construct(private readonly StockCatalogService $catalogService)
    {
    }

    /**
     * يبني ملف Excel (.xlsx) جاهز للتنزيل — مع تبويبات المراجع.
     */
    public function templateBinary(): string
    {
        return $this->buildWorkbookBinary($this->buildExampleRows());
    }

    /**
     * يصدّر الأصناف الحالية إلى Excel بنفس هيكل القالب.
     *
     * @param  iterable<int, array<string, mixed>>  $items
     */
    public function exportBinary(iterable $items): string
    {
        $rows = [];

        foreach ($items as $item) {
            $supplierId = (string) (($item['suppliers'][0]['id'] ?? '') ?: '');
            $rows[] = [
                (string) ($item['code'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ((int) ($item['qty'] ?? 0)),
                (string) ((int) ($item['min_qty'] ?? 0)),
                $this->formatPriceColumn($item),
                $supplierId,
                (string) (($item['category_id'] ?? '') ?: ''),
                $this->formatAttributesColumn($item),
            ];
        }

        return $this->buildWorkbookBinary($rows);
    }

    /**
     * @return array{created:int, updated:int, skipped:int, errors:list<string>}
     */
    public function import(UploadedFile $file): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        $rows = $this->readRows($file);

        DB::transaction(function () use ($rows, &$created, &$updated, &$skipped, &$errors) {
            foreach ($rows as $lineNo => $cols) {
                $parsed = $this->parseRowColumns($cols);

                if ($parsed['code'] === '' && $parsed['name'] === '') {
                    continue;
                }
                if ($parsed['name'] === '') {
                    $skipped++;
                    $errors[] = "السطر {$lineNo}: اسم الصنف مفقود.";

                    continue;
                }

                $priceParts  = $this->parsePriceList($parsed['price_raw']);
                $mainPrice   = $priceParts[0] ?? 0.0;
                $extraPrices = array_slice($priceParts, 1);

                $payload = [
                    'code'    => $parsed['code'] !== '' ? $parsed['code'] : null,
                    'name'    => $parsed['name'],
                    'qty'     => (int) $this->num($parsed['qty_raw']),
                    'min_qty' => (int) $this->num($parsed['min_qty_raw']),
                    'price'   => $mainPrice,
                    'prices'  => array_map(
                        fn (float $amount) => ['amount' => $amount],
                        $extraPrices,
                    ),
                ];

                if ($parsed['supplier_ref'] !== '') {
                    $supplier = $this->resolveSupplier($parsed['supplier_ref']);
                    if (! $supplier) {
                        $skipped++;
                        $errors[] = "السطر {$lineNo}: المورد «{$parsed['supplier_ref']}» غير موجود.";

                        continue;
                    }
                    $payload['supplier_ids'] = [$supplier->id];
                }

                if ($parsed['category_ref'] !== '') {
                    $category = $this->resolveCategory($parsed['category_ref']);
                    if (! $category) {
                        $skipped++;
                        $errors[] = "السطر {$lineNo}: القسم «{$parsed['category_ref']}» غير موجود.";

                        continue;
                    }
                    $payload['category_id'] = $category->id;
                }

                if ($parsed['attributes_raw'] !== '') {
                    $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : null;
                    $attributes = $this->parseAttributes($parsed['attributes_raw'], $categoryId);
                    if ($categoryId !== null) {
                        $attributes = $this->normalizeAttributeValues($categoryId, $attributes);
                    }
                    $payload['attributes'] = $attributes;
                }

                $existing = $parsed['code'] !== '' ? StockItem::where('code', $parsed['code'])->first() : null;

                try {
                    if ($existing) {
                        $this->catalogService->update($existing, $payload);
                        $updated++;
                    } else {
                        $this->catalogService->create($payload);
                        $created++;
                    }
                } catch (ValidationException $e) {
                    $skipped++;
                    $errors[] = "السطر {$lineNo}: " . implode(' ', $e->validator->errors()->all());
                }
            }
        });

        AuditService::log(
            action:      'import',
            description: "رفع جماعي للأصناف — {$created} جديد، {$updated} محدَّث، {$skipped} متخطّى",
            tag:         'admin',
            after:       ['created' => $created, 'updated' => $updated, 'skipped' => $skipped],
        );

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @param  list<list<string>>  $itemRows
     */
    private function buildWorkbookBinary(array $itemRows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stock_tpl_');
        if ($path === false) {
            throw new \RuntimeException('تعذّر إنشاء ملف مؤقت للقالب.');
        }

        $xlsxPath = $path . '.xlsx';
        @unlink($path);

        $writer = new XlsxWriter();
        $writer->openToFile($xlsxPath);

        $itemsSheet = $writer->getCurrentSheet();
        $itemsSheet->setName(self::SHEET_ITEMS);
        $writer->addRow(Row::fromValues(self::HEADERS));
        $writer->addRow(Row::fromValues([
            '← تعليمات',
            'اسم الصنf',
            'رقم',
            'رقم',
            '1000;1200',
            'معرف من تبويب الموردون',
            'معرف من تبويب الأقسام',
            'انسخ من تبويب خيارات الحقول — قيم مفصولة بـ |',
        ]));
        foreach ($itemRows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $suppliersSheet = $writer->addNewSheetAndMakeItCurrent();
        $suppliersSheet->setName(self::SHEET_SUPPLIERS);
        $writer->addRow(Row::fromValues(['المعرف', 'اسم المورد']));
        foreach ($this->supplierReferenceRows() as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $categoriesSheet = $writer->addNewSheetAndMakeItCurrent();
        $categoriesSheet->setName(self::SHEET_CATEGORIES);
        $writer->addRow(Row::fromValues(['المعرف', 'اسم القسم']));
        foreach ($this->categoryReferenceRows() as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $fieldsSheet = $writer->addNewSheetAndMakeItCurrent();
        $fieldsSheet->setName(self::SHEET_FIELDS);
        $writer->addRow(Row::fromValues([
            'معرف القسم',
            'اسم القسم',
            'مفتاح الحقل',
            'اسم الحقل',
            'نوع الحقل',
            'مثال',
        ]));
        foreach ($this->fieldReferenceRows() as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $optionsSheet = $writer->addNewSheetAndMakeItCurrent();
        $optionsSheet->setName(self::SHEET_FIELD_OPTIONS);
        $writer->addRow(Row::fromValues([
            'معرف القسم',
            'اسم القسم',
            'اسم الحقل',
            'ما تكتبه في الملف',
        ]));
        foreach ($this->fieldOptionsReferenceRows() as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        $bytes = (string) file_get_contents($xlsxPath);
        @unlink($xlsxPath);

        return $bytes;
    }

    /** @return list<list<string>> */
    private function buildExampleRows(): array
    {
        $blatchford = Supplier::query()->where('name', 'Blatchford Group')->first();
        $fillauer   = Supplier::query()->where('name', 'Fillauer LLC')->first();
        $ottobock   = Supplier::query()->where('name', 'Ottobock Egypt')->first();

        $joints    = StockCategory::query()->where('name', 'مفاصل')->first();
        $fabrics   = StockCategory::query()->where('name', 'أقمشة ومواد خام')->first();
        $fasteners = StockCategory::query()->where('name', 'مسامير وربط')->first();

        return [
            [
                'RM-100',
                'مفصل ركبة هيدروليكي',
                '10',
                '5',
                '15000;18000',
                (string) ($blatchford?->id ?? ''),
                (string) ($joints?->id ?? ''),
                '',
            ],
            [
                'RM-101',
                'قماش تغليف',
                '50',
                '10',
                '850',
                (string) ($fillauer?->id ?? ''),
                (string) ($fabrics?->id ?? ''),
                'قماش Lamination|متر|#1E40AF',
            ],
            [
                'RM-102',
                'مسامير تثبيت M8',
                '200',
                '25',
                '120',
                (string) ($ottobock?->id ?? ''),
                (string) ($fasteners?->id ?? ''),
                'مسمار سداسي|M8|Stainless Steel|قطعة',
            ],
        ];
    }

    /** @return list<list<string>> */
    private function supplierReferenceRows(): array
    {
        return Supplier::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [(string) $supplier->id, $supplier->name])
            ->all();
    }

    /** @return list<list<string>> */
    private function categoryReferenceRows(): array
    {
        return StockCategory::query()
            ->orderBy('name')
            ->get()
            ->map(fn (StockCategory $category) => [(string) $category->id, $category->name])
            ->all();
    }

    /** @return list<list<string>> */
    private function fieldReferenceRows(): array
    {
        $rows = [];

        StockCategory::query()
            ->with('fields')
            ->orderBy('name')
            ->get()
            ->each(function (StockCategory $category) use (&$rows) {
                foreach ($category->fields->sortBy('sort_order')->values() as $field) {
                    $rows[] = [
                        (string) $category->id,
                        $category->name,
                        $field->field_key,
                        $field->label,
                        $this->fieldTypeLabel($field->type),
                        $this->fieldExample($field),
                    ];
                }
            });

        return $rows;
    }

    /** @return list<list<string>> */
    private function fieldOptionsReferenceRows(): array
    {
        $rows = [];

        StockCategory::query()
            ->with('fields')
            ->orderBy('name')
            ->get()
            ->each(function (StockCategory $category) use (&$rows) {
                foreach ($category->fields->sortBy('sort_order')->values() as $field) {
                    $options = collect($field->options ?? []);

                    if ($options->isNotEmpty()) {
                        foreach ($options as $option) {
                            $rows[] = [
                                (string) $category->id,
                                $category->name,
                                $field->label,
                                (string) ($option['label'] ?? $option['value'] ?? ''),
                            ];
                        }

                        continue;
                    }

                    $rows[] = [
                        (string) $category->id,
                        $category->name,
                        $field->label,
                        $this->fieldExample($field) !== ''
                            ? $this->fieldExample($field)
                            : ($field->config['placeholder'] ?? 'اكتب القيمة'),
                    ];
                }
            });

        return $rows;
    }

    private function fieldTypeLabel(string $type): string
    {
        return match ($type) {
            'list'     => 'قائمة',
            'radio'    => 'اختيار واحد',
            'checkbox' => 'اختيارات متعددة',
            'color'    => 'لون',
            'number'   => 'رقم',
            'range'    => 'نطاق',
            default    => 'نص',
        };
    }

    private function fieldExample(StockCategoryField $field): string
    {
        $options = collect($field->options ?? []);
        if ($options->isNotEmpty()) {
            return (string) ($options->first()['label'] ?? $options->first()['value'] ?? '');
        }

        return match ($field->type) {
            'color' => (string) (($field->config['default'] ?? '') ?: '#334155'),
            'number', 'range' => '0',
            default => (string) ($field->config['placeholder'] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributeValues(int $categoryId, array $attributes): array
    {
        $category = StockCategory::query()->with('fields')->find($categoryId);
        if (! $category) {
            return $attributes;
        }

        $fieldsByKey = $category->fields->keyBy('field_key');
        $out = [];

        foreach ($attributes as $key => $value) {
            if (! is_string($value)) {
                $out[$key] = $value;
                continue;
            }

            $field = $fieldsByKey->get($key);
            $out[$key] = $field instanceof StockCategoryField
                ? $this->resolveFieldInputValue($field, $value)
                : $value;
        }

        return $out;
    }

    private function resolveFieldInputValue(StockCategoryField $field, string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (! in_array($field->type, ['list', 'radio'], true)) {
            return $raw;
        }

        foreach ($field->options ?? [] as $option) {
            if (! is_array($option)) {
                continue;
            }

            $value = trim((string) ($option['value'] ?? ''));
            $label = trim((string) ($option['label'] ?? $value));

            if ($raw === $value || mb_strtolower($raw) === mb_strtolower($value)) {
                return $value;
            }

            if ($raw === $label || mb_strtolower($raw) === mb_strtolower($label)) {
                return $value;
            }
        }

        return $raw;
    }

    private function displayValueForField(StockCategoryField $field, string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (! in_array($field->type, ['list', 'radio'], true)) {
            return $value;
        }

        foreach ($field->options ?? [] as $option) {
            if (! is_array($option)) {
                continue;
            }

            if ((string) ($option['value'] ?? '') === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    /**
     * @return array<int, list<string>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($file);
        }

        return $this->readCsvRows($file);
    }

    /**
     * @return array<int, list<string>>
     */
    private function readXlsxRows(UploadedFile $file): array
    {
        $reader = new XlsxReader();
        $reader->open($file->getRealPath());

        $sheetIterator = $reader->getSheetIterator();
        $sheetIterator->rewind();
        $sheet = $sheetIterator->current();

        $rows = [];
        $lineNo = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $lineNo++;
            $cells = array_map(
                fn ($value) => trim((string) ($value ?? '')),
                $row->toArray(),
            );

            if ($this->isHeaderRow($cells) || $this->isInstructionRow($cells)) {
                continue;
            }

            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            $rows[$lineNo] = $cells;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @return array<int, list<string>>
     */
    private function readCsvRows(UploadedFile $file): array
    {
        $content = $this->normalizeToUtf8((string) file_get_contents($file->getRealPath()));

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $rows  = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = $this->parseCsvLine($line);

            if ($this->isHeaderRow($cols) || $this->isInstructionRow($cols)) {
                continue;
            }

            $rows[$i + 1] = $cols;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $cols
     * @return array{
     *   code:string,
     *   name:string,
     *   qty_raw:string,
     *   min_qty_raw:string,
     *   price_raw:string,
     *   supplier_ref:string,
     *   category_ref:string,
     *   attributes_raw:string
     * }
     */
    private function parseRowColumns(array $cols): array
    {
        if (count($cols) >= 7) {
            return [
                'code'            => trim((string) ($cols[0] ?? '')),
                'name'            => trim((string) ($cols[1] ?? '')),
                'qty_raw'         => trim((string) ($cols[2] ?? '')),
                'min_qty_raw'     => trim((string) ($cols[3] ?? '')),
                'price_raw'       => trim((string) ($cols[4] ?? '')),
                'supplier_ref'    => trim((string) ($cols[5] ?? '')),
                'category_ref'    => trim((string) ($cols[6] ?? '')),
                'attributes_raw'  => trim((string) ($cols[7] ?? '')),
            ];
        }

        $priceParts = array_slice($cols, 3);
        $priceRaw   = implode(';', array_map(
            fn ($part) => trim((string) $part),
            $priceParts,
        ));

        return [
            'code'            => trim((string) ($cols[0] ?? '')),
            'name'            => trim((string) ($cols[1] ?? '')),
            'qty_raw'         => trim((string) ($cols[2] ?? '')),
            'min_qty_raw'     => '0',
            'price_raw'       => $priceRaw,
            'supplier_ref'    => '',
            'category_ref'    => '',
            'attributes_raw'  => '',
        ];
    }

    private function resolveSupplier(string $ref): ?Supplier
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $ref)) {
            return Supplier::query()->find((int) $ref);
        }

        return Supplier::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($ref)])
            ->first();
    }

    private function resolveCategory(string $ref): ?StockCategory
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $ref)) {
            return StockCategory::query()->find((int) $ref);
        }

        return StockCategory::query()
            ->where('name', $ref)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAttributes(string $raw, ?int $categoryId): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if (str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (str_contains($raw, '=')) {
            return $this->parseKeyValueAttributes($raw);
        }

        if ($categoryId !== null) {
            return $this->parsePositionalAttributes($raw, $categoryId);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseKeyValueAttributes(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[;؛]/u', $raw) ?: [] as $pair) {
            $pair = trim((string) $pair);
            if ($pair === '' || ! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = trim($key);
            if ($key !== '') {
                $out[$key] = trim($value);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePositionalAttributes(string $raw, int $categoryId): array
    {
        $category = StockCategory::query()->with('fields')->find($categoryId);
        if (! $category) {
            return [];
        }

        $fields = $category->fields->sortBy('sort_order')->values();
        $values = array_map('trim', explode('|', $raw));
        $out    = [];

        foreach ($fields as $index => $field) {
            $value = trim((string) ($values[$index] ?? ''));
            if ($value !== '') {
                $out[$field->field_key] = $value;
            }
        }

        return $out;
    }

    private function normalizeToUtf8(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $raw = $content;
        $candidates = [];

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $candidates[] = substr($raw, 3);
            $raw = substr($raw, 3);
        }

        if (str_starts_with($raw, "\xFF\xFE")) {
            $converted = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        if (str_starts_with($raw, "\xFE\xFF")) {
            $converted = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        if (mb_check_encoding($raw, 'UTF-8') && $this->encodingQualityScore($raw) >= 40) {
            return $raw;
        }

        if ($this->looksUtf16Le($raw) && ! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
            if (is_string($converted) && $converted !== '') {
                $candidates[] = $converted;
            }
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            $candidates[] = $raw;
        }

        foreach (['CP1256', 'Windows-1256', 'ISO-8859-6', 'CP1252', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $raw);
            if ($converted !== false && $converted !== '') {
                $candidates[] = $converted;
            }
        }

        if ($candidates === []) {
            return $raw;
        }

        usort(
            $candidates,
            fn (string $a, string $b): int => $this->encodingQualityScore($b) <=> $this->encodingQualityScore($a),
        );

        return $candidates[0];
    }

    private function encodingQualityScore(string $text): int
    {
        $score = 0;
        $score += preg_match_all('/\p{Arabic}/u', $text) * 25;
        $score += preg_match_all('/\p{L}/u', $text);

        if (str_contains($text, 'كود') || str_contains($text, 'اسم الصنف') || str_contains($text, 'اسم الصنف')) {
            $score += 120;
        }

        $score -= substr_count($text, '?') * 20;
        $score -= substr_count($text, "\u{FFFD}");

        if (preg_match('/[ÃØÙÚÛÅÂ]/u', $text)) {
            $score -= 80;
        }

        return $score;
    }

    private function looksUtf16Le(string $content): bool
    {
        $len = strlen($content);
        if ($len < 8) {
            return false;
        }

        if ($content[1] !== "\x00" || $content[3] !== "\x00") {
            return false;
        }

        $nullOdd = 0;
        $samples = min($len, 120);

        for ($i = 1; $i < $samples; $i += 2) {
            if ($content[$i] === "\x00") {
                $nullOdd++;
            }
        }

        return $nullOdd >= 20;
    }

    /** @param  list<string>  $cols */
    private function isInstructionRow(array $cols): bool
    {
        $first = trim((string) ($cols[0] ?? ''));

        return str_starts_with($first, '←') || str_contains($first, 'تعليمات');
    }

    /** @param  list<string>  $cols */
    private function isHeaderRow(array $cols): bool
    {
        $first = trim((string) ($cols[0] ?? ''));
        $haystack = mb_strtolower(implode(' ', array_map(
            fn ($col) => trim((string) $col),
            $cols,
        )));

        foreach (self::HEADERS as $header) {
            if (str_contains($haystack, mb_strtolower($header))) {
                return true;
            }
        }

        foreach (self::LEGACY_HEADER_ALIASES as $alias) {
            if (str_contains($haystack, mb_strtolower($alias))) {
                return true;
            }
        }

        return $first === self::HEADERS[0]
            || str_contains($haystack, 'كود الصنف')
            || str_contains($haystack, 'اسم الصنف')
            || str_contains($haystack, 'الحد الأدنى')
            || str_contains($haystack, 'معرف المورد')
            || str_contains($haystack, 'معرف القسم')
            || (str_contains($haystack, 'كود') && str_contains($haystack, 'السعر'))
            || $first === 'code';
    }

    /** @param  list<string>  $cols */
    private function rowIsEmpty(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function parseCsvLine(string $line): array
    {
        $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';

        return str_getcsv($line, $delimiter) ?: [];
    }

    private function num(mixed $value): float
    {
        return (float) str_replace([',', ' '], '', (string) $value);
    }

    /** @return list<float> */
    private function parsePriceList(mixed $raw): array
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s*و\s*|;+/u', $text) ?: [$text];
        $amounts = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $amount = $this->num($part);
            if ($amount > 0) {
                $amounts[] = $amount;
            }
        }

        return $amounts;
    }

    /** @param list<string> $row */
    private function toCsvLine(array $row): string
    {
        $escaped = array_map(function (string $cell) {
            if (preg_match('/[",;\n]/', $cell)) {
                return '"' . str_replace('"', '""', $cell) . '"';
            }

            return $cell;
        }, $row);

        return implode(',', $escaped) . "\r\n";
    }

    /** @param  array<string, mixed>  $item */
    private function formatPriceColumn(array $item): string
    {
        $parts = [];
        $main  = (float) ($item['price'] ?? 0);

        if ($main > 0) {
            $parts[] = $this->formatAmount($main);
        }

        foreach ($item['prices'] ?? [] as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount > 0) {
                $parts[] = $this->formatAmount($amount);
            }
        }

        return $parts !== [] ? implode(';', $parts) : '0';
    }

    /** @param  array<string, mixed>  $item */
    private function formatAttributesColumn(array $item): string
    {
        $map = $item['attributes_map'] ?? [];
        if (! is_array($map) || $map === []) {
            return '';
        }

        $categoryId = $item['category_id'] ?? null;
        if ($categoryId) {
            $category = StockCategory::query()->with('fields')->find($categoryId);
            if ($category) {
                $parts = [];
                foreach ($category->fields->sortBy('sort_order') as $field) {
                    $stored = (string) ($map[$field->field_key] ?? '');
                    $parts[] = $this->displayValueForField($field, $stored);
                }

                while ($parts !== [] && end($parts) === '') {
                    array_pop($parts);
                }

                if ($parts !== []) {
                    return implode('|', $parts);
                }
            }
        }

        $legacy = [];
        foreach ($map as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $legacy[] = $key . '=' . $value;
        }

        return implode(';', $legacy);
    }

    private function formatAmount(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
