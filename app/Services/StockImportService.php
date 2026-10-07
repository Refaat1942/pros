<?php

namespace App\Services;

use App\Models\StockItem;
use App\Support\CatalogColumns;
use App\Support\StockCatalogPicker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * الرفع الجماعي للأصناف — قالب Excel (config/catalog.php).
 */
class StockImportService
{
    public const SHEET_ITEMS = 'الأصناف';

    /** @return list<string> */
    public static function headers(): array
    {
        return CatalogColumns::templateHeaders();
    }

    public function __construct(private readonly StockCatalogService $catalogService) {}

    /**
     * يبني ملف Excel (.xlsx) جاهز للتنزيل.
     */
    public function templateBinary(): string
    {
        return $this->buildWorkbookBinary($this->buildExampleRows(), true);
    }

    /**
     * يصدّر الأصناف الحالية إلى Excel بنفس هيكل القالب.
     *
     * @param  iterable<int, array<string, mixed>>  $items
     */
    public function exportBinary(iterable $items, bool $includeInstructions = false): string
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[] = $this->rowFromItem($item);
        }

        return $this->buildWorkbookBinary($rows, $includeInstructions);
    }

    /**
     * رفع جماعي آمن (دمج وليس استبدال):
     *  - لا يُحذف أي صنف موجود، ولا تُمسح أي قيمة مسجلة بخلية فارغة في الملف.
     *  - الصنف الموجود يُحدَّث فقط بالقيم الجديدة المختلفة الموجودة فعلاً في الملف.
     *  - الصنف غير الموجود يُضاف.
     *  - تُقرأ كل الشيتات (وليس الأولى فقط)، وفشل سطر لا يُلغي باقي الملف.
     *
     * @return array{created:int, updated:int, unchanged:int, skipped:int, errors:list<string>, rows_processed:int, rows_in_file:int}
     */
    public function import(UploadedFile $file): array
    {
        @set_time_limit(0);

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
        $errors = [];
        $rowsProcessed = 0;
        $rowsInFile = 0;

        try {
            $sheets = $this->readSheetsWithColumnMaps($file);
        } catch (\Throwable $e) {
            report($e);

            return [
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'skipped' => 0,
                'errors' => ['تعذّر قراءة الملف — تأكد أنه Excel (.xlsx) أو CSV سليم وغير محمي بكلمة سر.'],
                'rows_processed' => 0,
                'rows_in_file' => 0,
            ];
        }

        $multiSheet = count($sheets) > 1;

        foreach ($sheets as $sheet) {
            $rowsInFile += count($sheet['rows']);

            foreach ($sheet['rows'] as $lineNo => $cols) {
                $where = $multiSheet ? "الشيت «{$sheet['name']}» — السطر {$lineNo}" : "السطر {$lineNo}";
                $parsed = $this->normalizeParsedImportRow($this->parseRowColumns($cols, $sheet['map']));

                if ($parsed['catalog_number'] === '' && $parsed['name'] === '' && $parsed['alt_codes'] === '') {
                    continue;
                }

                $rowsProcessed++;

                try {
                    // savepoint لكل سطر — خطأ في سطر واحد لا يُسقط بقية الاستيراد.
                    $status = DB::transaction(fn () => $this->importRow($parsed));
                } catch (\InvalidArgumentException $e) {
                    $status = $e->getMessage();
                } catch (ValidationException $e) {
                    $status = implode(' ', $e->validator->errors()->all());
                } catch (\Throwable $e) {
                    report($e);
                    $status = 'تعذّر حفظ الصنف ('.mb_substr($e->getMessage(), 0, 120).')';
                }

                match ($status) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'unchanged' => $unchanged++,
                    default => (function () use (&$skipped, &$errors, $where, $status) {
                        $skipped++;
                        $errors[] = "{$where}: {$status}";
                    })(),
                };
            }
        }

        AuditService::log(
            action: 'import',
            description: "رفع جماعي للأصناف — {$created} جديد، {$updated} محدَّث، {$unchanged} بدون تغيير، {$skipped} متخطّى",
            tag: 'admin',
            after: ['created' => $created, 'updated' => $updated, 'unchanged' => $unchanged, 'skipped' => $skipped],
        );

        StockCatalogPicker::forgetCachedRows();

        return compact('created', 'updated', 'unchanged', 'skipped', 'errors') + [
            'rows_processed' => $rowsProcessed,
            'rows_in_file' => $rowsInFile,
        ];
    }

    /**
     * @param  array<string, string>  $parsed
     * @return string 'created' | 'updated' | 'unchanged' | رسالة سبب التخطي
     */
    private function importRow(array $parsed): string
    {
        [$parsed['uom'], $uomQty] = $this->parseUomField($parsed['uom']);

        $existing = $this->findExistingForImport($parsed);

        if ($existing !== null) {
            return $this->mergeIntoExisting($existing, $parsed);
        }

        if ($parsed['name'] === '') {
            return 'اسم الصنف مفقود والصنف غير موجود في المخزون — لا يمكن إضافته.';
        }

        $openingQty = $this->qty($parsed['opening_qty_raw']);
        $addition = $this->qty($parsed['addition_raw']);
        $discount = $this->qty($parsed['discount_raw']);
        $balance = $parsed['balance_raw'] !== ''
            ? $this->qty($parsed['balance_raw'])
            : max(0.0, $openingQty + $addition - $discount);

        $noMovements = $openingQty == 0.0 && $addition == 0.0 && $discount == 0.0;

        // لو رصيد أول المدة فارغ لكن الرصيد معروف، ابدأ من الرصيد.
        if ($noMovements && $balance > 0) {
            $openingQty = $balance;
        }

        if ($uomQty !== null && $noMovements && $openingQty == 0.0) {
            $openingQty = (float) $uomQty;
            if ($balance == 0.0) {
                $balance = (float) $uomQty;
            }
        }

        $this->catalogService->create([
            'catalog_number' => $parsed['catalog_number'] !== '' ? $parsed['catalog_number'] : null,
            'page_number' => $parsed['page_number'] !== '' ? $parsed['page_number'] : null,
            'name' => $parsed['name'],
            'brand' => $parsed['brand'] !== '' ? $parsed['brand'] : null,
            'alt_codes' => $parsed['alt_codes'] !== '' ? $parsed['alt_codes'] : null,
            'uom' => $parsed['uom'] !== '' ? $parsed['uom'] : null,
            'opening_qty' => $openingQty,
            'addition' => $addition,
            'discount' => $discount,
            'balance' => $balance,
            'qty' => $balance,
            'price' => round($this->num($parsed['price_raw']), 4),
        ]);

        return 'created';
    }

    /**
     * يدمج سطر الملف مع صنف موجود: الخلايا الفارغة لا تمسح شيئاً،
     * والرصيد الحالي لا يُعاد حسابه إلا إذا كان عمود «الرصيد» مكتوباً في الملف.
     *
     * @param  array<string, string>  $parsed
     */
    private function mergeIntoExisting(StockItem $existing, array $parsed): string
    {
        $changes = [];

        $textFields = [
            'name' => 'name',
            'catalog_number' => 'catalog_number',
            'page_number' => 'page_number',
            'brand' => 'brand',
            'alt_codes' => 'alt_codes',
            'uom' => 'uom',
        ];

        foreach ($textFields as $field => $column) {
            $value = trim((string) ($parsed[$field] ?? ''));
            if ($value !== '' && $value !== trim((string) $existing->{$column})) {
                $changes[$field] = $value;
            }
        }

        $fileQty = [
            'opening_qty' => $parsed['opening_qty_raw'] !== '' ? $this->qty($parsed['opening_qty_raw']) : null,
            'addition' => $parsed['addition_raw'] !== '' ? $this->qty($parsed['addition_raw']) : null,
            'discount' => $parsed['discount_raw'] !== '' ? $this->qty($parsed['discount_raw']) : null,
        ];

        // نفس قاعدة الإنشاء: أول/إضافة/خصم = صفر والرصيد مكتوب ← أول المدة = الرصيد.
        // بدونها يُعتبر إعادة رفع نفس الملف «تعديلاً» ويُصفَّر أول المدة المحفوظ.
        $balanceInFile = $parsed['balance_raw'] !== '' ? $this->qty($parsed['balance_raw']) : null;
        if ($balanceInFile !== null && $balanceInFile > 0
            && ($fileQty['opening_qty'] ?? 0.0) == 0.0
            && ($fileQty['addition'] ?? 0.0) == 0.0
            && ($fileQty['discount'] ?? 0.0) == 0.0
            && $fileQty['opening_qty'] !== null) {
            $fileQty['opening_qty'] = $balanceInFile;
        }

        foreach ($fileQty as $column => $value) {
            if ($value !== null && ! $this->sameQty($value, (float) $existing->{$column})) {
                $changes[$column] = $value;
            }
        }

        if ($parsed['balance_raw'] !== '' && ! $this->sameQty($this->qty($parsed['balance_raw']), (float) $existing->qty)) {
            $changes['balance'] = $this->qty($parsed['balance_raw']);
        }

        if ($parsed['price_raw'] !== '') {
            $price = round($this->num($parsed['price_raw']), 4);
            if ($price > 0 && abs($price - (float) $existing->price) >= 0.00005) {
                $changes['price'] = $price;
            }
        }

        if ($changes === []) {
            return 'unchanged';
        }

        // update() يعيد حساب الرصيد من أول المدة/الإضافة/الخصم إن لم يُمرَّر رصيد —
        // نثبّت الرصيد الحالي صراحةً حتى لا تضيع حركات الصرف والاستلام المسجلة.
        $payload = $changes + [
            'name' => $existing->name,
            'qty' => (float) $existing->qty,
        ];

        $this->catalogService->update($existing, $payload);

        return 'updated';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function rowFromItem(array $item): array
    {
        $item['uom'] = $this->cleanUomForExport((string) ($item['uom'] ?? ''));

        return array_map(
            fn (string $key) => CatalogColumns::templateValue($item, $key),
            CatalogColumns::templateOrder(),
        );
    }

    /**
     * @param  list<list<string>>  $itemRows
     */
    private function buildWorkbookBinary(array $itemRows, bool $includeInstructions = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stock_tpl_');
        if ($path === false) {
            throw new \RuntimeException('تعذّر إنشاء ملف مؤقت للقالب.');
        }

        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        $headers = self::headers();
        $writer = new XlsxWriter;
        $writer->openToFile($xlsxPath);

        $itemsSheet = $writer->getCurrentSheet();
        $itemsSheet->setName(self::SHEET_ITEMS);
        $writer->addRow(Row::fromValues($headers));
        if ($includeInstructions) {
            $hints = [
                'code' => '← تعليمات',
                'page_number' => 'اختياري',
                'name' => 'مطلوب',
                'brand' => 'اختياري',
                'alt_codes' => 'كود الصنف (مطلوب في الرفع الجماعي)',
                'uom' => 'قطعة / متر ...',
                'opening_qty' => 'رقم',
                'addition' => 'رقم',
                'discount' => 'رقم',
                'balance' => 'رصيد = أول + إضافة − خصم',
                'price' => 'سعر التكلفة الأساسي (ج.م)',
            ];
            $writer->addRow(Row::fromValues(array_map(
                fn (string $key) => $hints[$key] ?? '',
                CatalogColumns::templateOrder(),
            )));
        }
        foreach ($itemRows as $row) {
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
        return [
            ['RM-100', '12', 'مفصل ركبة هيدروليكي', 'Ottobock', '4821', 'قطعة', '10', '5', '2', '13', '0'],
            ['RM-101', '13', 'قماش تغليف', 'Generic', '7394', 'متر', '50', '0', '10', '40', '0'],
            ['RM-102', '14', 'مسامير تثبيت M8', '', '6150', 'قطعة', '200', '20', '0', '220', '0'],
        ];
    }

    /**
     * يقرأ كل شيتات الملف (أو ملف CSV كشيت واحد) مع خريطة أعمدة مستقلة لكل شيت.
     *
     * الشيت الأول (أو شيت «الأصناف») يُقرأ حتى بدون صف عناوين (توافق خلفي)،
     * أما الشيتات الإضافية فلا تُستورد إلا إذا احتوت صف عناوين معروف — حتى لا
     * يتحول شيت ملخص/تعليمات إلى أصناف وهمية.
     *
     * @return list<array{name: string, map: array<string, int>|null, rows: array<int, list<string>>}>
     */
    private function readSheetsWithColumnMaps(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rawSheets = $extension === 'xlsx'
            ? $this->readXlsxSheetsRaw($file)
            : [['name' => 'CSV', 'primary' => true, 'rows' => $this->readCsvRowsRaw($file)]];

        $sheets = [];

        foreach ($rawSheets as $raw) {
            $columnMap = null;
            $rows = [];

            foreach ($raw['rows'] as $lineNo => $cols) {
                if ($this->rowIsEmpty($cols) || $this->isInstructionRow($cols)) {
                    continue;
                }

                if ($columnMap === null && $this->isHeaderRow($cols)) {
                    $columnMap = $this->buildColumnMap($cols);

                    continue;
                }

                // صف عناوين مكرر داخل الشيت (لصق أكثر من جدول) — ليس صنفاً.
                if ($columnMap !== null && $this->isHeaderRow($cols)) {
                    continue;
                }

                $rows[$lineNo] = $cols;
            }

            if ($rows === [] || ($columnMap === null && ! $raw['primary'])) {
                continue;
            }

            $sheets[] = ['name' => $raw['name'], 'map' => $columnMap, 'rows' => $rows];
        }

        return $sheets;
    }

    /**
     * @param  list<string>  $headerCells
     * @return array<string, int>
     */
    private function buildColumnMap(array $headerCells): array
    {
        $aliases = CatalogColumns::importAliases();

        // كل (عمود، حقل) بدرجة تطابقه، ثم التوزيع من الأعلى درجة — حتى لا يأخذ عمود مبكر
        // حقلاً عمودٌ لاحق أدق له (مثال: «سعر وحدة التوريد» قبل «سعر تكلفة وحدة الصرف»).
        $candidates = [];

        foreach ($headerCells as $index => $cell) {
            $normalized = $this->normalizeHeaderLabel((string) $cell);
            if ($normalized === '') {
                continue;
            }

            foreach ($aliases as $field => $labels) {
                $best = 0;

                foreach ($labels as $label) {
                    $labelNorm = $this->normalizeHeaderLabel($label);
                    if ($labelNorm === '') {
                        continue;
                    }

                    $score = 0;
                    if ($normalized === $labelNorm) {
                        $score = 200 + strlen($labelNorm);
                    } elseif (str_contains($normalized, $labelNorm)) {
                        $score = 100 + strlen($labelNorm);
                    } elseif (strlen($normalized) >= 2 && str_contains($labelNorm, $normalized)) {
                        $score = 60 + strlen($normalized);
                    }

                    $best = max($best, $score);
                }

                if ($best > 0) {
                    $candidates[] = ['score' => $best, 'index' => $index, 'field' => $field];
                }
            }
        }

        usort($candidates, fn (array $a, array $b) => [$b['score'], $a['index']] <=> [$a['score'], $b['index']]);

        $map = [];
        $usedColumns = [];

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate['field'], $map) || isset($usedColumns[$candidate['index']])) {
                continue;
            }

            $map[$candidate['field']] = $candidate['index'];
            $usedColumns[$candidate['index']] = true;
        }

        return $map;
    }

    private function normalizeHeaderLabel(string $cell): string
    {
        $s = trim($cell);
        $s = preg_replace('/^\x{FEFF}/u', '', $s) ?? $s;
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s) ?? $s;
        $s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
        $s = str_replace('ة', 'ه', $s);

        return mb_strtolower($s);
    }

    private function formatImportCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (abs($value - (int) round($value)) < 0.00001) {
                return (string) (int) round($value);
            }

            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function findExistingForImport(array $parsed): ?StockItem
    {
        $parsed = $this->normalizeParsedImportRow($parsed);
        $pageNumber = $parsed['page_number'];
        $altCodes = $parsed['alt_codes'];
        $catalogNumber = $parsed['catalog_number'];
        $name = $parsed['name'];

        if ($catalogNumber !== '' && $pageNumber !== '') {
            $byCatalogPage = StockItem::query()
                ->where('catalog_number', $catalogNumber)
                ->where('page_number', $pageNumber)
                ->first();
            if ($byCatalogPage !== null) {
                return $byCatalogPage;
            }
        }

        if ($altCodes !== '') {
            $byAlt = StockItem::query()->where('alt_codes', $altCodes)->first();
            if ($byAlt !== null) {
                return $byAlt;
            }
        }

        // مطابقات احتياطية برقم الصنف: الكتالوج فيه أصناف مختلفة بنفس رقم الصنف
        // (مثال 198 في الصفحة 1/198 و3/198) — لا تُدمج إذا اختلفت الصفحة أو الكود،
        // وإلا يختفي صنف من الملف ويبدو أن الرفع «لم يقرأ كل الأصناف».
        if ($catalogNumber !== '') {
            $byCode = StockItem::query()->where('code', $catalogNumber)->first();
            if ($byCode !== null && ! $this->contradictsRow($byCode, $pageNumber, $altCodes, $name)) {
                return $byCode;
            }

            if ($name !== '') {
                $byName = StockItem::query()
                    ->where('catalog_number', $catalogNumber)
                    ->where('name', $name)
                    ->first();
                if ($byName !== null && ! $this->contradictsRow($byName, $pageNumber, $altCodes)) {
                    return $byName;
                }
            }

            $legacy = StockItem::query()
                ->where('code', $catalogNumber)
                ->where(function ($q) {
                    $q->whereNull('catalog_number')->orWhere('catalog_number', '');
                })
                ->first();
            if ($legacy !== null && ! $this->contradictsRow($legacy, $pageNumber, $altCodes, $name)) {
                return $legacy;
            }
        }

        // بدون رقم صنف: رقم الصفحة وحده يجمع أصنافاً كثيرة (كل أصناف الصفحة) —
        // لذا يلزم تطابق الاسم أيضاً، وإلا كانت أصناف الصفحة الواحدة تُدمج في صنف واحد
        // ويبدو أن الملف «لم يُقرأ كله».
        if ($pageNumber !== '' && $catalogNumber === '' && $name !== '') {
            $byPageName = StockItem::query()
                ->where('page_number', $pageNumber)
                ->where('name', $name)
                ->first();
            if ($byPageName !== null) {
                return $byPageName;
            }
        }

        return null;
    }

    /**
     * الصنف المرشَّح صنف آخر إن كان له رقم صفحة أو كود صنف أو اسم مختلف عن السطر.
     * الاسم مهم لملفات يتكرر فيها ترقيم «رقم الصنف» لكل قسم (1، 2، 3… ثم 1، 2…)
     * بلا رقم صفحة ولا كود — وإلا يُدمج صنف القسم الثاني فوق صنف القسم الأول.
     */
    private function contradictsRow(StockItem $candidate, string $pageNumber, string $altCodes, string $name = ''): bool
    {
        $candidatePage = trim((string) $candidate->page_number);
        if ($pageNumber !== '' && $candidatePage !== '' && $candidatePage !== $pageNumber) {
            return true;
        }

        $candidateName = $this->normalizeImportIdentifier((string) $candidate->name);
        if ($name !== '' && $candidateName !== '' && $candidateName !== $name) {
            return true;
        }

        $candidateAlt = trim((string) $candidate->alt_codes);

        return $altCodes !== '' && $candidateAlt !== '' && $candidateAlt !== $altCodes;
    }

    /**
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    private function normalizeParsedImportRow(array $parsed): array
    {
        foreach (['catalog_number', 'page_number', 'name', 'brand', 'alt_codes', 'uom', 'price_raw'] as $field) {
            $parsed[$field] = $this->normalizeImportIdentifier((string) ($parsed[$field] ?? ''));
        }

        return $parsed;
    }

    private function normalizeImportIdentifier(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    /**
     * كل الشيتات المرئية في ملف Excel — شيت التعليمات يُتجاهَل.
     *
     * @return list<array{name: string, primary: bool, rows: array<int, list<string>>}>
     */
    private function readXlsxSheetsRaw(UploadedFile $file): array
    {
        $reader = new XlsxReader;
        $reader->open($file->getRealPath());

        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $index => $sheet) {
                $name = (string) $sheet->getName();

                if (str_contains($name, 'تعليمات') || stripos($name, 'instruction') !== false) {
                    continue;
                }

                $rows = [];
                $lineNo = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $lineNo++;
                    $rows[$lineNo] = array_map(
                        fn ($value) => $this->formatImportCell($value),
                        $row->toArray(),
                    );
                }

                $sheets[] = [
                    'name' => $name,
                    'primary' => $sheets === [] || $name === self::SHEET_ITEMS,
                    'rows' => $rows,
                ];
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }

    /**
     * CSV حقيقي (fgetcsv) — يدعم الخلايا بين علامتي تنصيص وفيها فواصل أو أسطر جديدة،
     * بدلاً من تقسيم الملف على الأسطر الذي كان يُسقط/يكسر بعض الأصناف.
     *
     * @return array<int, list<string>>
     */
    private function readCsvRowsRaw(UploadedFile $file): array
    {
        $content = $this->normalizeToUtf8((string) file_get_contents($file->getRealPath()));
        if (trim($content) === '') {
            return [];
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        if (substr_count($firstLine, "\t") > max(substr_count($firstLine, ','), substr_count($firstLine, ';'))) {
            $delimiter = "\t";
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        $lineNo = 0;

        while (($cols = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $lineNo++;
            if ($cols === [null]) {
                continue;
            }

            $rows[$lineNo] = array_map(fn ($c) => trim((string) $c), $cols);
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param  list<string>  $cols
     * @param  array<string, int>|null  $columnMap
     * @return array{catalog_number:string, page_number:string, name:string, brand:string, alt_codes:string, uom:string, opening_qty_raw:string, addition_raw:string, discount_raw:string, balance_raw:string, price_raw:string}
     */
    private function parseRowColumns(array $cols, ?array $columnMap = null): array
    {
        if ($columnMap !== null && $columnMap !== []) {
            return [
                'catalog_number' => $this->cellValue($cols, $columnMap, 'catalog_number'),
                'page_number' => $this->cellValue($cols, $columnMap, 'page_number'),
                'name' => $this->cellValue($cols, $columnMap, 'name'),
                'brand' => $this->cellValue($cols, $columnMap, 'brand'),
                'alt_codes' => $this->cellValue($cols, $columnMap, 'alt_codes'),
                'uom' => $this->cellValue($cols, $columnMap, 'uom'),
                'opening_qty_raw' => $this->cellValue($cols, $columnMap, 'opening_qty_raw'),
                'addition_raw' => $this->cellValue($cols, $columnMap, 'addition_raw'),
                'discount_raw' => $this->cellValue($cols, $columnMap, 'discount_raw'),
                'balance_raw' => $this->cellValue($cols, $columnMap, 'balance_raw'),
                'price_raw' => $this->cellValue($cols, $columnMap, 'price_raw'),
            ];
        }

        if ($this->looksLikeLegacyFiveColumnRow($cols)) {
            return [
                'catalog_number' => trim((string) ($cols[0] ?? '')),
                'page_number' => '',
                'name' => trim((string) ($cols[1] ?? '')),
                'brand' => '',
                'alt_codes' => '',
                'uom' => trim((string) ($cols[2] ?? '')),
                'opening_qty_raw' => trim((string) ($cols[3] ?? '')),
                'addition_raw' => '',
                'discount_raw' => '',
                'balance_raw' => trim((string) ($cols[3] ?? '')),
                'price_raw' => '',
            ];
        }

        if ($this->looksLikeLegacyTenColumnRow($cols)) {
            return [
                'catalog_number' => trim((string) ($cols[0] ?? '')),
                'page_number' => trim((string) ($cols[1] ?? '')),
                'name' => trim((string) ($cols[2] ?? '')),
                'brand' => '',
                'alt_codes' => trim((string) ($cols[3] ?? '')),
                'uom' => trim((string) ($cols[4] ?? '')),
                'opening_qty_raw' => trim((string) ($cols[5] ?? '')),
                'addition_raw' => trim((string) ($cols[6] ?? '')),
                'discount_raw' => trim((string) ($cols[7] ?? '')),
                'balance_raw' => trim((string) ($cols[8] ?? '')),
                'price_raw' => trim((string) ($cols[9] ?? '')),
            ];
        }

        return [
            'catalog_number' => trim((string) ($cols[0] ?? '')),
            'page_number' => trim((string) ($cols[1] ?? '')),
            'name' => trim((string) ($cols[2] ?? '')),
            'brand' => trim((string) ($cols[3] ?? '')),
            'alt_codes' => trim((string) ($cols[4] ?? '')),
            'uom' => trim((string) ($cols[5] ?? '')),
            'opening_qty_raw' => trim((string) ($cols[6] ?? '')),
            'addition_raw' => trim((string) ($cols[7] ?? '')),
            'discount_raw' => trim((string) ($cols[8] ?? '')),
            'balance_raw' => trim((string) ($cols[9] ?? '')),
            'price_raw' => trim((string) ($cols[10] ?? '')),
        ];
    }

    /** @param  list<string>  $cols */
    private function looksLikeLegacyTenColumnRow(array $cols): bool
    {
        $count = count($cols);

        if ($count <= 10) {
            return true;
        }

        return $count === 11 && trim((string) ($cols[10] ?? '')) === '';
    }

    /**
     * @param  list<string>  $cols
     * @param  array<string, int>  $columnMap
     */
    private function cellValue(array $cols, array $columnMap, string $field, string $default = ''): string
    {
        if (! array_key_exists($field, $columnMap)) {
            return $default;
        }

        return trim((string) ($cols[$columnMap[$field]] ?? $default));
    }

    /** @param  list<string>  $cols */
    private function looksLikeLegacyFiveColumnRow(array $cols): bool
    {
        $nonEmpty = count(array_filter($cols, fn ($c) => trim((string) $c) !== ''));

        return $nonEmpty <= 5 && count($cols) <= 6;
    }

    /** @return array{0: string, 1: ?int} [uom, embeddedQty] */
    private function parseUomField(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['', null];
        }

        if (preg_match('/^(\d+)\s+(.+)$/u', $raw, $m)) {
            return [trim($m[2]), (int) $m[1]];
        }

        return [$raw, null];
    }

    private function cleanUomForExport(string $uom): string
    {
        [$clean] = $this->parseUomField($uom);

        return $clean !== '' ? $clean : $uom;
    }

    /** @param  list<string>  $cols */
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

        foreach (self::headers() as $header) {
            if (str_contains($text, $header)) {
                $score += 120;
            }
        }

        if (str_contains($text, 'كود') || str_contains($text, 'اسم الصنف') || str_contains($text, 'رقم الصنف')) {
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

        foreach (self::headers() as $header) {
            if (str_contains($haystack, mb_strtolower($header))) {
                return true;
            }
        }

        foreach (config('catalog.legacy_header_aliases', []) as $alias) {
            if (str_contains($haystack, mb_strtolower($alias))) {
                return true;
            }
        }

        return $first === (self::headers()[0] ?? '')
            || str_contains($haystack, 'كود الصنف')
            || str_contains($haystack, 'رقم الصنف')
            || str_contains($haystack, 'اسم الصنف')
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


    /** كمية مخزنية — الأرصدة عشرية (decimal:4) ولا تُقرَّب لعدد صحيح. */
    private function qty(string $raw): float
    {
        return round($this->num($raw), 4);
    }

    private function sameQty(float $a, float $b): bool
    {
        return abs($a - $b) < 0.00005;
    }

    /** رقم من خلية: أرقام عربية/فارسية، فواصل آلاف، ونص عملة («7,250 جنية») — أول رقم في الخلية. */
    private function num(mixed $value): float
    {
        $s = strtr((string) $value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => '', ',' => '', ' ' => '', "\u{00A0}" => '',
        ]);

        return preg_match('/-?\d+(?:\.\d+)?/', $s, $m) ? (float) $m[0] : 0.0;
    }
}
