@php
    $pages = config("dashboards.{$dashboardKey}.pages", []);
    $canNotifications = auth()->check()
        && auth()->user()?->canViewDashboardPage($dashboardKey, 'notifications');
    $unreadCount = (int) ($headerBadges['notifications'] ?? 0);
    $isNotifActive = ($activePage ?? '') === 'notifications';
@endphp
<div class="page-header-actions">
    @if ($canNotifications)
        <a href="{{ route("{$dashboardKey}.notifications") }}"
           class="dashboard-notif-bell{{ $isNotifActive ? ' is-active' : '' }}"
           id="headerNotifBell"
           title="الإشعارات"
           aria-label="الإشعارات{{ $unreadCount > 0 ? " — {$unreadCount} غير مقروء" : '' }}">
            <span class="dashboard-notif-bell-icon" aria-hidden="true">🔔</span>
            @if ($unreadCount > 0)
                <span class="dashboard-notif-badge" id="headerNotifBadge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
            @else
                <span class="dashboard-notif-badge is-hidden" id="headerNotifBadge" hidden>0</span>
            @endif
        </a>
    @endif
    <button type="button" class="workflow-path-trigger" data-workflow-path-open title="خط سير المسار">🧭 خط السير</button>
    <div class="user-chip">
        <div class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
        <span>{{ auth()->user()->name }}</span>
    </div>
</div>
