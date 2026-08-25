<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

/**
 * تصدير نظرة عامة — CSV متعدد الأقسام حسب الفترة والصلاحيات.
 */
class AdminOverviewExportService
{
    public function __construct(
        private readonly AdminOverviewService $overview,
        private readonly AdminReportsService $reports,
        private readonly AdminOverviewScopeService $scope,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     period_label: string,
     *     sections: list<array{title: string, headers: list<string>, rows: list<list<string|int|float>>}>
     * }
     */
    public function build(User $user, Carbon $from, Carbon $to): array
    {
        $data = $this->overview->pageData($user, $from, $to);
        $sections = [];

        if (! empty($data['cycle_cards'])) {
            $summaryRows = [];
            foreach ($data['cycle_cards'] as $card) {
                $summaryRows[] = [$card['label'], (string) $card['count'], $card['hint']];
            }

            $sections[] = [
                'title' => 'دورة العمل — الطوابير',
                'headers' => ['القسم', 'العدد', 'الوصف'],
                'rows' => $summaryRows,
            ];
        }

        $strip = $data['case_strip'] ?? [];
        if ($strip !== []) {
            $labelMap = [
                'waiting_return' => 'بانتظار موافقة جهات التعاقد',
                'awaiting_cashier' => 'بانتظار الدفع النقدي — الخزنة',
                'awaiting_assignment' => 'بانتظار تخصيص الإنتاج',
                'in_progress' => 'تحت التنفيذ',
                'delivered' => 'تم التسليم',
            ];

            $caseRows = [];
            foreach ($strip as $key => $count) {
                $caseRows[] = [$labelMap[$key] ?? $key, (string) $count];
            }

            $sections[] = [
                'title' => 'متابعة الحالات',
                'headers' => ['الحالة', 'العدد'],
                'rows' => $caseRows,
            ];
        }

        $kpiRows = [];
        $reports = null;
        $board4 = $data['board4'] ?? [];

        if ($this->scope->canSeeBundle($user, 'export_finance_revenue_kpis')) {
            $reports ??= $this->reports->build($from, $to);
            $financial = $reports['financial'] ?? [];
            $kpiRows[] = ['الإيرادات', number_format((float) ($financial['monthly_revenue'] ?? 0), 2).' ج.م'];
            $kpiRows[] = ['حالات مدنية مُسلّمة', (string) ($financial['delivered_count'] ?? 0)];
        }

        if ($this->scope->canSeeBundle($user, 'export_finance_civilian_debt_kpis')) {
            $civilianDebt = $board4['civilian_debt'] ?? [];
            $kpiRows[] = [
                'مديونيات جهات التعاقد — المتبقي',
                number_format((float) ($civilianDebt['net_debts'] ?? 0), 2).' ج.م',
            ];
        }

        if ($this->scope->canSeeBundle($user, 'export_finance_cash_kpis')) {
            $cash = $board4['cash'] ?? [];
            $kpiRows[] = [
                'محصّل نقدي — الخزنة',
                number_format((float) ($cash['cash_collected_total'] ?? 0), 2).' ج.م',
            ];
            $kpiRows[] = [
                'بانتظار الدفع — الخزنة',
                (string) ($cash['cash_awaiting_payment'] ?? 0),
            ];
        }

        if ($this->scope->canSeeBundle($user, 'export_finance_military_kpis')) {
            $military = $board4['military'] ?? [];
            $kpiRows[] = [
                'مديونيات عسكرية — بانتظار التحصيل',
                number_format((float) ($military['military_debt_pending'] ?? 0), 2).' ج.م',
            ];
            $kpiRows[] = [
                'مديونيات عسكرية — محصّلة',
                number_format((float) ($military['military_debt_collected'] ?? 0), 2).' ج.م',
            ];
            $kpiRows[] = [
                'التكلفة المجمعة — عسكري',
                number_format((float) ($military['military_aggregated_cost'] ?? 0), 2).' ج.م',
            ];
        }

        if ($this->scope->canSeeBundle($user, 'export_operations_kpis')) {
            $reports ??= $this->reports->build($from, $to);
            $financial = $reports['financial'] ?? [];
            $kpiRows[] = ['أوامر التشغيل', (string) ($financial['work_orders_count'] ?? 0)];
        }

        if ($this->scope->canSeeBundle($user, 'export_inventory_kpis')) {
            $reports ??= $this->reports->build($from, $to);
            $inventory = $reports['inventory'] ?? [];
            $kpiRows[] = ['صحة المخزون', (string) ($inventory['health_pct'] ?? 0).'%'];
            $kpiRows[] = ['صرف المخزن', (string) ($inventory['issues_this_month'] ?? 0).' وحدة'];
        }

        if (isset($data['cycle_total_active'])) {
            $kpiRows[] = ['حالات مفتوحة (إجمالي)', (string) $data['cycle_total_active']];
        }

        if ($kpiRows !== []) {
            $sections[] = [
                'title' => 'مؤشرات المالية والمخزون',
                'headers' => ['المؤشر', 'القيمة'],
                'rows' => $kpiRows,
            ];
        }

        if ($this->scope->canSeeBundle($user, 'export_finance_revenue_kpis')) {
            $reports ??= $this->reports->build($from, $to);
            $financial = $reports['financial'] ?? [];
            $topItemRows = [];
            foreach ($financial['top_items'] ?? [] as $item) {
                $topItemRows[] = [
                    $item['code'] ?? '—',
                    $item['name'] ?? '—',
                    (string) ($item['qty'] ?? 0),
                ];
            }

            $sections[] = [
                'title' => 'الأصناف الأكثر طلباً (BOM)',
                'headers' => ['رقم الصنف', 'الاسم', 'الرصيد'],
                'rows' => $topItemRows ?: [['—', 'لا توجد بيانات', '0']],
            ];
        }

        if ($this->scope->canSeeBundle($user, 'export_finance_civilian_debt_kpis')) {
            $civilianDebt = $board4['civilian_debt'] ?? [];
            $debtRows = [];
            foreach ($civilianDebt['company_debts'] ?? [] as $debt) {
                $debtRows[] = [
                    $debt['company_name'] ?? '—',
                    number_format((float) ($debt['due'] ?? 0), 2),
                    number_format((float) ($debt['collected'] ?? 0), 2),
                    number_format((float) ($debt['remaining'] ?? 0), 2),
                ];
            }

            $sections[] = [
                'title' => 'مديونيات جهات التعاقد',
                'headers' => ['الجهة', 'المستحق', 'المحصّل', 'المتبقي'],
                'rows' => $debtRows ?: [['—', '0.00', '0.00', '0.00']],
            ];
        }

        if ($this->scope->canSeeBundle($user, 'export_operations_kpis')
            && $this->scope->canSeeBundle($user, 'bi_board1_patients')) {
            $reports ??= $this->reports->build($from, $to);
            $financial = $reports['financial'] ?? [];
            $workOrderRows = [];
            foreach ($financial['work_orders'] ?? [] as $wo) {
                $workOrderRows[] = [
                    $wo['work_order_no'] ?? '—',
                    $wo['patient'] ?? '—',
                    $wo['case_no'] ?? '—',
                ];
            }

            $sections[] = [
                'title' => 'أوامر التشغيل',
                'headers' => ['أمر التشغيل', 'المريض', 'رقم الحالة'],
                'rows' => $workOrderRows ?: [['—', 'لا توجد أوامر', '—']],
            ];
        }

        if ($this->scope->canSeeBundle($user, 'export_bom_detail')) {
            $reports ??= $this->reports->build($from, $to);
            $bom = $reports['bom'] ?? [];
            $bomRows = [];
            $canShowPatients = $this->scope->canSeeBundle($user, 'bi_board1_patients');
            foreach ($bom['rows'] ?? [] as $row) {
                if ($canShowPatients) {
                    $bomRows[] = [
                        $row['patient'] ?? '—',
                        $row['work_order_no'] ?? '—',
                        $row['stage_label'] ?? '—',
                        (string) ($row['line_count'] ?? 0),
                        number_format((float) ($row['value'] ?? 0), 2),
                    ];
                } else {
                    $bomRows[] = [
                        $row['work_order_no'] ?? '—',
                        $row['stage_label'] ?? '—',
                        (string) ($row['line_count'] ?? 0),
                        number_format((float) ($row['value'] ?? 0), 2),
                    ];
                }
            }

            $sections[] = [
                'title' => 'قوائم BOM',
                'headers' => $canShowPatients
                    ? ['المريض', 'أمر التشغيل', 'المرحلة', 'البنود', 'قيمة الاصناف (ج.م)']
                    : ['أمر التشغيل', 'المرحلة', 'البنود', 'قيمة الاصناف (ج.م)'],
                'rows' => $bomRows ?: (
                    $canShowPatients
                        ? [['—', '—', '—', '0', '0.00']]
                        : [['—', '—', '0', '0.00']]
                ),
            ];
        }

        return [
            'title' => 'نظرة عامة — الإدارة العليا',
            'period_label' => $data['period_label'] ?? $this->overview->periodLabel($from, $to),
            'sections' => $sections,
        ];
    }
}
