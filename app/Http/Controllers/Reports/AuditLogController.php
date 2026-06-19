<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersAdminDashboard;
use App\Models\AuditLog;
use App\Traits\PaginationTrait;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * عارض سجل الرقابة — للقراءة فقط (لا store/update/destroy).
 */
class AuditLogController extends Controller
{
    use RendersAdminDashboard;
    use PaginationTrait;

    /**
     * سجل الرقابة — مُرقَّم مع فلاتر.
     */
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->when($request->tag, fn ($q, $t) => $q->where('tag', $t))
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('logged_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('logged_at', '<=', $d))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('user_name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            }))
            ->latest('logged_at')
            ->paginate($this->perPage())
            ->withQueryString();

        $actionCounts = AuditLog::query()
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        return $this->adminPage('audit', [
            'auditLogs'    => $logs,
            'audit_stats'  => [
                [
                    'icon'  => '📝',
                    'label' => 'إجمالي العمليات',
                    'value' => (string) AuditLog::count(),
                    'bg'    => 'rgba(124,58,237,0.1)',
                ],
                [
                    'icon'  => '➕',
                    'label' => 'إنشاء',
                    'value' => (string) ($actionCounts['create'] ?? 0),
                    'color' => '#059669',
                    'bg'    => 'rgba(5,150,105,0.1)',
                ],
                [
                    'icon'  => '✏️',
                    'label' => 'تحديث',
                    'value' => (string) (($actionCounts['update'] ?? 0) + ($actionCounts['deliver'] ?? 0)),
                    'color' => '#d97706',
                    'bg'    => 'rgba(217,119,6,0.1)',
                ],
                [
                    'icon'  => '🚫',
                    'label' => 'محظور',
                    'value' => (string) ($actionCounts['blocked'] ?? 0),
                    'color' => '#dc2626',
                    'bg'    => 'rgba(220,38,38,0.1)',
                ],
            ],
            'filterTags'    => AuditLog::query()->distinct()->orderBy('tag')->pluck('tag')->filter(),
            'filterActions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')->filter(),
            'filters'       => $request->only(['tag', 'action', 'user_id', 'date_from', 'date_to', 'search']),
        ]);
    }
}
