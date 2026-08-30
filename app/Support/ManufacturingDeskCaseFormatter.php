<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\CaseWorkshopAssignment;
use App\Support\IssueVoucherPresenter;
use Illuminate\Support\Collection;

/**
 * تنسيق بيانات طابور قسم الإنتاج / التسليم — مصدر واحد للأرقام والبنود.
 */
class ManufacturingDeskCaseFormatter
{
    public static function format(CaseRecord $case, string $printRouteName): array
    {
        $bom = null;
        $issueVoucherUrl = null;

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

            $issueVoucherUrl = str_starts_with($printRouteName, 'workshop.')
                ? route('workshop.issue-voucher.print', $case)
                : IssueVoucherPresenter::printUrl($case->bom);
        }

        $assignments = $case->relationLoaded('workshopAssignments')
            ? $case->workshopAssignments->map(fn (CaseWorkshopAssignment $row) => [
                'workshop_section_id' => $row->workshop_section_id,
                'assigned_technician_id' => $row->assigned_technician_id,
                'workshop_section' => $row->relationLoaded('workshopSection') && $row->workshopSection
                    ? $row->workshopSection->only(['id', 'name', 'code'])
                    : null,
                'assigned_technician' => $row->relationLoaded('assignedTechnician') && $row->assignedTechnician
                    ? $row->assignedTechnician->only(['id', 'name'])
                    : null,
            ])->values()->all()
            : [];

        $payload = $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage',
            'work_order_no', 'patient_type', 'path', 'quote_no',
            'workshop_section_id', 'assigned_technician_id', 'workshop_progress_pct', 'workshop_assigned_at',
            'workshop_assignment_approved_at',
        ]) + [
            'assignment_approved' => $case->isWorkshopAssignmentApproved(),
            'company_name' => $case->displayEntity(),
            'entity' => $case->entityPresentation(),
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'work_order_print_url' => $case->work_order_no
                ? route($printRouteName, $case)
                : null,
            'issue_voucher_print_url' => $issueVoucherUrl,
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'workshop_section' => $case->relationLoaded('workshopSection') && $case->workshopSection
                ? $case->workshopSection->only(['id', 'name', 'code'])
                : null,
            'assigned_technician' => $case->relationLoaded('assignedTechnician') && $case->assignedTechnician
                ? $case->assignedTechnician->only(['id', 'name'])
                : null,
            'workshop_assignments' => $assignments,
            'bom' => $bom,
        ];

        return $payload;
    }

    /** @return array{wip: int, military: int, civilian: int, total_active: int, assigned: int, unassigned: int, technicians: int, avg_progress: int} */
    public static function workshopSummary(Collection $cases): array
    {
        $mil = $cases->filter(fn ($c) => $c->isMilitary())->count();
        $assigned = $cases->filter(fn ($c) => $c->assigned_technician_id || $c->relationLoaded('workshopAssignments') && $c->workshopAssignments->isNotEmpty())->count();
        $technicians = $cases->pluck('assigned_technician_id')->filter()->unique()->count();

        return [
            'wip' => $cases->count(),
            'military' => $mil,
            'civilian' => $cases->count() - $mil,
            'total_active' => $cases->count(),
            'assigned' => $assigned,
            'unassigned' => $cases->count() - $assigned,
            'technicians' => $technicians,
            'avg_progress' => $cases->isEmpty()
                ? 0
                : (int) round($cases->avg(fn ($c) => (int) ($c->workshop_progress_pct ?? 0))),
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
