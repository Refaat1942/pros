<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\User;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * تجميع بيانات صفحة نظرة عامة — الإدارة (مع فلترة بالتاريخ ونطاق الصلاحيات).
 */
class AdminOverviewService
{
    public const BI_BOARD_CACHE_PREFIX = 'admin_overview_bi_board_';

    public const FINANCE_SECTION_CACHE_PREFIX = 'admin_overview_finance_';

    public const BI_BOARD_CACHE_VERSION = 'v4';

    public function __construct(
        private readonly AdminReportsHubService $hub,
        private readonly AdminCycleDashboardService $cycle,
        private readonly BiReportService $biReports,
        private readonly AdminOverviewScopeService $scope,
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
    public function pageData(User $user, Carbon $from, Carbon $to): array
    {
        $data = [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'period_label' => $this->periodLabel($from, $to),
        ];

        $cycleKeys = $this->scope->authorizedCycleKeys($user);
        if ($cycleKeys !== []) {
            $data['cycle_cards'] = $this->cycle->build($from, $to, $cycleKeys);
        }

        if ($this->scope->canSeeCycleTotalActive($user)) {
            $data['cycle_total_active'] = $this->cycle->totalActive($from, $to);
        }

        $stripKeys = $this->scope->authorizedCaseStripKeys($user);
        if ($stripKeys !== []) {
            $data['case_strip'] = $this->caseStripCounts($from, $to, $stripKeys);
        }

        foreach ($this->scope->authorizedBiBoardKeys($user) as $boardKey) {
            if ($boardKey === 'board4') {
                $board4 = $this->scopedFinanceBoard($user);
                if ($board4 !== []) {
                    $data[$boardKey] = $board4;
                }

                continue;
            }

            $data[$boardKey] = $this->cachedBiBoard($boardKey);
        }

        return $data;
    }

    /**
     * @param  list<string>  $onlyKeys
     * @return array<string, int>
     */
    public function caseStripCounts(Carbon $from, Carbon $to, array $onlyKeys): array
    {
        $strip = [];

        if (in_array('waiting_return', $onlyKeys, true)) {
            $strip['waiting_return'] = CaseRecord::query()
                ->where('patient_type', Patient::TYPE_CIVILIAN)
                ->where('stage_key', '!=', CaseRecord::STAGE_DELIVERED)
                ->where('stage_key', '!=', CaseRecord::STAGE_CASHIER)
                ->whereHas('quotes', fn ($q) => $q->where('status', Quote::STATUS_ISSUED))
                ->whereBetween('updated_at', [$from, $to])
                ->count();
        }

        if (in_array('awaiting_cashier', $onlyKeys, true)) {
            $strip['awaiting_cashier'] = CaseRecord::query()
                ->awaitingCashier()
                ->whereBetween('updated_at', [$from, $to])
                ->count();
        }

        if (in_array('awaiting_assignment', $onlyKeys, true)) {
            $strip['awaiting_assignment'] = config('workshop.enabled', true)
                ? CaseRecord::query()
                    ->awaitingWorkshopAssignmentApproval()
                    ->whereBetween('updated_at', [$from, $to])
                    ->count()
                : 0;
        }

        if (in_array('in_progress', $onlyKeys, true)) {
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

            $strip['in_progress'] = $inProgressQuery->count();
        }

        if (in_array('delivered', $onlyKeys, true)) {
            $strip['delivered'] = CaseRecord::query()
                ->where('stage_key', CaseRecord::STAGE_DELIVERED)
                ->whereBetween('delivered_at', [$from, $to])
                ->count();
        }

        return $strip;
    }

    public function periodLabel(Carbon $from, Carbon $to): string
    {
        return 'من '.$from->format('Y-m-d').' إلى '.$to->format('Y-m-d');
    }

    /** @return array<string, array<string, mixed>> */
    private function scopedFinanceBoard(User $user): array
    {
        $board = [];

        foreach ($this->scope->authorizedFinanceSectionKeys($user) as $sectionKey) {
            $board[$sectionKey] = $this->cachedFinanceSection($sectionKey);
        }

        return $board;
    }

    /** @return array<string, mixed> */
    private function cachedFinanceSection(string $sectionKey): array
    {
        $cacheKey = self::FINANCE_SECTION_CACHE_PREFIX.$sectionKey.'_'.self::BI_BOARD_CACHE_VERSION;

        return Cache::remember(
            $cacheKey,
            300,
            fn () => match ($sectionKey) {
                'cash' => $this->biReports->boardFinanceCash(),
                'civilian_debt' => $this->biReports->boardFinanceCivilianDebt(),
                'revenue_cost' => $this->biReports->boardFinanceRevenueCost(),
                'military' => $this->biReports->boardFinanceMilitary(),
                'contracts_companies' => $this->biReports->boardFinanceContractsCompanies(),
                default => [],
            },
        );
    }

    /** @return array<string, mixed> */
    private function cachedBiBoard(string $boardKey): array
    {
        $cacheKey = self::BI_BOARD_CACHE_PREFIX.$boardKey.'_'.self::BI_BOARD_CACHE_VERSION;

        return Cache::remember(
            $cacheKey,
            300,
            fn () => match ($boardKey) {
                'board1' => $this->biReports->boardPatients(),
                'board2' => $this->biReports->boardInventory(),
                'board3' => $this->biReports->boardOperations(),
                'board5' => $this->biReports->boardPurchasing(),
                default => [],
            },
        );
    }

    public static function clearBiBoardsCache(): void
    {
        foreach (['board1', 'board2', 'board3', 'board5'] as $boardKey) {
            Cache::forget(self::BI_BOARD_CACHE_PREFIX.$boardKey.'_'.self::BI_BOARD_CACHE_VERSION);
        }

        foreach (['cash', 'civilian_debt', 'revenue_cost', 'military', 'contracts_companies'] as $sectionKey) {
            Cache::forget(self::FINANCE_SECTION_CACHE_PREFIX.$sectionKey.'_'.self::BI_BOARD_CACHE_VERSION);
        }

        foreach (['board1', 'board2', 'board3', 'board4', 'board5'] as $boardKey) {
            Cache::forget(self::BI_BOARD_CACHE_PREFIX.$boardKey.'_v3');
        }

        Cache::forget('admin_overview_bi_boards_v2');
    }
}
