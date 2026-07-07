<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * تصدير نظرة عامة — CSV متعدد الأقسام حسب الفترة المُفلترة.
 */
class AdminOverviewExportService
{
    public function __construct(
        private readonly AdminOverviewService $overview,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     period_label: string,
     *     sections: list<array{title: string, headers: list<string>, rows: list<list<string|int|float>>}>
     * }
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $data = $this->overview->pageData($from, $to);
        $reports = $data['admin_reports'] ?? [];
        $financial = $reports['financial'] ?? [];
        $inventory = $reports['inventory'] ?? [];
        $bom = $reports['bom'] ?? [];

        $summaryRows = [];

        foreach ($data['cycle_cards'] ?? [] as $card) {
            $summaryRows[] = [$card['label'], (string) $card['count'], $card['hint']];
        }

        $strip = $data['case_strip'] ?? [];
        $caseRows = [
            ['بانتظار موافقة جهات التعاقد', (string) ($strip['waiting_return'] ?? 0)],
            ['بانتظار الدفع النقدي — الخزنة', (string) ($strip['awaiting_cashier'] ?? 0)],
            ['تحت التنفيذ', (string) ($strip['in_progress'] ?? 0)],
            ['تم التسليم', (string) ($strip['delivered'] ?? 0)],
        ];

        $kpiRows = [
            ['الإيرادات', number_format((float) ($financial['monthly_revenue'] ?? 0), 2).' ج.م'],
            ['حالات مدنية مُسلّمة', (string) ($financial['delivered_count'] ?? 0)],
            ['أوامر التشغيل', (string) ($financial['work_orders_count'] ?? 0)],
            ['صحة المخزون', (string) ($inventory['health_pct'] ?? 0).'%'],
            ['صرف المخزن', (string) ($inventory['issues_this_month'] ?? 0).' وحدة'],
            ['حالات مفتوحة (إجمالي)', (string) ($data['cycle_total_active'] ?? 0)],
        ];

        $topItemRows = [];
        foreach ($financial['top_items'] ?? [] as $item) {
            $topItemRows[] = [
                $item['code'] ?? '—',
                $item['name'] ?? '—',
                (string) ($item['qty'] ?? 0),
            ];
        }

        $bomRows = [];
        foreach ($bom['rows'] ?? [] as $row) {
            $bomRows[] = [
                $row['patient'] ?? '—',
                $row['work_order_no'] ?? '—',
                $row['stage_label'] ?? '—',
                (string) ($row['line_count'] ?? 0),
                number_format((float) ($row['value'] ?? 0), 2),
            ];
        }

        $workOrderRows = [];
        foreach ($financial['work_orders'] ?? [] as $wo) {
            $workOrderRows[] = [
                $wo['work_order_no'] ?? '—',
                $wo['patient'] ?? '—',
                $wo['case_no'] ?? '—',
            ];
        }

        return [
            'title' => 'نظرة عامة — الإدارة العليا',
            'period_label' => $data['period_label'] ?? $this->overview->periodLabel($from, $to),
            'sections' => [
                [
                    'title' => 'دورة العمل — الطوابير',
                    'headers' => ['القسم', 'العدد', 'الوصف'],
                    'rows' => $summaryRows,
                ],
                [
                    'title' => 'متابعة الحالات',
                    'headers' => ['الحالة', 'العدد'],
                    'rows' => $caseRows,
                ],
                [
                    'title' => 'مؤشرات المالية والمخزون',
                    'headers' => ['المؤشر', 'القيمة'],
                    'rows' => $kpiRows,
                ],
                [
                    'title' => 'الأصناف الأكثر طلباً (BOM)',
                    'headers' => ['الكود', 'الاسم', 'الكمية'],
                    'rows' => $topItemRows ?: [['—', 'لا توجد بيانات', '0']],
                ],
                [
                    'title' => 'أوامر التشغيل',
                    'headers' => ['أمر التشغيل', 'المريض', 'رقم الحالة'],
                    'rows' => $workOrderRows ?: [['—', 'لا توجد أوامر', '—']],
                ],
                [
                    'title' => 'قوائم BOM',
                    'headers' => ['المريض', 'أمر التشغيل', 'المرحلة', 'البنود', 'قيمة الاصناف (ج.م)'],
                    'rows' => $bomRows ?: [['—', '—', '—', '0', '0.00']],
                ],
            ],
        ];
    }
}
