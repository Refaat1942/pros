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
 * الرفع الجماعي للأصناف — قالب Excel مبسّط بخمسة أعمدة فقط.
 *
 * الأعمدة (بالترتيب): كود الصنف، اسم الصنف، الوحدة، الكمية، الحد الأدنى للطلب.
 *
 * الأسعار والموردون والأقسام تُدار من شاشة الكاتلوج — ليست ضمن القالب.
 */
class StockImportService
{
    public const SHEET_ITEMS = 'الأصناف';

    /** ترويسة ورقة الأصناف — تُستخدم للتنزيل والاستيراد. */
    public const HEADERS = [
        'كود الصنف',
        'اسم الصنف',
        'الوحدة',
        'الكمية',
        'الحد الأدنى للطلب',
    ];

    /** ترويسات قديمة (توافق خلفي مع ملفات مرفوعة بصيغة سابقة). */
    private const LEGACY_HEADER_ALIASES = [
        'كود الصنف',
        'اسم الصنف',
        'الكمية',
        'الحد الأدنى للصنف',
        'الحد الأدنى',
        'السعر',
    ];

    public function __construct(private readonly StockCatalogService $catalogService) {}

    /**
     * يبني ملف Excel (.xlsx) جاهز للتنزيل بخمسة أعمدة.
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
            $rows[] = [
                (string) ($item['code'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ($item['uom'] ?? ''),
                (string) ((int) ($item['qty'] ?? 0)),
                (string) ((int) ($item['min_qty'] ?? 0)),
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

                $payload = [
                    'code' => $parsed['code'] !== '' ? $parsed['code'] : null,
                    'name' => $parsed['name'],
                    'uom' => $parsed['uom'] !== '' ? $parsed['uom'] : null,
                    'qty' => (int) $this->num($parsed['qty_raw']),
                    'min_qty' => (int) $this->num($parsed['min_qty_raw']),
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

        $writer = new XlsxWriter;
        $writer->openToFile($xlsxPath);

        $itemsSheet = $writer->getCurrentSheet();
        $itemsSheet->setName(self::SHEET_ITEMS);
        $writer->addRow(Row::fromValues(self::HEADERS));
        $writer->addRow(Row::fromValues([
            '← تعليمات',
            'اسم الصنف',
            'قطعة / متر / طقم ...',
            'رقم',
            'رقم',
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
            ['RM-100', 'مفصل ركبة هيدروليكي', 'قطعة', '10', '5'],
            ['RM-101', 'قماش تغليف', 'متر', '50', '10'],
            ['RM-102', 'مسامير تثبيت M8', 'قطعة', '200', '25'],
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
     * @return array{code:string, name:string, uom:string, qty_raw:string, min_qty_raw:string}
     */
    private function parseRowColumns(array $cols): array
    {
        return [
            'code' => trim((string) ($cols[0] ?? '')),
            'name' => trim((string) ($cols[1] ?? '')),
            'uom' => trim((string) ($cols[2] ?? '')),
            'qty_raw' => trim((string) ($cols[3] ?? '')),
            'min_qty_raw' => trim((string) ($cols[4] ?? '')),
        ];
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

        if (str_contains($text, 'كود') || str_contains($text, 'اسم الصنف') || str_contains($text, 'الوحدة')) {
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
