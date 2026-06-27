<?php

namespace App\Services;

use App\Enums\ManufacturingStage;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\ReturnNote;
use App\Support\ClinicTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مؤشرات ورسوم بيانية وتقارير لوحة ورشة التصنيع — من بيانات حقيقية فقط.
 */
class WorkshopAnalyticsService
{
    public function build(): array
    {
        $now        = ClinicTime::now();
        $today      = ClinicTime::todayDateString();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd   = $now->copy()->subMonth()->endOfMonth();

        $finishedToday = $this->countFinishedBetween($today, $today);
        $finishedMonth = $this->countFinishedBetween($monthStart->toDateString(), $now->toDateString());
        $finishedLastMonth = $this->countFinishedBetween($lastMonthStart->toDateString(), $lastMonthEnd->toDateString());

        $wipCount = CaseRecord::query()->workshopDeskQueue()->count();
        $awaitingDispense = CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
            ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_RAW))
            ->count();

        $finishedMonthCases = $this->finishedBomsBetween($monthStart, $now);
        $pathMonth = $this->countByPatientType($finishedMonthCases);
        $avgDurationDays = $this->averageProductionDays($finishedMonthCases);

        $monthDelta = $finishedLastMonth > 0
            ? round((($finishedMonth - $finishedLastMonth) / $finishedLastMonth) * 100)
            : ($finishedMonth > 0 ? 100 : 0);

        $wipStages = $this->wipStageBreakdown();
        $bomStages = $this->bomStageCounts();

        return [
            'meta' => [
                'generated_at' => ClinicTime::format($now),
                'today'        => ClinicTime::format($now, 'd/m/Y'),
                'month_label'  => $now->translatedFormat('F Y'),
            ],
            'stats' => [
                ['icon' => '✅', 'label' => 'تم تصنيعها — اليوم', 'value' => (string) $finishedToday, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📅', 'label' => 'تم تصنيعها — الشهر', 'value' => (string) $finishedMonth, 'color' => '#047857', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '🏭', 'label' => 'تحت التشغيل الآن', 'value' => (string) $wipCount, 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '📦', 'label' => 'بانتظار صرف المخزن', 'value' => (string) $awaitingDispense, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '🌐', 'label' => 'مدني — الشهر', 'value' => (string) ($pathMonth[Patient::TYPE_CIVILIAN] ?? 0), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '🪖', 'label' => 'عسكري — الشهر', 'value' => (string) ($pathMonth[Patient::TYPE_MILITARY] ?? 0), 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
                ['icon' => '⏱️', 'label' => 'متوسط زمن التصنيع', 'value' => $this->formatDurationDays($avgDurationDays), 'sub' => 'من صرف المخزن حتى الإغلاق', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '📋', 'label' => 'أوامر نشطة', 'value' => (string) CaseRecord::query()->where('stage_key', CaseRecord::STAGE_MANUFACTURING)->whereNotNull('work_order_no')->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
            'charts' => [
                [
                    'type'  => 'column',
                    'title' => '📈 قطع مُنجزة — آخر 7 أيام',
                    'wide'  => true,
                    'unit'  => 'count',
                    'items' => $this->lastSevenDaysFinished(),
                ],
                [
                    'type'  => 'donut',
                    'title' => '⚙️ مراحل التصنيع — تحت التشغيل',
                    'large' => true,
                    'items' => $wipStages['items'],
                    'summary' => [
                        ['label' => 'إجمالي تحت التشغيل', 'value' => (string) $wipStages['total']],
                        ['label' => 'بانتظار الصرف', 'value' => (string) $awaitingDispense, 'color' => '#d97706'],
                        ['label' => 'أكثر مرحلة', 'value' => $wipStages['top_label'], 'color' => '#7c3aed'],
                    ],
                ],
                [
                    'type'  => 'donut',
                    'title' => '🪖 المسار — مُنجَز الشهر',
                    'large' => true,
                    'items' => [
                        ['label' => 'مدني', 'value' => $pathMonth[Patient::TYPE_CIVILIAN] ?? 0, 'color' => '#059669'],
                        ['label' => 'عسكري', 'value' => $pathMonth[Patient::TYPE_MILITARY] ?? 0, 'color' => '#4f46e5'],
                    ],
                    'summary' => [
                        ['label' => 'إجمالي الشهر', 'value' => (string) $finishedMonth],
                        ['label' => 'الشهر السابق', 'value' => (string) $finishedLastMonth],
                        ['label' => 'التغيّر', 'value' => ($monthDelta >= 0 ? '+' : '') . $monthDelta . '%', 'color' => $monthDelta >= 0 ? '#059669' : '#dc2626'],
                    ],
                ],
                [
                    'type'  => 'bar',
                    'title' => '📦 قوائم المواد — خام / تحت التشغيل / تام',
                    'wide'  => true,
                    'items' => [
                        ['label' => 'خام — بانتظار الصرف', 'value' => $bomStages[Bom::STAGE_RAW] ?? 0, 'color' => '#d97706'],
                        ['label' => 'تحت التشغيل', 'value' => $bomStages[Bom::STAGE_WIP] ?? 0, 'color' => '#7c3aed'],
                        ['label' => 'تام — مُغلق', 'value' => $bomStages[Bom::STAGE_FINISHED] ?? 0, 'color' => '#059669'],
                    ],
                ],
                [
                    'type'  => 'bar',
                    'title' => '📊 أسابيع الشهر — مُنجَز',
                    'wide'  => true,
                    'items' => $this->weeklyFinishedThisMonth($monthStart, $now),
                ],
                [
                    'type'  => 'column',
                    'title' => '📆 إنتاج شهري — آخر 6 أشهر',
                    'wide'  => true,
                    'unit'  => 'count',
                    'items' => $this->lastSixMonthsFinished($now),
                ],
                [
                    'type'  => 'bar',
                    'title' => '🔥 أكثر مواد — تحت التشغيل',
                    'items' => $this->topWipBomItems(),
                ],
            ],
            'reports' => $this->reports($monthStart, $now),
        ];
    }

    /** @return array<string, mixed> */
    private function reports($monthStart, $now): array
    {
        $returnsMonth = ReturnNote::query()
            ->whereBetween('created_at', [$monthStart, $now])
            ->count();

        $finishedMonth = $this->countFinishedBetween($monthStart->toDateString(), $now->toDateString());

        return [
            'returns_this_month'   => $returnsMonth,
            'finished_this_month'    => $finishedMonth,
            'month_label'            => $now->translatedFormat('F Y'),
            'top_wip_items'          => array_slice($this->topWipBomItems(), 0, 5),
            'longest_wip'            => $this->longestWipCases(),
            'completed_rows'         => $this->completedReportRows(),
        ];
    }

    private function countFinishedBetween(string $from, string $to): int
    {
        return Bom::query()
            ->where('stage', Bom::STAGE_FINISHED)
            ->whereNotNull('finished_at')
            ->whereDate('finished_at', '>=', $from)
            ->whereDate('finished_at', '<=', $to)
            ->count();
    }

    /** @return Collection<int, Bom> */
    private function finishedBomsBetween($from, $to): Collection
    {
        return Bom::query()
            ->where('stage', Bom::STAGE_FINISHED)
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$from, $to])
            ->with(['caseRecord:id,patient_type'])
            ->get(['id', 'case_id', 'released_at', 'finished_at']);
    }

    /** @param Collection<int, Bom> $boms */
    private function countByPatientType(Collection $boms): array
    {
        return $boms
            ->map(fn (Bom $b) => $b->caseRecord?->patient_type)
            ->filter()
            ->countBy()
            ->all();
    }

    /** @param Collection<int, Bom> $boms */
    private function averageProductionDays(Collection $boms): ?float
    {
        $total = 0.0;
        $count = 0;

        foreach ($boms as $bom) {
            if (! $bom->released_at || ! $bom->finished_at) {
                continue;
            }

            $days = $bom->released_at->diffInDays($bom->finished_at);
            if ($days >= 0) {
                $total += $days;
                $count++;
            }
        }

        return $count > 0 ? round($total / $count, 1) : null;
    }

    private function formatDurationDays(?float $days): string
    {
        if ($days === null) {
            return '—';
        }

        if ($days < 1) {
            return '< 1 ي';
        }

        return rtrim(rtrim(number_format($days, 1), '0'), '.') . ' ي';
    }

    /** @return array{total: int, top_label: string, items: list<array{label: string, value: int, color?: string}>} */
    private function wipStageBreakdown(): array
    {
        $counts = CaseRecord::query()
            ->workshopDeskQueue()
            ->pluck('manufacturing_stage')
            ->countBy()
            ->all();

        if ($counts === []) {
            return [
                'total'     => 0,
                'top_label' => '—',
                'items'     => [['label' => 'لا أوامر تحت التشغيل', 'value' => 0, 'color' => '#94a3b8']],
            ];
        }

        $palette = ['#7c3aed', '#0e7490', '#059669', '#d97706', '#4f46e5', '#dc2626'];
        $i = 0;
        $items = [];

        arsort($counts);

        foreach ($counts as $stage => $value) {
            $items[] = [
                'label' => ManufacturingStage::labelFor($stage),
                'value' => (int) $value,
                'color' => $palette[$i++ % count($palette)],
            ];
        }

        $topKey = array_key_first($counts);

        return [
            'total'     => array_sum($counts),
            'top_label' => ManufacturingStage::labelFor($topKey),
            'items'     => $items,
        ];
    }

    /** @return array<string, int> */
    private function bomStageCounts(): array
    {
        return Bom::query()
            ->whereHas('caseRecord', fn ($q) => $q->whereIn('stage_key', [
                CaseRecord::STAGE_MANUFACTURING,
                CaseRecord::STAGE_READY_DELIVERY,
                CaseRecord::STAGE_DELIVERED,
            ]))
            ->select('stage', DB::raw('COUNT(*) as cnt'))
            ->groupBy('stage')
            ->pluck('cnt', 'stage')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return list<array{label: string, value: int, sub?: string, color?: string}> */
    private function lastSevenDaysFinished(): array
    {
        $items = [];

        for ($i = 6; $i >= 0; $i--) {
            $day   = ClinicTime::now()->subDays($i);
            $date  = $day->toDateString();
            $count = $this->countFinishedBetween($date, $date);
            $isToday = $i === 0;

            $items[] = [
                'label' => $day->format('d/m'),
                'value' => $count,
                'sub'   => $isToday ? 'اليوم' : $day->translatedFormat('D'),
                'color' => $isToday ? '#059669' : '#7c3aed',
            ];
        }

        return $items;
    }

    /** @return list<array{label: string, value: int, color?: string}> */
    private function weeklyFinishedThisMonth($monthStart, $now): array
    {
        $items = [];
        $cursor = $monthStart->copy();
        $week = 1;

        while ($cursor->lessThanOrEqualTo($now)) {
            $weekEnd = $cursor->copy()->addDays(6)->min($now);
            $count = $this->countFinishedBetween($cursor->toDateString(), $weekEnd->toDateString());

            $items[] = [
                'label' => 'أسبوع ' . $week,
                'value' => $count,
                'color' => $week === (int) ceil($now->day / 7) ? '#059669' : '#7c3aed',
            ];

            $cursor->addDays(7);
            $week++;
        }

        if ($items === []) {
            return [['label' => 'لا إنتاج', 'value' => 0, 'color' => '#94a3b8']];
        }

        return $items;
    }

    /** @return list<array{label: string, value: int, sub?: string, color?: string}> */
    private function lastSixMonthsFinished($now): array
    {
        $items = [];
        $palette = ['#94a3b8', '#64748b', '#7c3aed', '#0e7490', '#059669', '#047857'];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end   = $month->copy()->endOfMonth();
            $isCurrent = $i === 0;

            $items[] = [
                'label' => $month->translatedFormat('M Y'),
                'value' => $this->countFinishedBetween($start->toDateString(), $end->toDateString()),
                'sub'   => $isCurrent ? 'الشهر الحالي' : null,
                'color' => $isCurrent ? '#059669' : $palette[$i % count($palette)],
            ];
        }

        return $items;
    }

    /** @return list<array{label: string, value: int, color?: string}> */
    private function topWipBomItems(): array
    {
        $rows = BomItem::query()
            ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_WIP))
            ->select('stock_item_code', DB::raw('MAX(name) as name'), DB::raw('SUM(qty) as total_qty'))
            ->groupBy('stock_item_code')
            ->orderByDesc('total_qty')
            ->limit(6)
            ->get();

        if ($rows->isEmpty()) {
            return [['label' => 'لا بنود تحت التشغيل', 'value' => 0, 'color' => '#94a3b8']];
        }

        $palette = ['#7c3aed', '#0e7490', '#059669', '#d97706', '#4f46e5', '#dc2626'];

        return $rows->values()->map(function ($row, int $i) use ($palette) {
            $label = $row->stock_item_code . ' — ' . ($row->name ?: '—');

            return [
                'label' => $label,
                'value' => (int) $row->total_qty,
                'color' => $palette[$i % count($palette)],
            ];
        })->all();
    }

    /** @return list<array{work_order_no: string, patient: string, stage: string, days: int}> */
    private function longestWipCases(): array
    {
        return CaseRecord::query()
            ->workshopDeskQueue()
            ->with(['patient:id,name', 'bom:id,case_id,released_at'])
            ->orderBy('updated_at')
            ->limit(5)
            ->get()
            ->map(function (CaseRecord $case) {
                $released = $case->bom?->released_at;
                $days = $released ? max(0, (int) $released->diffInDays(ClinicTime::now())) : 0;

                return [
                    'work_order_no' => $case->work_order_no ?? '—',
                    'patient'       => $case->patient?->name ?? '—',
                    'stage'         => ManufacturingStage::labelFor($case->manufacturing_stage),
                    'days'          => $days,
                ];
            })
            ->sortByDesc('days')
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function completedReportRows(): array
    {
        $limit = (int) config('dashboards.table_fetch_limit', 1000);

        return Bom::query()
            ->where('stage', Bom::STAGE_FINISHED)
            ->whereNotNull('finished_at')
            ->with([
                'caseRecord:id,case_no,work_order_no,patient_type,patient_id',
                'caseRecord.patient:id,name',
            ])
            ->orderByDesc('finished_at')
            ->limit($limit)
            ->get()
            ->map(function (Bom $bom) {
                $case = $bom->caseRecord;
                $days = ($bom->released_at && $bom->finished_at)
                    ? max(0, (int) $bom->released_at->diffInDays($bom->finished_at))
                    : null;

                return [
                    'work_order_no'  => $case?->work_order_no ?? '—',
                    'patient'          => $case?->patient?->name ?? $bom->patient_name ?? '—',
                    'case_no'          => $case?->case_no ?? '—',
                    'path'             => $case?->patient_type === Patient::TYPE_MILITARY ? 'عسكري' : 'مدني',
                    'finished_at'      => ClinicTime::format($bom->finished_at, 'd/m/Y H:i'),
                    'duration_days'    => $days !== null ? $days . ' ي' : '—',
                    'bom_no'           => $bom->bom_no ?? '—',
                ];
            })
            ->all();
    }
}
