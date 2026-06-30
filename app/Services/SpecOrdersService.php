<?php

namespace App\Services;

use App\Models\CaseRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * طلبات التوصيف الفني — قائمة الانتظار، فلترة التاريخ، وتصدير.
 */
class SpecOrdersService
{
    /**
     * @return array{from: Carbon, to: Carbon}|null
     */
    public function parseDateRange(?string $from, ?string $to): ?array
    {
        if (! $from && ! $to) {
            return null;
        }

        $fromDate = $from ? Carbon::parse($from) : now()->startOfMonth();
        $toDate   = $to ? Carbon::parse($to) : now();

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return [
            'from' => $fromDate->copy()->startOfDay(),
            'to'   => $toDate->copy()->endOfDay(),
        ];
    }

    public function query(?Carbon $from = null, ?Carbon $to = null, ?string $search = null): Builder
    {
        return CaseRecord::query()
            ->with([
                'patient:id,patient_code,name,patient_type,company_name',
                'techOrderSpec:id,case_id,locked,submitted_at',
            ])
            ->where('stage_key', CaseRecord::STAGE_TECHNICAL)
            ->when($from && $to, fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]))
            ->when($search, function (Builder $q, string $term) {
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('case_no', 'like', "%{$term}%")
                        ->orWhere('order_ref', 'like', "%{$term}%")
                        ->orWhereHas(
                            'patient',
                            fn (Builder $p) => $p->where('name', 'like', "%{$term}%")
                                ->orWhere('patient_code', 'like', "%{$term}%")
                        );
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /** @return Collection<int, CaseRecord> */
    public function list(?Carbon $from = null, ?Carbon $to = null, ?string $search = null): Collection
    {
        return $this->query($from, $to, $search)->get();
    }

    /** @return array{today_from_doctor: int, pending_spec: int} */
    public function stats(?Carbon $from = null, ?Carbon $to = null, ?string $search = null): array
    {
        $cases = $this->list($from, $to, $search);
        $todayStart = now()->startOfDay();

        $todayQuery = CaseRecord::query()->where('created_at', '>=', $todayStart);
        if ($from && $to) {
            $todayQuery->whereBetween('created_at', [$from, $to]);
        }

        return [
            'today_from_doctor' => $todayQuery->count(),
            'pending_spec'      => $cases->count(),
        ];
    }

    /**
     * @return array{title: string, period_label: string, headers: list<string>, rows: list<list<string>>}
     */
    public function exportReport(?Carbon $from = null, ?Carbon $to = null, ?string $search = null): array
    {
        $rows = $this->list($from, $to, $search)->map(fn (CaseRecord $case) => $this->exportRow($case));

        $period = ($from && $to)
            ? 'الفترة: ' . $from->format('d/m/Y') . ' — ' . $to->format('d/m/Y')
            : 'كل الفترات';

        if ($search) {
            $period .= ' | بحث: ' . $search;
        }

        return [
            'title'         => 'طلبات التوصيف الفني',
            'period_label'  => $period,
            'headers'       => ['المريض', 'رقم الحالة', 'رقم الطلب', 'الجهة', 'النوع', 'تاريخ التحويل'],
            'rows'          => $rows->values()->all(),
        ];
    }

    /** @return list<string> */
    public function exportRow(CaseRecord $case): array
    {
        return [
            $case->patient?->name ?? '—',
            $case->case_no,
            $case->order_ref,
            $case->displayEntity(),
            $case->patient_type === 'military' ? 'عسكري' : 'مدني',
            $case->created_at?->format('d/m/Y') ?? '—',
        ];
    }
}
