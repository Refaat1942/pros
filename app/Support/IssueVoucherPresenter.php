<?php

namespace App\Support;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Quote;
use Illuminate\Support\Collection;

class IssueVoucherPresenter
{
    /**
     * @return array{
     *     voucher_no: string,
     *     work_order_no: ?string,
     *     case_no: ?string,
     *     patient_name: string,
     *     company_name: string,
     *     written_items: ?string,
     *     tech_notes: ?string,
     *     technician_name: ?string,
     *     workshop_section_name: ?string,
     *     spec_groups: list<array{label: string, lines: list<array{stock_item_code: string, name: string, qty: int}>}>,
     *     items: Collection<int, BomItem>
     * }
     */
    public static function fromBom(Bom $bom): array
    {
        $bom->loadMissing([
            'items',
            'caseRecord.patient',
            'caseRecord.techOrderSpec.items',
            'caseRecord.workshopSection:id,name',
            'caseRecord.assignedTechnician:id,name',
        ]);

        $case = $bom->caseRecord;
        $quote = $case
            ? Quote::where('case_id', $case->id)->orderByDesc('id')->first()
            : null;
        $spec = $case?->techOrderSpec;

        return [
            'voucher_no' => $quote?->order_ref ?: ($bom->order_ref ?: ($case?->order_ref ?? '—')),
            'work_order_no' => $case?->work_order_no,
            'case_no' => $case?->case_no,
            'patient_name' => $quote?->patient_name ?: ($bom->patient_name ?: ($case?->patient?->name ?? '—')),
            'company_name' => $quote?->company_name ?: ($case?->displayEntity() ?? '—'),
            'written_items' => $spec?->written_items,
            'tech_notes' => $spec?->tech_notes,
            'technician_name' => $case?->assignedTechnician?->name,
            'workshop_section_name' => $case?->workshopSection?->name,
            'spec_groups' => self::specGroups($spec?->items ?? $bom->items, $bom->items),
            'items' => collect(BomItemAggregator::byStockCode($bom->items)),
        ];
    }

    /**
     * @param  iterable<int, BomItem|\App\Models\TechOrderSpecItem>  $specItems
     * @param  iterable<int, BomItem>  $bomItems
     * @return list<array{label: string, lines: list<array{stock_item_code: string, name: string, qty: int}>}>
     */
    private static function specGroups(iterable $specItems, iterable $bomItems): array
    {
        $groupLabels = [];
        foreach ($bomItems as $item) {
            if ($item instanceof BomItem && filled($item->group_label)) {
                $groupLabels[$item->stock_item_code] = $item->group_label;
            }
        }

        $groups = [];

        foreach ($specItems as $item) {
            $code = $item->stock_item_code ?? '';
            $label = $item->group_label
                ?? ($groupLabels[$code] ?? null)
                ?? 'الطرف الصناعي';

            if (! isset($groups[$label])) {
                $groups[$label] = [];
            }

            $existing = collect($groups[$label])->firstWhere('stock_item_code', $code);
            if ($existing !== null) {
                continue;
            }

            $groups[$label][] = [
                'stock_item_code' => $code,
                'name' => $item->name ?? $code,
                'qty' => (int) ($item->qty ?? 0),
            ];
        }

        if ($groups === []) {
            foreach ($bomItems as $item) {
                if (! $item instanceof BomItem) {
                    continue;
                }
                $label = $item->group_label ?: 'الطرف الصناعي';
                if (! isset($groups[$label])) {
                    $groups[$label] = [];
                }
                $code = $item->stock_item_code;
                if (collect($groups[$label])->contains(fn ($row) => $row['stock_item_code'] === $code)) {
                    continue;
                }
                $groups[$label][] = [
                    'stock_item_code' => $code,
                    'name' => $item->name ?? $code,
                    'qty' => (int) $item->qty,
                ];
            }
        }

        return collect($groups)
            ->map(fn (array $lines, string $label) => ['label' => $label, 'lines' => $lines])
            ->values()
            ->all();
    }

    public static function printUrl(Bom $bom): ?string
    {
        if (! $bom->case_id) {
            return null;
        }

        $quote = Quote::where('case_id', $bom->case_id)->orderByDesc('id')->first();

        if ($quote) {
            return route('technical.quote.print-issue-voucher', $quote);
        }

        return route('technical.bom.print-issue-voucher', $bom);
    }
}
