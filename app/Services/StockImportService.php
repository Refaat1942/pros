<?php

namespace App\Services;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الرفع الجماعي للأصناف عبر CSV (أصلي — بدون حزم خارجية).
 *
 * الأعمدة (بالترتيب):
 * كود الصنف، اسم الصنف، الكمية، الحد الأدنى للصنف، السعر، المورد، القسم، خصائص القسم.
 *
 * عمود السعر يقبل أكثر من قيمة مفصولة بـ «;» أو «و» — الأول سعر أساسي والباقي أسعار إضافية.
 * عمود خصائص القسم: مفتاح=قيمة مفصولة بـ «;» — مثال: uom=متر;color=#1E40AF
 *
 * يقوم بعملية upsert: إن وُجد الكود يُحدَّث، وإلا يُنشأ صنف جديد.
 * الملفات القديمة (4 أعمدة بدون ترويسة) ما زالت مدعومة.
 */
class StockImportService
{
    /** ترويسة قالب CSV — تُستخدم للتنزيل والاستيراد. */
    public const HEADERS = [
        'كود الصنف',
        'اسم الصنف',
        'الكمية',
        'الحد الأدنى للصنف',
        'السعر',
        'المورد',
        'القسم',
        'خصائص القسم',
    ];

    public function __construct(private readonly StockCatalogService $catalogService)
    {
    }

    /**
     * يبني محتوى قالب CSV (مع BOM لدعم العربية في Excel).
     */
    public function templateContents(): string
    {
        $bom  = "\xEF\xBB\xBF";
        $rows = [
            self::HEADERS,
            ['RM-100', 'مفصل ركبة هيدروليكي', '10', '5', '15000;18000', 'Blatchford Group', 'مفاصل', ''],
            ['RM-101', 'قماش تغليف', '50', '10', '850', 'Fillauer LLC', 'أقمشة ومواد خام', 'uom=متر;color=#1E40AF'],
            ['RM-102', 'مسامير تثبيت M8', '200', '25', '120', 'Ottobock Egypt', 'مسامير وربط', 'uom=قطعة;size=M8'],
        ];

        $out = $bom;
        foreach ($rows as $row) {
            $out .= $this->toCsvLine($row);
        }

        return $out;
    }

    /**
     * يصدّر الأصناف الحالية بنفس أعمدة القالب (للتعديل وإعادة الرفع).
     *
     * @param  iterable<int, array<string, mixed>>  $items  عناصر من StockCatalogService::formatItem
     */
    public function exportContents(iterable $items): string
    {
        $rows = [self::HEADERS];

        foreach ($items as $item) {
            $supplier = ($item['suppliers'][0]['name'] ?? '') ?: '';
            $rows[] = [
                (string) ($item['code'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ((int) ($item['qty'] ?? 0)),
                (string) ((int) ($item['min_qty'] ?? 0)),
                $this->formatPriceColumn($item),
                $supplier,
                (string) ($item['category'] ?? ''),
                $this->formatAttributesColumn($item),
            ];
        }

        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= $this->toCsvLine($row);
        }

        return $out;
    }

    /**
     * يستورد ملف CSV ويعيد ملخص العملية.
     *
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
                    'code'   => $parsed['code'] !== '' ? $parsed['code'] : null,
                    'name'   => $parsed['name'],
                    'qty'    => (int) $this->num($parsed['qty_raw']),
                    'min_qty' => (int) $this->num($parsed['min_qty_raw']),
                    'price'  => $mainPrice,
                    'prices' => array_map(
                        fn (float $amount) => ['amount' => $amount],
                        $extraPrices,
                    ),
                ];

                if ($parsed['supplier_name'] !== '') {
                    $supplier = $this->resolveSupplier($parsed['supplier_name']);
                    if (! $supplier) {
                        $skipped++;
                        $errors[] = "السطر {$lineNo}: المورد «{$parsed['supplier_name']}» غير موجود.";

                        continue;
                    }
                    $payload['supplier_ids'] = [$supplier->id];
                }

                if ($parsed['category_name'] !== '') {
                    $category = $this->resolveCategory($parsed['category_name']);
                    if (! $category) {
                        $skipped++;
                        $errors[] = "السطر {$lineNo}: القسم «{$parsed['category_name']}» غير موجود.";

                        continue;
                    }
                    $payload['category_id'] = $category->id;
                }

                if ($parsed['attributes_raw'] !== '') {
                    $payload['attributes'] = $this->parseAttributes($parsed['attributes_raw']);
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
            description: "رفع جماعي للأصناf — {$created} جديد، {$updated} محدَّث، {$skipped} متخطّى",
            tag:         'admin',
            after:       ['created' => $created, 'updated' => $updated, 'skipped' => $skipped],
        );

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * يقرأ صفوف البيانات (يتخطّى صف الترويسة إن وُجد).
     *
     * @return array<int, list<string>>
     */
    private function readRows(UploadedFile $file): array
    {
        $content = $this->normalizeToUtf8((string) file_get_contents($file->getRealPath()));

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $rows  = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = $this->parseCsvLine($line);

            if ($this->isHeaderRow($cols)) {
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
     *   supplier_name:string,
     *   category_name:string,
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
                'supplier_name'   => trim((string) ($cols[5] ?? '')),
                'category_name'   => trim((string) ($cols[6] ?? '')),
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
            'supplier_name'   => '',
            'category_name'   => '',
            'attributes_raw'  => '',
        ];
    }

    private function resolveSupplier(string $name): ?Supplier
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return Supplier::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->first();
    }

    private function resolveCategory(string $name): ?StockCategory
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return StockCategory::query()
            ->where('name', $name)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAttributes(string $raw): array
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
     * يحوّل محتوى الملف إلى UTF-8 — يقارن عدة ترميزات ويختار الأنسب (Excel العربي).
     */
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

        if (str_contains($text, 'كود') || str_contains($text, 'اسم الصنف') || str_contains($text, 'الصنف')) {
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
    private function isHeaderRow(array $cols): bool
    {
        $first = trim((string) ($cols[0] ?? ''));
        $haystack = mb_strtolower(implode(' ', array_map(
            fn ($col) => trim((string) $col),
            $cols,
        )));

        return $first === self::HEADERS[0]
            || str_contains($haystack, 'كود الصنف')
            || str_contains($haystack, 'اسم الصنف')
            || str_contains($haystack, 'الحد الأدنى')
            || (str_contains($haystack, 'كود') && str_contains($haystack, 'السعر'))
            || $first === 'code';
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

    /**
     * @return list<float>
     */
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

        $parts = [];
        foreach ($map as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . $value;
        }

        return implode(';', $parts);
    }

    private function formatAmount(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
