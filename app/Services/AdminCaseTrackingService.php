<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Enums\ManufacturingStage;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Quote;
use App\Support\CaseDisplayStatus;
use App\Support\CaseFinancialSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * متابعة الحالات — لوحة الأدمن (بانتظار رجوع / تحت التنفيذ / تم التسليم).
 */
class AdminCaseTrackingService
{
    public function __construct(private readonly BomService $bomService)
    {
    }

    /** @return array{waiting_return: Collection<int, array>, in_progress: Collection<int, array>, delivered: Collection<int, array>, counts: array{waiting_return: int, in_progress: int, delivered: int}} */
    public function buckets(): array
    {
        $cases = CaseRecord::query()
            ->with([
                'patient:id,name,patient_type',
                'bom:id,case_id,stage,bom_no',
                'bom.items:id,bom_id,qty,unit_cost',
                'pricingRequest:id,case_id,request_no,computed_total',
                'quotes:id,case_id,status',
            ])
            ->whereIn('stage_key', [
                CaseRecord::STAGE_WAITING_RETURN,
                CaseRecord::STAGE_MANUFACTURING,
                CaseRecord::STAGE_READY_DELIVERY,
                CaseRecord::STAGE_DELIVERED,
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get();

        $waiting = $cases
            ->where('stage_key', CaseRecord::STAGE_WAITING_RETURN)
            ->filter(fn (CaseRecord $c) => $c->quotes->contains('status', Quote::STATUS_ISSUED))
            ->values();
        $progress = $cases->whereIn('stage_key', [
            CaseRecord::STAGE_MANUFACTURING,
            CaseRecord::STAGE_READY_DELIVERY,
        ])->values();
        $delivered = $cases->where('stage_key', CaseRecord::STAGE_DELIVERED)->values();

        return [
            'waiting_return' => $waiting->map(fn (CaseRecord $c) => $this->formatRow($c))->values(),
            'in_progress'    => $progress->map(fn (CaseRecord $c) => $this->formatRow($c))->values(),
            'delivered'      => $delivered->map(fn (CaseRecord $c) => $this->formatRow($c))->values(),
            'counts'         => [
                'waiting_return' => $waiting->count(),
                'in_progress'    => $progress->count(),
                'delivered'      => $delivered->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function formatRow(CaseRecord $case): array
    {
        $display = CaseDisplayStatus::forCase($case);
        $bom     = $case->bom;
        $canDel  = $this->bomService->canDeliver($case);
        $totalCost = CaseFinancialSummary::totalCost($case);

        return [
            'id'                  => (string) $case->id,
            'caseNo'              => $case->case_no,
            'patient'             => $case->patient?->name ?? '—',
            'company'             => $case->company_name ?? '—',
            'patientType'         => $case->patient_type,
            'orderRef'            => $case->order_ref,
            'quoteId'             => $case->quote_no,
            'quoteDate'           => $this->formatDate($case->quote_date),
            'quoteDaysWaiting'    => $case->quote_date ? (int) $case->quote_date->diffInDays(now()) : 0,
            'pricingRef'          => $case->pricingRequest?->request_no,
            'manufacturingStage'  => $case->manufacturing_stage,
            'manufacturingLabel'  => ManufacturingStage::labelFor($case->manufacturing_stage),
            'approvalDate'        => $this->formatApprovalDate($case),
            'stageKey'            => $case->stage_key,
            'stageLabel'          => $display->label,
            'totalCost'           => $totalCost,
            'paid'                => CaseFinancialSummary::paidAmount($case, $totalCost),
            'deliveredAt'         => $this->formatDate($case->delivered_at),
            'pipelineHtml'        => $this->pipelineHtml($case, $display->label),
            'quoteRefHtml'        => $case->quote_no
                ? '<strong>' . e($case->quote_no) . '</strong>'
                : '—',
            'bom'                 => $bom ? [
                'stage'       => $bom->stage,
                'stageLabel'  => $this->bomStageLabel($bom->stage),
                'badgeClass'  => $this->bomBadgeClass($bom->stage),
            ] : null,
            'canDeliver'          => $canDel,
            'deliverBlockReason'  => $canDel ? null : $this->deliverBlockReason($case, $bom),
        ];
    }

    private function pipelineHtml(CaseRecord $case, string $label): string
    {
        $badge = CaseStage::badgeClassFor($case->stage_key);

        return '<span class="badge ' . e($badge) . '">' . e($label) . '</span>';
    }

    private function bomStageLabel(?string $stage): string
    {
        return match ($stage) {
            Bom::STAGE_RAW      => 'خام',
            Bom::STAGE_WIP      => 'تحت التشغيل',
            Bom::STAGE_FINISHED => 'تام',
            default             => '—',
        };
    }

    private function bomBadgeClass(?string $stage): string
    {
        return match ($stage) {
            Bom::STAGE_RAW      => 'badge-raw',
            Bom::STAGE_WIP      => 'badge-wip',
            Bom::STAGE_FINISHED => 'badge-finished',
            default             => 'default',
        };
    }

    private function deliverBlockReason(CaseRecord $case, ?Bom $bom): string
    {
        if ($case->stage_key !== CaseRecord::STAGE_READY_DELIVERY) {
            return 'الحالة لم تصل بعد لمرحلة «جاهز للتسليم»';
        }

        if ($bom?->stage !== Bom::STAGE_FINISHED) {
            return 'BOM لم يُغلق بعد — يجب أن يكون «تام»';
        }

        return 'غير جاهز للتسليم';
    }

    private function formatApprovalDate(CaseRecord $case): ?string
    {
        if ($case->approval_date) {
            return $this->formatDate($case->approval_date);
        }

        if ($case->approval_confirmed_at) {
            return $case->approval_confirmed_at->format('d/m/Y');
        }

        return null;
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('d/m/Y');
        }

        return Carbon::parse((string) $value)->format('d/m/Y');
    }
}
