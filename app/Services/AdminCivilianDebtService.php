<?php

namespace App\Services;

use App\Enums\DebtStatus;
use App\Models\ContractCompanyDebt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * مديونيات جهات التعاقد المدنية — قراءة وتجميع للوحة الإدارة.
 */
class AdminCivilianDebtService
{
    public function __construct(
        private readonly DebtCollectionEntryService $collectionEntryService,
    ) {}

    public function query(?Request $request = null): Builder
    {
        $request ??= request();

        return ContractCompanyDebt::query()
            ->with(['contractCompany:id,company_code,name,is_military', 'collectionEntries'])
            ->whereHas('contractCompany', fn (Builder $q) => $q->where('is_military', false))
            ->when($request->status, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->company_id, fn (Builder $q, $id) => $q->where('contract_company_id', (int) $id))
            ->when($request->balance === 'outstanding', fn (Builder $q) => $q->whereColumn('due', '>', 'collected'))
            ->when($request->balance === 'settled', fn (Builder $q) => $q->whereColumn('due', '<=', 'collected'))
            ->when($request->search, function (Builder $q, string $search) {
                $q->whereHas('contractCompany', fn (Builder $cq) => $cq
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_code', 'like', "%{$search}%"));
            })
            ->orderByDesc('due')
            ->orderByDesc('id');
    }

    /**
     * @param  Collection<int, ContractCompanyDebt>  $debts
     */
    public function stats(Collection $debts): array
    {
        $totalDue = $debts->sum(fn (ContractCompanyDebt $d) => (float) $d->due);
        $totalCollected = $debts->sum(fn (ContractCompanyDebt $d) => (float) $d->collected);
        $totalRemaining = $debts->sum(fn ($d) => $this->remaining($d));
        $outstanding = $debts->filter(fn ($d) => $this->remaining($d) > 0)->count();

        return [
            ['icon' => '🏢', 'label' => 'جهات مدنية', 'value' => (string) $debts->count(), 'bg' => 'rgba(14,116,144,0.1)', 'color' => '#0e7490', 'key' => 'entities'],
            ['icon' => '💰', 'label' => 'إجمالي المستحق', 'value' => number_format($totalDue, 0), 'bg' => 'rgba(79,70,229,0.1)', 'color' => '#4f46e5', 'key' => 'total_due'],
            ['icon' => '✅', 'label' => 'إجمالي المحصّل', 'value' => number_format($totalCollected, 0), 'bg' => 'rgba(5,150,105,0.1)', 'color' => '#059669', 'key' => 'total_collected'],
            ['icon' => '⏳', 'label' => 'المتبقي للتحصيل', 'value' => number_format($totalRemaining, 0), 'bg' => 'rgba(217,119,6,0.1)', 'color' => '#d97706', 'key' => 'total_remaining'],
            ['icon' => '🔴', 'label' => 'جهات بمتبقٍ', 'value' => (string) $outstanding, 'bg' => 'rgba(220,38,38,0.1)', 'color' => '#dc2626', 'key' => 'outstanding_count'],
        ];
    }

    public function formatDebt(ContractCompanyDebt $debt): array
    {
        $due = (float) $debt->due;
        $collected = (float) $debt->collected;
        $remaining = $this->remaining($debt);
        $collectionPkg = $this->collectionEntryService->packageForPayable($debt, $due, $collected);
        $lastCollectedAt = $collectionPkg['collection_summary']['last_collected_at'] ?? null;

        return $debt->only(['id', 'contract_company_id', 'due', 'collected', 'status']) + [
            'remaining' => $remaining,
            'status_label' => $this->statusLabel((string) $debt->status, $due, $collected),
            'last_collected_at' => $lastCollectedAt,
            'is_frozen' => $due > 0 && $collected >= $due,
            'balance' => $remaining > 0 ? 'outstanding' : 'settled',
            'company' => $debt->relationLoaded('contractCompany') && $debt->contractCompany
                ? $debt->contractCompany->only(['id', 'company_code', 'name', 'is_military'])
                : null,
        ] + $collectionPkg;
    }

    private function remaining(ContractCompanyDebt $debt): float
    {
        return max(0, round((float) $debt->due - (float) $debt->collected, 2));
    }

    private function statusLabel(string $status, float $due, float $collected): string
    {
        if ($due > 0 && $collected >= $due) {
            return 'تم التحصيل';
        }

        return DebtStatus::tryFrom($status)?->label() ?? $status;
    }
}
