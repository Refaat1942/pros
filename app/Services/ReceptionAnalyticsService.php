<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ApprovalContract;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\VisitType;
use App\Support\ClinicTime;
use Illuminate\Support\Collection;

/**
 * مؤشرات ورسوم بيانية لوحة الاستقبال — من بيانات حقيقية فقط.
 */
class ReceptionAnalyticsService
{
    public function build(): array
    {
        $today     = ClinicTime::todayDateString();
        $now       = ClinicTime::now();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd   = $now->copy()->subMonth()->endOfMonth();

        $todayAppointments = Appointment::query()
            ->whereDate('appointment_date', $today)
            ->get(['id', 'status', 'patient_type', 'visit_type_id', 'visit_type', 'transferred_to_clinic', 'transferred_to_clinic_at', 'created_at']);

        $monthAppointments = Appointment::query()
            ->whereBetween('appointment_date', [$monthStart->toDateString(), $now->toDateString()])
            ->get(['id', 'patient_type', 'visit_type_id', 'visit_type']);

        $lastMonthCount = Appointment::query()
            ->whereBetween('appointment_date', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])
            ->count();

        $statusToday = $this->countByStatus($todayAppointments);
        $pathMonth   = $this->countByPatientType($monthAppointments);

        $patientsTotal      = Patient::query()->count();
        $patientsThisMonth  = Patient::query()->where('created_at', '>=', $monthStart)->count();
        $patientsCivilian   = Patient::query()->where('patient_type', Patient::TYPE_CIVILIAN)->count();
        $patientsMilitary   = Patient::query()->where('patient_type', Patient::TYPE_MILITARY)->count();

        $quotesPending = Quote::query()->where('status', Quote::STATUS_PENDING)->count();
        $quotesIssued  = Quote::query()->where('status', Quote::STATUS_ISSUED)->count();
        $quotesApproved = Quote::query()->where('status', Quote::STATUS_APPROVED)->count();

        $contractsThisMonth = ApprovalContract::query()
            ->whereBetween('approval_date', [$monthStart->toDateString(), $now->toDateString()])
            ->count();

        $transferredToday = $todayAppointments->where('transferred_to_clinic', true)->count();
        $avgWaitMinutes   = $this->averageReceptionWaitMinutes($todayAppointments);

        $monthCount = $monthAppointments->count();
        $monthDelta = $lastMonthCount > 0
            ? round((($monthCount - $lastMonthCount) / $lastMonthCount) * 100)
            : ($monthCount > 0 ? 100 : 0);

