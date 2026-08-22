<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Support\ClinicTime;
use Carbon\Carbon;

/**
 * تجميع بيانات صفحة نظرة عامة — الإدارة (مع فلترة بالتاريخ).
 */
class AdminOverviewService
{
    public function __construct(
        private readonly AdminReportsHubService $hub,
        private readonly AdminCycleDashboardService $cycle,
        private readonly BiReportService $biReports,
    ) {}

    /** @return array{from: Carbon, to: Carbon} */
    public function parseDateRange(?string $from, ?string $to): array
    {
        $range = $this->hub->parseDateRange($from, $to);

        return [
            'from' => $range['from'] ?? ClinicTime::now()->copy()->startOfMonth()->startOfDay(),
            'to' => $range['to'] ?? ClinicTime::now()->copy()->endOfDay(),
        ];
    }

    /** @return array<string, mixed> */
    public function pageData(Carbon $from, Carbon $to): array
    {
        $boards = \Illuminate\Support\Facades\Cache::remember(
            'admin_overview_bi_boards_v2',
            300,
            fn () => [
                'board1' => $this->biReports->boardPatients(),
                'board2' => $this->biReports->boardInventory(),
                'board3' => $this->biReports->boardOperations(),
                'board4' => $this->biReports->boardEntitiesAndCosts(),
                'board5' => $this->biReports->boardPurchasing(),
            ],
        );

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'period_label' => $this->periodLabel($from, $to),
            'cycle_cards' => $this->cycle->build($from, $to),
            'cycle_total_active' => $this->cycle->totalActive($from, $to),
            'case_strip' => $this->caseStripCounts($from, $to),
            ...$boards,
        ];
    }

    /** @return array{waiting_return: int, awaiting_cashier: int, awaiting_assignment: int, in_progress: int, delivered: int} */
    public function caseStripCounts(Carbon $from, Carbon $to): array
    {
        $waiting = CaseRecord::query()
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->where('stage_key', '!=', CaseRecord::STAGE_DELIVERED)
            ->where('stage_key', '!=', CaseRecord::STAGE_CASHIER)
            ->whereHas('quotes', fn ($q) => $q->where('status', Quote::STATUS_ISSUED))
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $awaitingCashier = CaseRecord::query()
            ->awaitingCashier()
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $awaitingAssignment = config('workshop.enabled', true)
            ? CaseRecord::query()
                ->awaitingWorkshopAssignmentApproval()
                ->whereBetween('updated_at', [$from, $to])
                ->count()
            : 0;

        $assignmentIds = config('workshop.enabled', true)
            ? CaseRecord::query()
                ->awaitingWorkshopAssignmentApproval()
                ->whereBetween('updated_at', [$from, $to])
                ->pluck('id')
            : collect();

        $inProgressQuery = CaseRecord::query()
            ->whereIn('stage_key', [
                CaseRecord::STAGE_MANUFACTURING,
                CaseRecord::STAGE_READY_DELIVERY,
            ])
            ->whereBetween('updated_at', [$from, $to]);

        if ($assignmentIds->isNotEmpty()) {
            $inProgressQuery->whereNotIn('id', $assignmentIds);
        }

        $inProgress = $inProgressQuery->count();

        $delivered = CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->whereBetween('delivered_at', [$from, $to])
            ->count();

        return [
            'waiting_return' => $waiting,
            'awaiting_cashier' => $awaitingCashier,
            'awaiting_assignment' => $awaitingAssignment,
            'in_progress' => $inProgress,
            'delivered' => $delivered,
        ];
    }

    public function periodLabel(Carbon $from, Carbon $to): string
    {
        return 'من '.$from->format('Y-m-d').' إلى '.$to->format('Y-m-d');
    }
}
