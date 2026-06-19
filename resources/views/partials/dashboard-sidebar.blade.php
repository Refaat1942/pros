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
                <li>
                    <a href="{{ route($routePrefix . $slug) }}"
                       class="{{ ($activePage ?? '') === $slug ? 'active' : '' }}">
                        <span class="nav-icon">{{ $page['icon'] }}</span> {{ $page['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    @if ($sidebar['show_home_link'] ?? true)
    <div class="sidebar-footer">
        <a href="{{ route('home') }}" class="btn-back">← العودة للصفحة الرئيسية</a>
    </div>
    @endif
</aside>
