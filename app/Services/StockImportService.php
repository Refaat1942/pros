<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * الرفع الجماعي للأصناف عبر CSV (أصلي — بدون حزم خارجية).
 *
 * الأعمدة (بالترتيب): كود الصنف، اسم الصنف، الكمية، السعر.
 * عمود السعر يقبل أكثر من قيمة مفصولة بـ «;» أو «و» — الأول سعر أساسي والباقي أسعار إضافية.
 * مثال: 1500;2000 أو 1500 و 2200
 *
 * يقوم بعملية upsert: إن وُجد الكود يُحدَّث، وإلا يُنشأ صنف جديد.
 */
class StockImportService
{
    /** ترويسة قالب CSV — تُستخدم للتنزيل والاستيراد. */
    public const HEADERS = [
        'كود الصنف',
        'اسم الصنف',
        'الكمية',
        'السعر',
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
            ['RM-100', 'مفصل ركبة ميكانيكي', '10', '1500;2000'],
        ];

        $out = $bom;
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
                [$code, $name, $qtyRaw, $priceRaw] = $this->normalizeColumns($cols);

                if ($code === '' && $name === '') {
                    continue; // سطر فارغ
                }
                if ($name === '') {
                    $skipped++;
                    $errors[] = "السطر {$lineNo}: اسم الصنف مفقود.";
                    continue;
                }

                $priceParts = $this->parsePriceList($priceRaw);
                $mainPrice  = $priceParts[0] ?? 0.0;
                $extraPrices = array_slice($priceParts, 1);

                $payload = [
                    'code'   => $code !== '' ? $code : null,
                    'name'   => $name,
                    'qty'    => (int) $this->num($qtyRaw),
                    'price'  => $mainPrice,
                    'prices' => array_map(
                        fn (float $amount) => ['amount' => $amount],
                        $extraPrices,
                    ),
                ];

                $existing = $code !== '' ? StockItem::where('code', $code)->first() : null;

                if ($existing) {
                    $this->catalogService->update($existing, $payload);
                    $updated++;
                } else {
                    $this->catalogService->create($payload);
                    $created++;
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

        // تشويه شائع عند قراءة CP1256 كـ UTF-8
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

        // نمط UTF-16 LE للنص اللاتيني: حرف ثم \x00
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
            || (str_contains($haystack, 'كود') && str_contains($haystack, 'السعر'))
            || $first === 'code';
    }

    /**
     * @param  list<string>  $cols
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function normalizeColumns(array $cols): array
    {
        $code = trim((string) ($cols[0] ?? ''));
        $name = trim((string) ($cols[1] ?? ''));
        $qty  = trim((string) ($cols[2] ?? ''));
        $priceParts = array_slice($cols, 3);
        $priceRaw = implode(';', array_map(
            fn ($part) => trim((string) $part),
            $priceParts,
        ));

        return [$code, $name, $qty, $priceRaw];
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
     * يفكّ عمود السعر إلى قائمة أرقام — مفصولة بـ «و» أو «;».
     *
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
}
