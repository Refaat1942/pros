<?php

namespace App\Support;

use App\Models\CaseRecord;
use Illuminate\Support\Collection;

/**
 * تنسيق بيانات طابور الورشة / التسليم — مصدر واحد للأرقام والبنود.
 */
class ManufacturingDeskCaseFormatter
{
    public static function format(CaseRecord $case, string $printRouteName): array
    {
        $bom = null;

        if ($case->relationLoaded('bom') && $case->bom) {
            $aggregated = $case->bom->relationLoaded('items')
                ? BomItemAggregator::byStockCode($case->bom->items)
                : [];

            $bom = $case->bom->only(['id', 'bom_no', 'stage']) + [
                'items_count' => count($aggregated),
                'items' => array_map(
                    fn (array $item) => [
                        'stock_item_code' => $item['stock_item_code'],
                        'name' => $item['name'],
                        'qty' => $item['qty'],
                    ],
                    $aggregated
                ),
            ];
        }

        $payload = $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage',
            'work_order_no', 'patient_type', 'path', 'quote_no',
            'workshop_section_id', 'assigned_technician_id', 'workshop_progress_pct', 'workshop_assigned_at',
        ]) + [
            'company_name' => $case->displayEntity(),
            'entity' => $case->entityPresentation(),
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'work_order_print_url' => $case->work_order_no
                ? route($printRouteName, $case)
                : null,
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'workshop_section' => $case->relationLoaded('workshopSection') && $case->workshopSection
                ? $case->workshopSection->only(['id', 'name', 'code'])
                : null,
            'assigned_technician' => $case->relationLoaded('assignedTechnician') && $case->assignedTechnician
                ? $case->assignedTechnician->only(['id', 'name'])
                : null,
            'bom' => $bom,
        ];

        return $payload;
    }

    /** @return array{wip: int, military: int, civilian: int, total_active: int} */
    public static function workshopSummary(Collection $cases): array
    {
        $mil = $cases->filter(fn ($c) => $c->isMilitary())->count();

        return [
            'wip' => $cases->count(),
            'military' => $mil,
            'civilian' => $cases->count() - $mil,
            'total_active' => $cases->count(),
        ];
    }

    /** @return array{ready: int, military: int, civilian: int, done: int, total_active: int} */
    public static function deliverySummary(Collection $cases): array
    {
        $mil = $cases->filter(fn ($c) => $c->isMilitary())->count();

        return [
            'ready' => $cases->count(),
            'military' => $mil,
            'civilian' => $cases->count() - $mil,
            'done' => CaseRecord::countDeliveredByOps(),
            'total_active' => $cases->count(),
        ];
    }
}
