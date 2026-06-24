@php
    $cfg = config("dashboards.{$dashboardKey}");
    $pages = $cfg['pages'] ?? [];
    $sidebar = $cfg['sidebar'] ?? [];
    $routePrefix = $dashboardKey . '.';
@endphp
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="icon">{{ $sidebar['icon'] ?? '📊' }}</div>
        <h2>{{ $sidebar['title'] ?? $cfg['title'] }}</h2>
        <span>{{ $sidebar['subtitle'] ?? '' }}</span>
    </div>
    <div class="sidebar-nav-scroll">
        <ul class="nav-menu">
            @foreach ($pages as $slug => $page)
                @continue(! auth()->user()?->canViewDashboardPage($dashboardKey, $slug))
                @php $badgeCount = (int) (($sidebarBadges ?? [])[$slug] ?? 0); @endphp
                <li>
                    <a href="{{ route($routePrefix . $slug) }}"
                       class="{{ ($activePage ?? '') === $slug ? 'active' : '' }}">
                        <span class="nav-icon">{{ $page['icon'] }}</span>
                        <span class="nav-label">{{ $page['label'] }}</span>
                        @if ($badgeCount > 0)
                            <span class="nav-badge" id="{{ $slug === 'pricing' ? 'sidebarPricingBadge' : '' }}" title="بانتظار الاعتماد">{{ $badgeCount }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">
            @csrf
            <button type="submit" class="btn-sidebar-logout" title="تسجيل الخروج">
                <span class="nav-icon">↩</span> تسجيل الخروج
            </button>
        </form>
    </div>
</aside>
