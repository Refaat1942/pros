<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersAdminDashboard;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\MilitaryDebt;
use App\Models\User;
use App\Services\AdminPatientTrackService;
use App\Services\BiReportService;
use App\Services\Dashboard\DashboardPageDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOverviewController extends Controller
{
    use RendersAdminDashboard;

    public function __construct(
        private readonly BiReportService $biReportService,
        private readonly DashboardPageDataService $pageData,
        private readonly AdminPatientTrackService $patientTrackService,
    ) {
    }

    /**
     * نظرة عامة — KPIs + آخر 5 حركات رقابة.
     */
    public function index(Request $request): View
    {
        $board1 = $this->biReportService->boardPatients();
        $board2 = $this->biReportService->boardInventory();
        $ops    = $this->pageData->resolve('operations', 'operations');

        return $this->adminPage('overview', array_merge($ops, [
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
                    'icon'  => '🪖',
                    'label' => 'مديونيات عسكرية معلقة',
                    'value' => number_format(
                        MilitaryDebt::where('status', MilitaryDebt::STATUS_PENDING)->sum('total_cost'),
                        0
                    ) . ' ج.م',
                    'color' => '#7c3aed',
                    'bg'    => 'rgba(124,58,237,0.1)',
                ],
            ],
            'case_strip' => [
                'waiting_return' => CaseRecord::atOperations()->count(),
                'in_progress'    => CaseRecord::whereIn('stage_key', [
                    CaseRecord::STAGE_MANUFACTURING,
                    CaseRecord::STAGE_READY_DELIVERY,
                ])->count(),
                'delivered'      => CaseRecord::where('stage_key', CaseRecord::STAGE_DELIVERED)->count(),
            ],
            'inventory_health_pct' => $board2['item_count'] > 0
                ? (int) round((($board2['item_count'] - $board2['low_stock']) / $board2['item_count']) * 100)
                : 0,
            'employees_preview' => User::query()
                ->with('role:id,slug,label_ar')
                ->orderByDesc('id')
                ->get(['id', 'name', 'email', 'role_id', 'status', 'last_login_at']),
        ]));
    }

    /** API: مسار المرضى النشطين */
    public function patientTracksApi(Request $request): JsonResponse
    {
        return response()->json(
            $this->patientTrackService->list($request->query('search'))->values()
        );
    }
}
