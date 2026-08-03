<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * الرفع الجماعي للأصناف — قالب Excel بـ 9 أعمدة (config/catalog.php).
 */
class StockImportService
{
    public const SHEET_ITEMS = 'الأصناف';

    /** @return list<string> */
    public static function headers(): array
    {
        return config('catalog.template_headers', []);
    }

    public function __construct(private readonly StockCatalogService $catalogService) {}

    /**
     * يبني ملف Excel (.xlsx) جاهز للتنزيل.
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
            $rows[] = $this->rowFromItem($item);
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
        $errors = [];

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

                $openingQty = (int) $this->num($parsed['opening_qty_raw']);
                $addition = (int) $this->num($parsed['addition_raw']);
                $discount = (int) $this->num($parsed['discount_raw']);
                $balanceRaw = trim($parsed['balance_raw']);
                $balance = $balanceRaw !== ''
                    ? (int) $this->num($balanceRaw)
                    : ($openingQty + $addition - $discount);

                $payload = [
                    'code' => $parsed['code'] !== '' ? $parsed['code'] : null,
                    'page_number' => $parsed['page_number'] !== '' ? $parsed['page_number'] : null,
                    'name' => $parsed['name'],
                    'alt_codes' => $parsed['alt_codes'] !== '' ? $parsed['alt_codes'] : null,
                    'uom' => $parsed['uom'] !== '' ? $parsed['uom'] : null,
                    'opening_qty' => $openingQty,
                    'addition' => $addition,
                    'discount' => $discount,
                    'balance' => $balance,
                    'qty' => $balance,
                ];

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
                    $errors[] = "السطر {$lineNo}: ".implode(' ', $e->validator->errors()->all());
                }
            }
        });

        AuditService::log(
            action: 'import',
            description: "رفع جماعي للأصناف — {$created} جديد، {$updated} محدَّث، {$skipped} متخطّى",
            tag: 'admin',
            after: ['created' => $created, 'updated' => $updated, 'skipped' => $skipped],
        );

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function rowFromItem(array $item): array
    {
        return [
            (string) ($item['code'] ?? ''),
            (string) ($item['page_number'] ?? ''),
            (string) ($item['name'] ?? ''),
            (string) ($item['alt_codes'] ?? ''),
            (string) ($item['uom'] ?? ''),
            (string) ((int) ($item['opening_qty'] ?? $item['qty'] ?? 0)),
            (string) ((int) ($item['addition'] ?? 0)),
            (string) ((int) ($item['discount'] ?? 0)),
            (string) ((int) ($item['balance'] ?? $item['qty'] ?? 0)),
        ];
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

        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        $headers = self::headers();
        $writer = new XlsxWriter;
        $writer->openToFile($xlsxPath);

        $itemsSheet = $writer->getCurrentSheet();
        $itemsSheet->setName(self::SHEET_ITEMS);
        $writer->addRow(Row::fromValues($headers));
        $writer->addRow(Row::fromValues([
            '← تعليمات',
            'اختياري',
            'مطلوب',
            'باركود / أكواد إضافية',
            'قطعة / متر ...',
            'رقم',
            'رقم',
            'رقم',
            'رصيد = أول + إضافة − خصم',
        ]));
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
            ['RM-100', '12', 'مفصل ركبة هيدروليكي', 'BC-RM-100', 'قطعة', '10', '5', '2', '13'],
            ['RM-101', '13', 'قماش تغليف', 'BC-RM-101', 'متر', '50', '0', '10', '40'],
            ['RM-102', '14', 'مسامير تثبيت M8', 'BC-RM-102', 'قطعة', '200', '20', '0', '220'],
        ];
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
        $reader = new XlsxReader;
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
        $rows = [];

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
     * @return array{code:string, page_number:string, name:string, alt_codes:string, uom:string, opening_qty_raw:string, addition_raw:string, discount_raw:string, balance_raw:string}
     */
    private function parseRowColumns(array $cols): array
    {
        if ($this->looksLikeLegacyFiveColumnRow($cols)) {
            return [
                'code' => trim((string) ($cols[0] ?? '')),
                'page_number' => '',
                'name' => trim((string) ($cols[1] ?? '')),
                'alt_codes' => '',
                'uom' => trim((string) ($cols[2] ?? '')),
                'opening_qty_raw' => trim((string) ($cols[3] ?? '')),
                'addition_raw' => '0',
                'discount_raw' => '0',
                'balance_raw' => trim((string) ($cols[3] ?? '')),
            ];
        }

        return [
            'code' => trim((string) ($cols[0] ?? '')),
            'page_number' => trim((string) ($cols[1] ?? '')),
            'name' => trim((string) ($cols[2] ?? '')),
            'alt_codes' => trim((string) ($cols[3] ?? '')),
            'uom' => trim((string) ($cols[4] ?? '')),
            'opening_qty_raw' => trim((string) ($cols[5] ?? '')),
            'addition_raw' => trim((string) ($cols[6] ?? '0')),
            'discount_raw' => trim((string) ($cols[7] ?? '0')),
            'balance_raw' => trim((string) ($cols[8] ?? '')),
        ];
    }

    /** @param  list<string>  $cols */
    private function looksLikeLegacyFiveColumnRow(array $cols): bool
    {
        return count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) <= 5
            && count($cols) <= 6;
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
}
