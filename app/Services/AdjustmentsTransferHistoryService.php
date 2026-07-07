<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * سجل الحالات المحوّلة من المعدلات إلى التكاليف.
 */
class AdjustmentsTransferHistoryService
{
    /** @return array{from: Carbon, to: Carbon} */
    public function parseDateRange(?string $from, ?string $to): array
    {
        $fromDate = $from ? Carbon::parse($from) : now()->startOfMonth();
        $toDate = $to ? Carbon::parse($to) : now();

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return [
            'from' => $fromDate->copy()->startOfDay(),
            'to' => $toDate->copy()->endOfDay(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function list(Carbon $from, Carbon $to, ?string $search = null): array
    {
        return $this->query($from, $to, $search)
            ->values()
            ->all();
    }

    /** @return array{total: int, military: int, civilian: int} */
    public function stats(Carbon $from, Carbon $to, ?string $search = null): array
    {
        $rows = $this->query($from, $to, $search);

        return [
            'total' => $rows->count(),
            'military' => $rows->where('patient_type', 'military')->count(),
            'civilian' => $rows->where('patient_type', 'civilian')->count(),
        ];
    }

    /**
     * @return array{title: string, period_label: string, headers: list<string>, rows: list<list<string>>}
     */
    public function exportReport(Carbon $from, Carbon $to, ?string $search = null): array
    {
        $rows = $this->list($from, $to, $search);

        return [
            'title' => 'سجل المحوّلين من المعدلات للتكاليف',
            'period_label' => 'الفترة: '.ClinicTime::format($from, 'd/m/Y').' — '.ClinicTime::format($to, 'd/m/Y'),
            'headers' => [
                'تاريخ التحويل',
                'رقم الحالة',
                'الطلب',
                'المريض',
                'الجهة',
                'النوع',
                'عدد الأصناف',
                'طلب التسعير',
                'حوّل بواسطة',
                'المرحلة الحالية',
            ],
            'rows' => array_map(fn (array $row) => [
                $row['transferred_at_label'],
                $row['case_no'],
                $row['order_ref'],
                $row['patient_name'],
                $row['display_entity'],
                $row['pathway_label'],
                (string) $row['items_count'],
                $row['pricing_request_no'],
                $row['transferred_by'],
                $row['current_stage_label'],
            ], $rows),
        ];
    }

    private function query(Carbon $from, Carbon $to, ?string $search = null): Collection
    {
        $logs = AuditLog::query()
            ->where('tag', 'pricing')
            ->where('action', 'receive')
            ->where('description', 'like', 'استلام التكاليف%')
            ->whereBetween('logged_at', [$from, $to])
            ->orderByDesc('logged_at')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get();

        if ($logs->isEmpty()) {
            return collect();
        }

        $caseIds = $logs
            ->map(fn (AuditLog $log) => $log->payload_after['case_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $cases = CaseRecord::query()
            ->with([
                'patient:id,name,patient_type',
                'bom.items:id,bom_id',
            ])
            ->whereIn('id', $caseIds)
            ->get()
            ->keyBy('id');

        $term = $search ? mb_strtolower(trim($search)) : null;

        return $logs->map(function (AuditLog $log) use ($cases, $term) {
            $caseId = $log->payload_after['case_id'] ?? null;
            $case = $caseId ? $cases->get($caseId) : null;

            if (! $case) {
                return null;
            }

            $row = $this->formatRow($log, $case);

            if ($term) {
                $haystack = mb_strtolower(implode(' ', [
                    $row['patient_name'],
                    $row['case_no'],
                    $row['order_ref'],
                    $row['display_entity'],
                    $row['pricing_request_no'],
                ]));

                if (! str_contains($haystack, $term)) {
                    return null;
                }
            }

            return $row;
        })->filter()->values();
    }

    /** @return array<string, mixed> */
    private function formatRow(AuditLog $log, CaseRecord $case): array
    {
        $stage = CaseStage::tryFrom($case->stage_key);

        return [
            'id' => $log->id,
            'case_id' => $case->id,
            'case_no' => $case->case_no,
            'order_ref' => $case->order_ref,
            'patient_name' => $case->patient?->name ?? '—',
            'patient_type' => $case->patient_type,
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'display_entity' => $case->displayEntity(),
            'items_count' => $case->bom?->items?->count() ?? 0,
            'pricing_request_no' => (string) ($log->payload_after['pricing_request'] ?? '—'),
            'transferred_by' => $log->user_name ?? '—',
            'transferred_at' => $log->logged_at?->toIso8601String(),
            'transferred_at_label' => ClinicTime::format($log->logged_at),
            'current_stage_label' => $stage?->label() ?? $case->stage_key,
            'search_blob' => implode(' ', [
                $case->patient?->name,
                $case->case_no,
                $case->order_ref,
                $case->displayEntity(),
            ]),
        ];
    }
}
