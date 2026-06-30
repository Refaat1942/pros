@php
    $cfg = config("dashboards.{$dashboardKey}");
    $pages = $cfg['pages'] ?? [];
    $sidebar = $cfg['sidebar'] ?? [];
    $navGroups = $cfg['nav_groups'] ?? null;
    $routePrefix = $dashboardKey . '.';
    $groupedSlugs = [];
@endphp
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="icon">{{ $sidebar['icon'] ?? '📊' }}</div>
        <h2>{{ $sidebar['title'] ?? $cfg['title'] }}</h2>
        <span>{{ $sidebar['subtitle'] ?? '' }}</span>
    </div>
    <div class="sidebar-nav-scroll">
        <ul class="nav-menu{{ $navGroups ? ' nav-menu--grouped' : '' }}">
            @if ($navGroups)
                @foreach ($navGroups as $group)
                    @php
                        $groupPages = [];
                        foreach ($group['pages'] ?? [] as $slug) {
                            if (! isset($pages[$slug]) || ! empty($pages[$slug]['hidden'])) {
                                continue;
                            }
                            if (! auth()->user()?->canViewDashboardPage($dashboardKey, $slug)) {
                                continue;
                            }
                            $groupPages[$slug] = $pages[$slug];
                            $groupedSlugs[] = $slug;
                        }
                        $isOpen = in_array($activePage ?? '', array_keys($groupPages), true);
                    @endphp
                    @if ($groupPages !== [])
                        <li class="nav-group{{ $isOpen ? ' is-open' : '' }}">
                            <button type="button"
                                    class="nav-group-toggle"
                                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
                                <span class="nav-icon">{{ $group['icon'] ?? '📁' }}</span>
                                <span class="nav-group-label">{{ $group['label'] }}</span>
                                <span class="nav-group-caret" aria-hidden="true">▾</span>
                            </button>
                            <ul class="nav-group-items">
                                @foreach ($groupPages as $slug => $page)
                                    @include('partials.dashboard-nav-item', compact('slug', 'page', 'routePrefix', 'activePage'))
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach

                @foreach ($pages as $slug => $page)
                    @continue(! empty($page['hidden']))
                    @continue(in_array($slug, $groupedSlugs, true))
                    @continue(! auth()->user()?->canViewDashboardPage($dashboardKey, $slug))
                    @include('partials.dashboard-nav-item', compact('slug', 'page', 'routePrefix', 'activePage'))
                @endforeach
            @else
                @foreach ($pages as $slug => $page)
                    @continue(! empty($page['hidden']))
                    @continue(! auth()->user()?->canViewDashboardPage($dashboardKey, $slug))
                    @include('partials.dashboard-nav-item', compact('slug', 'page', 'routePrefix', 'activePage'))
                @endforeach
            @endif
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
