<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\Quote;
use App\Services\DocumentTemplateService;

/**
 * الفاتورة الختامية للحالة بعد التسليم — نفس النموذج الرسمي لعرض السعر بعنوان «فاتورة»،
 * مع رقم الفاتورة وتاريخ التسليم والمدفوع والمتبقي حسب نوع المريض.
 */
final class InvoicePrintPresenter
{
    /**
     * @param  array<string, mixed>|null  $template
     * @return array<string, mixed>
     */
    public static function document(CaseRecord $case, ?array $template = null): array
    {
        $case->loadMissing([
            'patient',
            'bom.items',
            'medicalRecords',
            'contractCompany',
        ]);

        $quote = Quote::query()
            ->with(['items', 'caseRecord'])
            ->where('case_id', $case->id)
            ->when($case->quote_no, fn ($q) => $q->where('quote_no', $case->quote_no))
            ->orderByDesc('id')
            ->first();

        $settings = $template ?? app(DocumentTemplateService::class)->for('quote');
        $entity = $case->entityPresentation();
        $isMilitary = $case->isMilitary();

        if ($quote) {
            $base = QuotePrintPresenter::document($quote, $settings);
            // الطرف سُلِّم — «مدة التوريد من تاريخ العرض» تخص عرض السعر فقط؛ الضمان يبقى.
            $base['spec_rows'] = array_values(array_filter(
                $base['spec_rows'],
                fn (array $row) => $row['label'] !== 'مدة التوريد',
            ));
            $totals = QuotePrintPresenter::fromQuote($quote);
        } else {
            $base = self::fromCaseOnly($case, $settings);
            $total = round((float) ($case->invoice_total ?: $case->total_cost ?: 0), 2);
            $totals = [
                'gross_total' => $total,
                'discount_percent' => 0.0,
                'discount_amount' => 0.0,
                'net_total' => $total,
                'display_total' => $total,
                'has_discount' => false,
            ];
        }

        // العسكري: المستحق = تكلفة الحالة السيادية (نفس قيد المديونية العسكرية).
        if ($isMilitary) {
            $total = round((float) ($case->invoice_total ?: $case->total_cost ?: 0), 2);
            $totals = array_merge($totals, [
                'gross_total' => $total,
                'net_total' => $total,
                'display_total' => $total,
                'discount_amount' => 0.0,
                'discount_percent' => 0.0,
                'has_discount' => false,
            ]);
        }

        $net = (float) $totals['display_total'];
        $paid = round((float) $case->paid, 2);
        $kind = $entity['kind'] ?? '';

        $payer = match (true) {
            $isMilitary => 'على حساب '.($case->sovereign_entity ?: 'القوات المسلحة').' — بدون تحصيل من المريض',
            $kind === PatientEntityPresenter::KIND_CASH => 'المريض — نقداً بالخزنة',
            $kind === PatientEntityPresenter::KIND_CONTRACTED => 'جهة التعاقد — '.($entity['label'] ?? ''),
            default => $entity['label'] ?? '—',
        };

        $deliveredAt = $case->delivered_at;

        return array_merge($base, [
            'invoice_no' => (string) ($case->invoice_no ?? ''),
            'invoice_date' => ClinicTime::format($deliveredAt, 'j/n/Y'),
            'delivered_at' => ClinicTime::format($deliveredAt, 'd/m/Y H:i'),
            'case_no' => (string) ($case->case_no ?? ''),
            'work_order_no' => (string) ($case->work_order_no ?? ''),
            'quote_no' => (string) ($quote?->quote_no ?? ''),
            'entity' => (string) ($entity['label'] ?? $base['entity'] ?? ''),
            'payer' => $payer,
            'is_military' => $isMilitary,
            'is_cash' => $kind === PatientEntityPresenter::KIND_CASH,
            'totals' => $totals,
            'paid' => $kind === PatientEntityPresenter::KIND_CASH ? $paid : null,
            'remaining' => $kind === PatientEntityPresenter::KIND_CASH ? max(0, round($net - $paid, 2)) : null,
        ]);
    }

    /**
     * حالة بلا عرض سعر (العسكري) — البيانات من ملف المريض وقائمة المواد.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function fromCaseOnly(CaseRecord $case, array $settings): array
    {
        $patient = $case->patient;
        $date = $case->delivered_at ?? now();
        $rank = trim((string) ($case->rank ?: $patient?->rank ?: ''));
        $name = trim((string) ($patient?->name ?? ''));
        $record = $case->medicalRecords?->sortByDesc('record_date')->first();

        $rows = collect($case->bom?->items ?? [])
            ->groupBy(fn ($i) => trim((string) $i->group_label))
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

        return [
            'date' => $date->format('j/n/Y'),
            'validity' => '',
            'entity' => (string) ($case->sovereign_entity ?: 'القوات المسلحة'),
            'patient_name' => $rank !== '' && ! str_starts_with($name, $rank) ? "{$rank} / {$name}" : $name,
            'age' => QuotePrintPresenter::ageFromNationalId((string) ($patient?->national_id ?? ''), $date),
            'patient_code' => (string) ($patient?->patient_code ?? ''),
            'injury' => trim((string) ($record?->diagnosis ?? '')),
            'injury_year' => '',
            'spec_rows' => $rows,
            'settings' => $settings,
        ];
    }
}
