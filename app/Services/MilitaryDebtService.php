<?php

namespace App\Services;

use App\Models\MilitaryDebt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مديونيات الجهات العسكرية — تحصيل جزئي/كامل مثل المديونيات المدنية.
 */
class MilitaryDebtService
{
    public function recordPayment(MilitaryDebt $debt, float $amount): MilitaryDebt
    {
        if ($debt->isCollected()) {
            throw new \InvalidArgumentException('السجل مجمَّد — تم اعتماد التحصيل مسبقاً.');
        }

        return DB::transaction(function () use ($debt, $amount) {
            $locked = MilitaryDebt::query()->whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);

            $remaining = $this->remaining($locked);
            if ($remaining <= 0) {
                throw new \InvalidArgumentException('لا يوجد متبقٍ للتحصيل على هذا السجل.');
            }

            if ($amount > $remaining) {
                throw new \InvalidArgumentException(
                    'المبلغ المُدخل أكبر من المتبقي للتحصيل (' . number_format($remaining, 2) . ' ج.م).'
                );
            }

            $locked->collected = round((float) $locked->collected + $amount, 2);
            $locked->status    = $this->computeStatus($locked);
            if ($locked->status === MilitaryDebt::STATUS_COLLECTED) {
                $locked->collected_at = $locked->collected_at ?? now();
            }
            $locked->save();

            AuditService::log(
                action:      'payment',
                description: "تسجيل تحصيل مديونية عسكرية — WO: {$locked->work_order_no} بمقدار {$amount}",
                tag:         'financial',
                before:      $before,
                after:       $this->snapshot($locked->fresh()),
            );

            return $locked->fresh();
        });
    }

    /**
     * @param  Collection<int, MilitaryDebt>  $debts
     */
    public function stats(Collection $debts): array
    {
        $totalDue       = $debts->sum(fn (MilitaryDebt $d) => (float) $d->total_cost);
        $totalCollected = $debts->sum(fn (MilitaryDebt $d) => (float) $d->collected);
        $totalRemaining = $debts->sum(fn ($d) => $this->remaining($d));
        $outstanding    = $debts->filter(fn ($d) => $this->remaining($d) > 0)->count();
        $fullyCollected = $debts->filter(fn ($d) => $d->isCollected())->count();

        return [
            ['icon' => '📋', 'label' => 'إجمالي السجلات', 'value' => (string) $debts->count(), 'bg' => 'rgba(79,70,229,0.1)', 'color' => '#4f46e5', 'key' => 'total'],
            ['icon' => '🔴', 'label' => 'سجلات بمتبقٍ', 'value' => (string) $outstanding, 'bg' => 'rgba(220,38,38,0.1)', 'color' => '#dc2626', 'key' => 'outstanding_count'],
            ['icon' => '🟢', 'label' => 'تم التحصيل بالكامل', 'value' => (string) $fullyCollected, 'bg' => 'rgba(5,150,105,0.1)', 'color' => '#059669', 'key' => 'collected_count'],
            ['icon' => '💰', 'label' => 'إجمالي المستحق', 'value' => number_format($totalDue, 0), 'bg' => 'rgba(79,70,229,0.1)', 'color' => '#4f46e5', 'key' => 'total_due'],
            ['icon' => '✅', 'label' => 'إجمالي المحصّل', 'value' => number_format($totalCollected, 0), 'bg' => 'rgba(5,150,105,0.1)', 'color' => '#059669', 'key' => 'total_collected'],
            ['icon' => '⏳', 'label' => 'المتبقي للتحصيل', 'value' => number_format($totalRemaining, 0), 'bg' => 'rgba(217,119,6,0.1)', 'color' => '#d97706', 'key' => 'total_remaining'],
        ];
    }

    public function formatDebt(MilitaryDebt $debt): array
    {
        $due       = (float) $debt->total_cost;
        $collected = (float) $debt->collected;
        $remaining = $this->remaining($debt);

        return [
            'id'                  => $debt->id,
            'case_id'             => $debt->case_id,
            'work_order_no'       => $debt->work_order_no,
            'patient_name'        => $debt->patient_name,
            'patient_national_id' => $debt->patient_national_id,
            'sovereign_entity'    => $debt->sovereign_entity,
            'due'                 => $due,
            'collected'           => $collected,
            'remaining'           => $remaining,
            'total_cost'          => $due,
            'delivered_at'        => $debt->delivered_at ? (string) $debt->delivered_at : null,
            'status'              => $debt->status,
            'status_label'        => $this->statusLabel($debt),
            'collected_at'        => $debt->collected_at?->format('Y-m-d H:i'),
            'is_frozen'           => $debt->isCollected(),
            'balance'             => $remaining > 0 ? 'outstanding' : 'settled',
        ];
    }

    public function remaining(MilitaryDebt $debt): float
    {
        return max(0, round((float) $debt->total_cost - (float) $debt->collected, 2));
    }

    private function computeStatus(MilitaryDebt $debt): string
    {
        $due       = (float) $debt->total_cost;
        $collected = (float) $debt->collected;

        if ($due <= 0) {
            return MilitaryDebt::STATUS_PENDING;
        }

        if ($collected >= $due) {
            return MilitaryDebt::STATUS_COLLECTED;
        }

        if ($collected > 0) {
            return MilitaryDebt::STATUS_PARTIAL;
        }

        return MilitaryDebt::STATUS_PENDING;
    }

    private function statusLabel(MilitaryDebt $debt): string
    {
        if ($debt->isCollected()) {
            return 'تم التحصيل';
        }

        return match ($debt->status) {
            MilitaryDebt::STATUS_PARTIAL => 'مسدَّد جزئياً',
            default                      => 'بانتظار التحصيل',
        };
    }

    private function snapshot(MilitaryDebt $debt): array
    {
        return [
            'total_cost' => (float) $debt->total_cost,
            'collected'  => (float) $debt->collected,
            'status'     => $debt->status,
        ];
    }
}
