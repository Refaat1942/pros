<?php

namespace App\Support;

use App\Models\BomItem;
use App\Models\Quote;
use App\Services\DocumentTemplateService;

/**
 * عرض طباعة عرض السعر — إجمالي صافٍ بعد خصم جهة التعاقد.
 */
final class QuotePrintPresenter
{
    /**
     * @return array{
     *     gross_total: float,
     *     discount_percent: float,
     *     discount_amount: float,
     *     net_total: float,
     *     display_total: float,
     *     has_discount: bool
     * }
     */
    public static function fromQuote(Quote $quote): array
    {
        $quote->loadMissing(['caseRecord.contractCompany', 'items']);

        $gross = round((float) $quote->total, 2);
        $case = $quote->caseRecord;

        if (! $case) {
            return self::withoutDiscount($gross);
        }

        $split = ContractBillingSplit::forCase($case, $gross);
        $discountAmount = round($gross - $split['patient_share'], 2);

        if ($discountAmount <= 0) {
            return self::withoutDiscount($gross);
        }

        return [
            'gross_total' => $gross,
            'discount_percent' => (float) $split['company_share_percent'],
            'discount_amount' => $discountAmount,
            'net_total' => (float) $split['patient_share'],
            'display_total' => (float) $split['patient_share'],
            'has_discount' => true,
        ];
    }

    /** المبلغ المعتمد في OCR والطباعة — صافٍ بعد خصم جهة التعاقد. */
    public static function approvedAmount(Quote $quote): float
    {
        $totals = self::fromQuote($quote);
        $display = (float) $totals['display_total'];

        if ($display > 0) {
            return $display;
        }

        $quote->loadMissing(['pricingRequest', 'caseRecord.contractCompany']);

        $pricing = $quote->pricingRequest;
        if (! $pricing) {
            return $display;
        }

        $gross = (float) $pricing->selling_price > 0
            ? (float) $pricing->selling_price
            : (float) $pricing->computed_total;

        if ($gross <= 0) {
            return $display;
        }

        $case = $quote->caseRecord;
        if (! $case) {
            return round($gross, 2);
        }

        return (float) ContractBillingSplit::forCase($case, $gross)['patient_share'];
    }

    /**
     * بيانات النموذج الرسمي لعرض الأسعار (بيانات العرض + المريض + المواصفات + الإجماليات).
     *
     * @return array{
     *     date: string,
     *     validity: string,
     *     entity: string,
     *     patient_name: string,
     *     age: ?int,
     *     injury: string,
     *     injury_year: string,
     *     spec_rows: list<array{label: string, detail: string}>,
     *     totals: array<string, mixed>,
     *     settings: array<string, mixed>
     * }
     *
     * @param  array<string, mixed>|null  $template  قالب «عرض سعر» من مركز الوثائق (حسب القسم/المرحلة)
     */
    public static function document(Quote $quote, ?array $template = null): array
    {
        $quote->loadMissing([
            'items',
            'caseRecord.patient',
            'caseRecord.bom.items',
            'caseRecord.techOrderSpec',
            'caseRecord.medicalRecords',
        ]);

        $case = $quote->caseRecord;
        $patient = $case?->patient;
        $date = $quote->quote_date ?? now();
        $settings = array_map(
            static fn ($value) => is_string($value) ? trim($value) : $value,
            $template ?? app(DocumentTemplateService::class)->for('quote'),
        );

        $rank = trim((string) ($case?->rank ?: $patient?->rank ?: ''));
        $name = trim((string) ($quote->patient_name ?: $patient?->name ?: ''));

        $record = $case?->medicalRecords?->sortByDesc('record_date')->first();

        $specRows = self::specRows($quote);

        if (($settings['supply_period'] ?? '') !== '') {
            $specRows[] = ['label' => 'مدة التوريد', 'detail' => (string) $settings['supply_period']];
        }

        if (($settings['warranty'] ?? '') !== '') {
            $specRows[] = ['label' => 'الضمان', 'detail' => (string) $settings['warranty']];
        }

        return [
            'date' => $date->format('j/n/Y'),
            'validity' => (string) ($settings['validity'] ?? ''),
            'entity' => trim((string) ($quote->company_name ?: $case?->company_name ?: $case?->sovereign_entity ?: 'نقدي')),
            'patient_name' => $rank !== '' && ! str_starts_with($name, $rank) ? "{$rank} / {$name}" : $name,
            'age' => self::ageFromNationalId((string) ($patient?->national_id ?? ''), $date),
            'injury' => trim((string) ($record?->diagnosis ?? '')),
            'injury_year' => '',
            'spec_rows' => $specRows,
            'totals' => self::fromQuote($quote),
            'settings' => $settings,
        ];
    }

