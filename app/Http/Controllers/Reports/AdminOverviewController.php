<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersAdminDashboard;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Services\BiReportService;
use Illuminate\View\View;

class AdminOverviewController extends Controller
{
    use RendersAdminDashboard;

    public function __construct(private readonly BiReportService $biReportService)
    {
    }

    /**
     * نظرة عامة — KPIs + آخر 5 حركات رقابة.
     */
    public function index(): View
    {
        $board1 = $this->biReportService->boardPatients();
        $board2 = $this->biReportService->boardInventory();
        $board4 = $this->biReportService->boardEntitiesAndCosts();

        return $this->adminPage('overview', [
            'open_cases'         => $board1['open_count'],
            'ready_for_delivery' => CaseRecord::where('stage_key', CaseRecord::STAGE_READY_DELIVERY)->count(),
            'sla_breached'       => $board1['sla_breached'],
            'audit_preview'      => AuditLog::query()
                ->latest('logged_at')
                ->take(5)
                ->get(['user_name', 'action', 'description', 'tag', 'logged_at']),
            'overview_stats'     => [
                [
                    'icon'  => '📂',
                    'label' => 'حالات مفتوحة',
                    'value' => (string) $board1['open_count'],
                    'color' => '#0e7490',
                    'bg'    => 'rgba(14,116,144,0.1)',
                ],
                [
                    'icon'  => '✅',
                    'label' => 'جاهز للتسليم',
                    'value' => (string) CaseRecord::where('stage_key', CaseRecord::STAGE_READY_DELIVERY)->count(),
                    'color' => '#059669',
                    'bg'    => 'rgba(5,150,105,0.1)',
                ],
                [
                    'icon'  => '⏱️',
                    'label' => 'تجاوز SLA',
                    'value' => (string) $board1['sla_breached'],
                    'color' => '#dc2626',
                    'bg'    => 'rgba(220,38,38,0.1)',
                ],
                [
                    'icon'  => '💰',
                    'label' => 'صافي المديونيات',
                    'value' => number_format($board4['net_debts'], 0) . ' ج.م',
                    'color' => '#7c3aed',
                    'bg'    => 'rgba(124,58,237,0.1)',
                ],
            ],
            'case_strip' => [
                'waiting_return' => CaseRecord::where('stage_key', CaseRecord::STAGE_WAITING_RETURN)->count(),
                'in_progress'    => CaseRecord::whereIn('stage_key', [
                    CaseRecord::STAGE_MANUFACTURING,
                    CaseRecord::STAGE_READY_DELIVERY,
                ])->count(),
                'delivered'      => CaseRecord::where('stage_key', CaseRecord::STAGE_DELIVERED)->count(),
            ],
            'inventory_health_pct' => $board2['item_count'] > 0
                ? (int) round((($board2['item_count'] - $board2['low_stock']) / $board2['item_count']) * 100)
                : 0,
        ]);
    }
}
