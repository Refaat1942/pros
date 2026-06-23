<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * الرفع الجماعي للأصناف عبر CSV (أصلي — بدون حزم خارجية).
 *
 * الأعمدة (بالترتيب): كود الصنف، اسم الصنف، الكمية، السعر.
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
            ['RM-100', 'مفصل ركبة ميكانيكي', '10', '1500.00'],
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
                $code = trim((string) ($cols[0] ?? ''));
                $name = trim((string) ($cols[1] ?? ''));

                if ($code === '' && $name === '') {
                    continue; // سطر فارغ
                }

                if ($name === '') {
                    $skipped++;
                    $errors[] = "السطر {$lineNo}: اسم الصنف مفقود.";
                    continue;
                }

                $payload = [
                    'code'  => $code !== '' ? $code : null,
                    'name'  => $name,
                    'qty'   => (int) $this->num($cols[2] ?? 0),
                    'price' => $this->num($cols[3] ?? 0),
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
        $content = (string) file_get_contents($file->getRealPath());

        // إزالة BOM إن وُجد.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $rows  = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line);

            // تخطّي صف الترويسة (يحتوي على "كود" أو "code").
            $first = trim((string) ($cols[0] ?? ''));
            if ($i === 0 && ($first === self::HEADERS[0] || mb_strtolower($first) === 'code')) {
                continue;
            }

            $rows[$i + 1] = $cols;
        }

        return $rows;
    }

    private function num(mixed $value): float
    {
        return (float) str_replace([',', ' '], '', (string) $value);
    }

    /** @param list<string> $row */
    private function toCsvLine(array $row): string
    {
        $escaped = array_map(function (string $cell) {
            if (preg_match('/[",\n]/', $cell)) {
                return '"' . str_replace('"', '""', $cell) . '"';
            }

            return $cell;
        }, $row);

        return implode(',', $escaped) . "\r\n";
    }
}