    public static function money(float $amount): string
    {
        return number_format($amount, 2);
    }

    /**
     * السن من الرقم القومي المصري (14 رقم: قرن + YYMMDD).
     */
    public static function ageFromNationalId(string $nationalId, ?\DateTimeInterface $at = null): ?int
    {
        $digits = preg_replace('/\D/', '', strtr($nationalId, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])) ?? '';

        if (strlen($digits) !== 14 || ! in_array($digits[0], ['2', '3'], true)) {
            return null;
        }

        $year = ($digits[0] === '2' ? 1900 : 2000) + (int) substr($digits, 1, 2);
        $month = (int) substr($digits, 3, 2);
        $day = (int) substr($digits, 5, 2);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $birth = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
        $age = $birth->diff($at ?? new \DateTimeImmutable)->y;

        return $age > 0 && $age < 120 ? $age : null;
    }

    /**
     * صفوف «البيان / المكونات — المواصفات التفصيلية».
     * الوصف الحر من التوصيف (إن وُجد) له الأولوية — ولا تظهر أسماء الكتالوج حينها.
     * سطر بصيغة «المكوّن: التفاصيل» يُقسَّم على العمودين.
     *
     * @return list<array{label: string, detail: string}>
     */
    private static function specRows(Quote $quote): array
    {
        $case = $quote->caseRecord;
        $written = $case?->resolvedWrittenItems();

        if ($written !== null && trim($written) !== '') {
            $rows = [];
            foreach (preg_split('/\R/u', $written) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (preg_match('/^([^:：]{1,40})[:：]\s*(.+)$/u', $line, $m)) {
                    $rows[] = ['label' => trim($m[1]), 'detail' => trim($m[2])];
                } else {
                    $rows[] = ['label' => '', 'detail' => $line];
                }
            }

            return $rows;
        }

        $items = $quote->items->where('source', BomItem::SOURCE_SPEC)->values();
        if ($items->isEmpty()) {
            $items = $quote->items->values();
        }

        $groupByCode = collect($case?->bom?->items ?? [])
            ->filter(fn ($i) => trim((string) $i->group_label) !== '')
            ->mapWithKeys(fn ($i) => [$i->stock_item_code => trim((string) $i->group_label)]);

        return $items
            ->groupBy(fn ($item) => $groupByCode[$item->stock_item_code] ?? '')
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'detail' => $group->map(function ($item) {
                    $qty = (float) $item->qty;

                    return $qty > 1
                        ? $item->name.' (عدد '.rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.').')'
                        : $item->name;
                })->implode(' - '),
            ])
            ->values()
            ->all();
    }

    /** @return array{gross_total: float, discount_percent: float, discount_amount: float, net_total: float, display_total: float, has_discount: bool} */
    private static function withoutDiscount(float $gross): array
    {
        return [
            'gross_total' => $gross,
            'discount_percent' => 0.0,
            'discount_amount' => 0.0,
            'net_total' => $gross,
            'display_total' => $gross,
            'has_discount' => false,
        ];
    }
}
