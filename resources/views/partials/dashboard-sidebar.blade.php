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
    <ul class="nav-menu">
        @foreach ($pages as $slug => $page)
            <li>
                <a href="{{ route($routePrefix . $slug) }}"
                   class="{{ ($activePage ?? '') === $slug ? 'active' : '' }}">
                    <span class="nav-icon">{{ $page['icon'] }}</span> {{ $page['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</aside>