        return [
            'meta' => [
                'generated_at' => ClinicTime::format($now),
                'today'        => ClinicTime::format($now, 'd/m/Y'),
                'month_label'  => $now->translatedFormat('F Y'),
            ],
            'stats' => [
                ['icon' => '📅', 'label' => 'مواعيد اليوم', 'value' => (string) $todayAppointments->count(), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '⏳', 'label' => 'في الانتظار', 'value' => (string) ($statusToday[Appointment::STATUS_WAITING] ?? 0), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '🏥', 'label' => 'في العيادة', 'value' => (string) ($statusToday[Appointment::STATUS_IN_CLINIC] ?? 0), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '✅', 'label' => 'مكتمل اليوم', 'value' => (string) ($statusToday[Appointment::STATUS_DONE] ?? 0), 'color' => '#047857', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '👤', 'label' => 'مرضى جدد — الشهر', 'value' => (string) $patientsThisMonth, 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '💰', 'label' => 'عروض معلقة', 'value' => (string) $quotesPending, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '📑', 'label' => 'عقود — الشهر', 'value' => (string) $contractsThisMonth, 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
                ['icon' => '⏱️', 'label' => 'متوسط انتظار اليوم', 'value' => $this->formatWaitMinutes($avgWaitMinutes), 'sub' => $transferredToday . ' تحويل للعيادة', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
            ],
            'charts' => [
                [
                    'type'  => 'column',
                    'title' => '📈 مواعيد آخر 7 أيام',
                    'wide'  => true,
                    'unit'  => 'count',
                    'items' => $this->lastSevenDaysSeries(),
                ],
                [
                    'type'  => 'donut',
                    'title' => '📊 حالات مواعيد اليوم',
                    'large' => true,
                    'items' => [
                        ['label' => 'في الانتظار', 'value' => $statusToday[Appointment::STATUS_WAITING] ?? 0, 'color' => '#d97706'],
                        ['label' => 'في العيادة', 'value' => $statusToday[Appointment::STATUS_IN_CLINIC] ?? 0, 'color' => '#0e7490'],
                        ['label' => 'تم التسعير', 'value' => $statusToday[Appointment::STATUS_QUOTED] ?? 0, 'color' => '#7c3aed'],
                        ['label' => 'مكتمل', 'value' => $statusToday[Appointment::STATUS_DONE] ?? 0, 'color' => '#059669'],
                    ],
                    'summary' => [
                        ['label' => 'إجمالي اليوم', 'value' => (string) $todayAppointments->count()],
                        ['label' => 'تحويل للعيادة', 'value' => (string) $transferredToday, 'color' => '#0e7490'],
                        ['label' => 'متوسط الانتظار', 'value' => $this->formatWaitMinutes($avgWaitMinutes), 'color' => '#d97706'],
                    ],
                ],
                [
                    'type'  => 'donut',
                    'title' => '🪖 المسار — مواعيد الشهر',
                    'large' => true,
                    'items' => [
                        ['label' => 'مدني', 'value' => $pathMonth[Patient::TYPE_CIVILIAN] ?? 0, 'color' => '#059669'],
                        ['label' => 'عسكري', 'value' => $pathMonth[Patient::TYPE_MILITARY] ?? 0, 'color' => '#4f46e5'],
                    ],
                    'summary' => [
                        ['label' => 'إجمالي الشهر', 'value' => (string) $monthCount],
                        ['label' => 'الشهر السابق', 'value' => (string) $lastMonthCount],
                        ['label' => 'التغيّر', 'value' => ($monthDelta >= 0 ? '+' : '') . $monthDelta . '%', 'color' => $monthDelta >= 0 ? '#059669' : '#dc2626'],
                    ],
                ],
                [
                    'type'  => 'bar',
                    'title' => '🩺 أنواع الزيارة — الشهر',
                    'wide'  => true,
                    'items' => $this->visitTypeBreakdown($monthAppointments),
                ],
                [
                    'type'  => 'bar',
                    'title' => '👥 سجل المرضى',
                    'items' => [
                        ['label' => 'إجمالي المسجّلين', 'value' => $patientsTotal, 'color' => '#7c3aed'],
                        ['label' => 'جدد هذا الشهر', 'value' => $patientsThisMonth, 'color' => '#059669'],
                        ['label' => 'مدني', 'value' => $patientsCivilian, 'color' => '#0e7490'],
                        ['label' => 'عسكري', 'value' => $patientsMilitary, 'color' => '#4f46e5'],
                    ],
                ],
                [
                    'type'  => 'bar',
                    'title' => '💰 عروض الأسعار (مدني)',
                    'items' => [
                        ['label' => 'معلّقة', 'value' => $quotesPending, 'color' => '#d97706'],
                        ['label' => 'صادرة', 'value' => $quotesIssued, 'color' => '#0e7490'],
                        ['label' => 'معتمدة', 'value' => $quotesApproved, 'color' => '#059669'],
                    ],
                ],
            ],
        ];
    }

    /** @param Collection<int, Appointment> $appointments */
    private function countByStatus(Collection $appointments): array
    {
        return $appointments->groupBy('status')->map->count()->all();
    }

    /** @param Collection<int, Appointment> $appointments */
    private function countByPatientType(Collection $appointments): array
    {
        return $appointments->groupBy('patient_type')->map->count()->all();
    }

    /** @return list<array{label: string, value: int, sub?: string}> */
    private function lastSevenDaysSeries(): array
    {
        $items = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = ClinicTime::now()->subDays($i);
            $date = $day->toDateString();
            $count = Appointment::query()->whereDate('appointment_date', $date)->count();
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

    /**
     * @param Collection<int, Appointment> $appointments
     * @return list<array{label: string, value: int, color?: string}>
     */
    private function visitTypeBreakdown(Collection $appointments): array
    {
        if ($appointments->isEmpty()) {
            return [['label' => 'لا توجد مواعيد', 'value' => 0, 'color' => '#94a3b8']];
        }

        $visitNames = VisitType::query()->pluck('name', 'id');

        $counts = [];

        foreach ($appointments as $appointment) {
            $label = $this->resolveVisitTypeLabel($appointment, $visitNames);
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts);

        $palette = ['#059669', '#0e7490', '#7c3aed', '#d97706', '#4f46e5', '#dc2626'];
        $i = 0;

        return collect($counts)->map(function (int $value, string $label) use (&$i, $palette) {
            return [
                'label' => $label,
                'value' => $value,
                'color' => $palette[$i++ % count($palette)],
            ];
        })->values()->all();
    }

    /** @param Collection<int|string, string> $visitNames */
    private function resolveVisitTypeLabel(Appointment $appointment, Collection $visitNames): string
    {
        if ($appointment->visit_type_id && $visitNames->has($appointment->visit_type_id)) {
            return (string) $visitNames[$appointment->visit_type_id];
        }

        if ($appointment->visit_type !== null && $appointment->visit_type !== '' && ctype_digit((string) $appointment->visit_type)) {
            $name = $visitNames[(int) $appointment->visit_type] ?? null;

            return $name ?: '—';
        }

        return match ($appointment->visit_type) {
            Appointment::VISIT_EXAM     => 'كشف',
            Appointment::VISIT_FOLLOWUP => 'متابعة',
            Appointment::VISIT_FITTING  => 'تجربة',
            Appointment::VISIT_DELIVERY => 'تسليم',
            Appointment::VISIT_REVIEW   => 'مراجعة',
            default                     => $appointment->visit_type ?: '—',
        };
    }

    /** @param Collection<int, Appointment> $todayAppointments */
    private function averageReceptionWaitMinutes(Collection $todayAppointments): int
    {
        $total = 0;
        $count = 0;

        foreach ($todayAppointments->where('transferred_to_clinic', true) as $appointment) {
            $start = $appointment->registrationMoment();
            $end   = $appointment->transferredAt();

            if (! $start || ! $end || $end->lessThan($start)) {
                continue;
            }

            $total += (int) $start->diffInMinutes($end);
            $count++;
        }

        return $count > 0 ? (int) round($total / $count) : 0;
    }

    private function formatWaitMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }

        if ($minutes < 60) {
            return $minutes . ' د';
        }

        $hours = intdiv($minutes, 60);
        $rem   = $minutes % 60;

        return $rem > 0 ? "{$hours} س {$rem} د" : "{$hours} س";
    }
}
