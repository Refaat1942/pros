<?php

namespace App\Services;

use App\Models\ContractCompany;
use App\Support\ContractCompanyColumns;
use App\Support\CatalogImportValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * الرفع الجماعي لجهات التعاقد — قالب Excel.
 */
class ContractCompanyImportService
{
    public function __construct(private readonly ContractDebtService $contractDebtService) {}

    public function templateBinary(): string
    {
        return $this->buildWorkbookBinary($this->exampleRows());
    }

    /**
     * @param  iterable<ContractCompany>  $companies
     */
    public function exportBinary(iterable $companies): string
    {
        $rows = [];

        foreach ($companies as $company) {
            $rows[] = $this->rowFromCompany($company);
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

        ['map' => $columnMap, 'rows' => $rows] = $this->readRowsWithColumnMap($file);

        DB::transaction(function () use ($rows, $columnMap, &$created, &$updated, &$skipped, &$errors) {
            foreach ($rows as $lineNo => $cols) {
                $parsed = $this->parseRowColumns($cols, $columnMap);
                $name = trim($parsed['name']);

                if ($name === '') {
                    continue;
                }

                if (mb_strlen($name) < 2) {
                    $skipped++;
                    $errors[] = "السطر {$lineNo}: اسم الجهة قصير جداً.";

                    continue;
                }

                $isContracted = $this->parseContracted($parsed['is_contracted_raw']);
                $discount = $isContracted
                    ? $this->parseDiscountPercent($parsed['discount_raw'])
                    : 0.0;

                if ($isContracted && ($discount < 0 || $discount > 100)) {
                    $skipped++;
                    $errors[] = "السطر {$lineNo}: نسبة الخصم يجب أن تكون بين 0 و 100.";

                    continue;
                }

                $existing = ContractCompany::query()
                    ->where('name', $name)
                    ->first();

                if ($existing === null) {
                    $company = ContractCompany::create([
                        'company_code' => $this->generateCompanyCode(),
                        'name' => $name,
                        'is_military' => false,
                        'is_contracted' => $isContracted,
                        'discount_percent' => $discount,
                    ]);

                    if ($company->is_contracted) {
                        $this->contractDebtService->initialise($company);
                    }

                    AuditService::log(
                        action: 'create',
                        description: "استيراد جهة تعاقد {$company->company_code} — {$company->name}",
                        tag: 'financial',
                        after: $company->toArray(),
                    );

                    $created++;

                    continue;
                }

                $before = $existing->only(['name', 'is_contracted', 'discount_percent']);

                $existing->update([
                    'is_contracted' => $isContracted,
                    'discount_percent' => $discount,
                ]);

                if ($existing->is_contracted && ! $existing->debt) {
                    $this->contractDebtService->initialise($existing);
                }

                AuditService::log(
                    action: 'update',
                    description: "تحديث جهة تعاقد من استيراد — {$existing->company_code}",
                    tag: 'financial',
                    before: $before,
                    after: $existing->only(['name', 'is_contracted', 'discount_percent']),
                );

                $updated++;
            }
        });

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /** @return list<list<string>> */
    private function exampleRows(): array
    {
        return [
            ['صندوق إعاقة', 'متعاقدة', '20'],
            ['القوات المسلحة الطبية', 'متعاقدة', '10'],
            ['مصر للتأمين', 'متعاقدة', '5'],
            ['شركة خاصة', 'غير متعاقدة', '0'],
        ];
    }

    /** @return list<string> */
    private function rowFromCompany(ContractCompany $company): array
    {
        $discount = (float) ($company->discount_percent ?? 0);

        return [
            $company->name,
            $company->is_contracted ? 'متعاقدة' : 'غير متعاقدة',
            $discount > 0 ? rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.') : '0',
        ];
    }

    /**
     * @param  list<list<string>>  $dataRows
     */
    private function buildWorkbookBinary(array $dataRows): string
    {
        $xlsxPath = tempnam(sys_get_temp_dir(), 'companies_tpl_').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($xlsxPath);

        $writer->getCurrentSheet()->setName(ContractCompanyColumns::SHEET_NAME);
        $writer->addRow(Row::fromValues(ContractCompanyColumns::templateHeaders()));

        foreach ($dataRows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        $bytes = (string) file_get_contents($xlsxPath);
        @unlink($xlsxPath);

        return $bytes;
    }

    /**
     * @return array{map: array<string, int>|null, rows: array<int, list<string>>}
     */
    private function readRowsWithColumnMap(UploadedFile $file): array
    {
        $extension = CatalogImportValidator::extension($file);
        $rawRows = $extension === 'xlsx'
            ? $this->readXlsxRowsRaw($file)
            : $this->readCsvRowsRaw($file);

        $columnMap = null;
        $rows = [];

        foreach ($rawRows as $lineNo => $cols) {
            if ($this->rowIsEmpty($cols)) {
                continue;
            }

            if ($columnMap === null && $this->isHeaderRow($cols)) {
                $columnMap = $this->buildColumnMap($cols);

                continue;
            }

            $rows[$lineNo] = $cols;
        }

        return ['map' => $columnMap, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $headerCells
     * @return array<string, int>
     */
    private function buildColumnMap(array $headerCells): array
    {
        $aliases = ContractCompanyColumns::importAliases();
        $map = [];

        foreach ($headerCells as $index => $cell) {
            $normalized = $this->normalizeHeaderLabel((string) $cell);
            if ($normalized === '') {
                continue;
            }

            $bestField = null;
            $bestScore = 0;

            foreach ($aliases as $field => $labels) {
                if (array_key_exists($field, $map)) {
                    continue;
                }

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

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestField = $field;
                    }
                }
            }

            if ($bestField !== null && $bestScore > 0) {
                $map[$bestField] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $cols
     * @param  array<string, int>|null  $columnMap
     * @return array{name:string, is_contracted_raw:string, discount_raw:string}
     */
    private function parseRowColumns(array $cols, ?array $columnMap): array
    {
        if ($columnMap !== null && $columnMap !== []) {
            return [
                'name' => $this->cellValue($cols, $columnMap, 'name'),
                'is_contracted_raw' => $this->cellValue($cols, $columnMap, 'is_contracted'),
                'discount_raw' => $this->cellValue($cols, $columnMap, 'discount_percent'),
            ];
        }

        return [
            'name' => trim((string) ($cols[0] ?? '')),
            'is_contracted_raw' => trim((string) ($cols[1] ?? '')),
            'discount_raw' => trim((string) ($cols[2] ?? '')),
        ];
    }

    /**
     * @param  list<string>  $cols
     * @param  array<string, int>  $map
     */
    private function cellValue(array $cols, array $map, string $field): string
    {
        if (! array_key_exists($field, $map)) {
            return '';
        }

        return trim((string) ($cols[$map[$field]] ?? ''));
    }

    private function parseContracted(string $raw): bool
    {
        $s = $this->normalizeHeaderLabel($raw);

        if ($s === '') {
            return true;
        }

        if (str_contains($s, 'غير') || in_array($s, ['0', 'no', 'false', 'نقدي', 'غير متعاقده'], true)) {
            return false;
        }

        if (str_contains($s, 'متعاق') || in_array($s, ['1', 'yes', 'true', 'متعاقده'], true)) {
            return true;
        }

        return true;
    }

    private function parseDiscountPercent(string $raw): float
    {
        $s = trim($raw);
        $s = str_replace(['%', '٪', ' '], '', $s);
        $s = str_replace(',', '.', $s);

        if ($s === '' || $s === '—' || $s === '-') {
            return 0.0;
        }

        return (float) $s;
    }

    private function generateCompanyCode(): string
    {
        $last = ContractCompany::orderByDesc('id')->value('company_code');
        $num = $last ? ((int) ltrim(substr($last, 3), '0') ?: 0) + 1 : 1;

        return sprintf('CO-%03d', $num);
    }

    /**
     * @param  list<string>  $cols
     */
    private function isHeaderRow(array $cols): bool
    {
        $joined = $this->normalizeHeaderLabel(implode(' ', $cols));

        return str_contains($joined, 'جهه')
            || str_contains($joined, 'اسم')
            || str_contains($joined, 'name');
    }

    /**
     * @param  list<string>  $cols
     */
    private function rowIsEmpty(array $cols): bool
    {
        foreach ($cols as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
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
     * @return array<int, list<string>>
     */
    private function readXlsxRowsRaw(UploadedFile $file): array
    {
        $reader = new XlsxReader;
        $reader->open($file->getRealPath());

        $sheet = null;
        $fallbackSheet = null;

        foreach ($reader->getSheetIterator() as $candidate) {
            if ($fallbackSheet === null) {
                $fallbackSheet = $candidate;
            }

            if ($candidate->getName() === ContractCompanyColumns::SHEET_NAME) {
                $sheet = $candidate;
                break;
            }
        }

        $sheet ??= $fallbackSheet;

        if ($sheet === null) {
            $reader->close();

            return [];
        }

        $rows = [];
        $lineNo = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $lineNo++;
            $cells = array_map(
                fn ($value) => $this->formatImportCell($value),
                $row->toArray(),
            );

            $rows[$lineNo] = $cells;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @return array<int, list<string>>
     */
    private function readCsvRowsRaw(UploadedFile $file): array
    {
        $content = $this->normalizeToUtf8((string) file_get_contents($file->getRealPath()));

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $rows = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[$i + 1] = $this->parseCsvLine($line);
        }

        return $rows;
    }

    private function normalizeToUtf8(string $content): string
    {
        if ($content === '' || mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        return mb_convert_encoding($content, 'UTF-8', 'Windows-1256, ISO-8859-6, UTF-8');
    }

    /**
     * @return list<string>
     */
    private function parseCsvLine(string $line): array
    {
        if (str_contains($line, ';') && ! str_contains($line, ',')) {
            return array_map('trim', explode(';', $line));
        }

        return array_map('trim', str_getcsv($line));
    }
}
