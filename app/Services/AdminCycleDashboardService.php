<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Services\Dashboard\DashboardQueueService;
use Carbon\Carbon;

/**
 * ملخص طوابير دورة العمل — عدد الطلبات في كل لوحة تحكم (مع فلترة بالتاريخ).
 */
class AdminCycleDashboardService
{
    public function __construct(
        private readonly DashboardQueueService $queues,
    ) {}

    /** @return list<array{key: string, icon: string, label: string, hint: string, count: int, color: string, bg: string}> */
    public function build(Carbon $from, Carbon $to): array
    {
        return [
            [
                'key' => 'reception',
                'icon' => '🏥',
                'label' => 'الاستقبال',
                'hint' => 'بانتظار التحويل للعيادة',
                'count' => $this->queues->receptionQueueCount($from, $to),
                'color' => '#059669',
                'bg' => 'rgba(5,150,105,0.1)',
            ],
            [
                'key' => 'doctor',
                'icon' => '🩺',
                'label' => 'عيادة الطبيب',
                'hint' => 'في العيادة — بانتظار الكشف',
                'count' => $this->queues->doctorClinicQueueCount($from, $to),
                'color' => '#0e7490',
                'bg' => 'rgba(14,116,144,0.1)',
            ],
            [
                'key' => 'spec',
                'icon' => '📐',
                'label' => 'التوصيف الفني',
                'hint' => 'حالات بانتظار التوصيف',
                'count' => $this->queues->specQueueCount($from, $to),
                'color' => '#d97706',
                'bg' => 'rgba(217,119,6,0.1)',
            ],
            [
                'key' => 'adjustments',
                'icon' => '📏',
                'label' => 'المعدلات',
                'hint' => 'مراجعة وإضافة بنود',
                'count' => $this->queues->adjustmentsQueueCount($from, $to),
                'color' => '#7c3aed',
                'bg' => 'rgba(124,58,237,0.1)',
            ],
            [
                'key' => 'operations',
                'icon' => '⚙️',
                'label' => 'مكتب التشغيل',
                'hint' => 'اعتماد وإعادة للمسار',
                'count' => $this->queues->operationsQueueCount($from, $to),
                'color' => '#4f46e5',
                'bg' => 'rgba(79,70,229,0.1)',
            ],
            [
                'key' => 'cashier',
                'icon' => '💵',
                'label' => 'الخزنة',
                'hint' => 'بانتظار تحصيل الدفع النقدي',
                'count' => $this->queues->cashierQueueCount($from, $to),
                'color' => '#0e7490',
                'bg' => 'rgba(14,116,144,0.12)',
            ],
            [
                'key' => 'workshop',
                'icon' => '🏭',
                'label' => 'ورشة التصنيع',
                'hint' => 'أوامر تحت التشغيل',
                'count' => $this->queues->workshopQueueCount($from, $to),
                'color' => '#0e7490',
                'bg' => 'rgba(14,116,144,0.12)',
            ],
            [
                'key' => 'inventory',
                'icon' => '📦',
                'label' => 'المخزن',
                'hint' => 'BOM خام — بانتظار الصرف',
                'count' => $this->queues->warehouseQueueCount($from, $to),
                'color' => '#b45309',
                'bg' => 'rgba(180,83,9,0.1)',
            ],
        ];
    }

    public function totalActive(Carbon $from, Carbon $to): int
    {
        return CaseRecord::query()
            ->where('stage_key', '!=', CaseRecord::STAGE_DELIVERED)
            ->whereBetween('updated_at', [$from, $to])
            ->count();
    }
}
