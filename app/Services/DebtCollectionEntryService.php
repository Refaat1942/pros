<?php

namespace App\Services;

use App\Models\ContractCompanyDebt;
use App\Models\DebtCollectionEntry;
use App\Models\MilitaryDebt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DebtCollectionEntryService
{
    public function record(Model $payable, float $amount, float $dueTotal): DebtCollectionEntry
    {
        $running = $this->runningCollected($payable);
        $nextNo = $this->nextInstallmentNo($payable);

        return DebtCollectionEntry::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->id,
            'installment_no' => $nextNo,
            'amount' => round($amount, 2),
            'running_collected' => $running,
            'remaining_after' => max(0, round($dueTotal - $running, 2)),
            'recorded_by' => auth()->id(),
            'recorded_by_name' => auth()->user()?->name,
            'collected_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, DebtCollectionEntry>  $entries
     */
    public function summary(Collection $entries, float $due, float $collected): array
    {
        $count = $entries->count();

        if ($count === 0 || $collected <= 0) {
            return [
                'payment_count' => 0,
                'mode' => 'none',
                'mode_label' => 'لم يُحصَّل بعد',
                'first_collected_at' => null,
                'last_collected_at' => null,
            ];
        }

        $sorted = $entries->sortBy('installment_no')->values();
        $first = $sorted->first();
        $last = $sorted->last();
        $isFull = $due > 0 && $collected >= $due;

        if ($count === 1 && $isFull) {
            $mode = 'full_once';
            $modeLabel = 'تحصيل كامل — دفعة واحدة';
        } elseif ($count === 1) {
            $mode = 'partial_once';
            $modeLabel = 'تحصيل جزئي — دفعة واحدة';
        } elseif ($isFull) {
            $mode = 'full_multi';
            $modeLabel = 'تحصيل كامل — '.$count.' دفعات';
        } else {
            $mode = 'partial_multi';
            $modeLabel = 'تحصيل جزئي — '.$count.' دفعات';
        }

        return [
            'payment_count' => $count,
            'mode' => $mode,
            'mode_label' => $modeLabel,
            'first_collected_at' => $first?->collected_at?->format('d/m/Y H:i'),
            'last_collected_at' => $last?->collected_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param  Collection<int, DebtCollectionEntry>  $entries
     */
    public function formatEntries(Collection $entries): array
    {
        return $entries->sortBy('installment_no')->values()->map(fn (DebtCollectionEntry $e) => [
            'installment_no' => $e->installment_no,
            'amount' => (float) $e->amount,
            'running_collected' => (float) $e->running_collected,
            'remaining_after' => (float) $e->remaining_after,
            'recorded_by_name' => $e->recorded_by_name ?? '—',
            'collected_at' => $e->collected_at?->format('d/m/Y H:i'),
        ])->all();
    }

    public function packageForPayable(Model $payable, float $due, float $collected): array
    {
        $entries = $payable->relationLoaded('collectionEntries')
            ? $payable->collectionEntries
            : $payable->collectionEntries()->orderBy('installment_no')->get();

        if ($entries->isEmpty() && $collected > 0) {
            return [
                'collection_summary' => $this->legacySummary($due, $collected),
                'collection_entries' => $this->legacyFormattedEntries($due, $collected),
            ];
        }

        return [
            'collection_summary' => $this->summary($entries, $due, $collected),
            'collection_entries' => $this->formatEntries($entries),
        ];
    }

    private function runningCollected(Model $payable): float
    {
        if ($payable instanceof ContractCompanyDebt) {
            return (float) $payable->collected;
        }

        if ($payable instanceof MilitaryDebt) {
            return (float) $payable->collected;
        }

        return 0;
    }

    private function nextInstallmentNo(Model $payable): int
    {
        $max = DebtCollectionEntry::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->id)
            ->max('installment_no');

        return ((int) $max) + 1;
    }

    /** تحصيل مسجّل على الجهة دون صفوف دفعات (بيانات قديمة قبل سجل الدفعات). */
    private function legacySummary(float $due, float $collected): array
    {
        $isFull = $due > 0 && $collected >= $due;

        return [
            'payment_count' => 1,
            'mode' => $isFull ? 'full_once' : 'partial_once',
            'mode_label' => $isFull ? 'تحصيل كامل — دفعة واحدة' : 'تحصيل جزئي — دفعة واحدة',
            'first_collected_at' => null,
            'last_collected_at' => null,
        ];
    }

    /**
     * @return list<array{installment_no: int, amount: float, running_collected: float, remaining_after: float, recorded_by_name: string, collected_at: null}>
     */
    private function legacyFormattedEntries(float $due, float $collected): array
    {
        return [[
            'installment_no' => 1,
            'amount' => round($collected, 2),
            'running_collected' => round($collected, 2),
            'remaining_after' => max(0, round($due - $collected, 2)),
            'recorded_by_name' => 'ترحيل سابق',
            'collected_at' => null,
        ]];
    }
}
