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
        @php
            $orgBranding = app(\App\Services\SettingService::class)->branding();
            $settings = app(\App\Services\SettingService::class);
            $sidebarLogo = $orgBranding['logo_path'] ?? '';
            $hasSidebarLogo = $settings->brandingLogoExists($sidebarLogo);
        @endphp
        @if ($hasSidebarLogo)
            <img class="sidebar-brand__logo"
                 src="{{ $settings->brandingLogoUrl($sidebarLogo) }}"
                 alt="{{ $orgBranding['center_name'] }}">
        @else
            <div class="icon">{{ $sidebar['icon'] ?? '📊' }}</div>
        @endif
        {{-- اسم المركز أعلى القائمة، واسم اللوحة تحته. --}}
        <h2 class="sidebar-brand__center">{{ $orgBranding['center_name'] }}</h2>
        <span>{{ $sidebar['title'] ?? $cfg['title'] }}</span>
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
        @php
            // صفحات مسموحة للمستخدم من أقسام أخرى (مثال: المخزن + «الأصناف والأسعار» من الإدارة) —
            // كانت تفتح بالرابط فقط ولا تظهر في القائمة.
            $authUser = auth()->user();
            $crossPages = [];
            if ($authUser && ! $authUser->isSuperAdmin()) {
                foreach (config('dashboards', []) as $otherKey => $otherCfg) {
                    if ($otherKey === $dashboardKey || $otherKey === 'home' || ! is_array($otherCfg['pages'] ?? null)) {
                        continue;
                    }
                    foreach ($otherCfg['pages'] as $otherSlug => $otherPage) {
                        if (! empty($otherPage['hidden']) || in_array($otherSlug, ['notifications', 'staff'], true)) {
                            continue;
                        }
                        if (! \Illuminate\Support\Facades\Route::has("{$otherKey}.{$otherSlug}")) {
                            continue;
                        }
                        if ($authUser->canViewDashboardPage($otherKey, $otherSlug)) {
                            $crossPages[] = [
                                'url' => route("{$otherKey}.{$otherSlug}"),
                                'icon' => $otherPage['icon'] ?? '📄',
                                'label' => $otherPage['label'] ?? $otherSlug,
                                'section' => $otherCfg['sidebar']['title'] ?? ($otherCfg['title'] ?? $otherKey),
                            ];
                        }
                    }
                }
            }
        @endphp
        @if ($crossPages !== [])
            <ul class="nav-menu nav-menu--grouped nav-menu--cross">
                <li class="nav-group is-open">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-icon">🔗</span>
                        <span class="nav-group-label">صفحات من أقسام أخرى</span>
                        <span class="nav-group-caret" aria-hidden="true">▾</span>
                    </button>
                    <ul class="nav-group-items">
                        @foreach ($crossPages as $cross)
                            <li>
                                <a href="{{ $cross['url'] }}" title="{{ $cross['section'] }}">
                                    <span class="nav-icon">{{ $cross['icon'] }}</span>
                                    <span class="nav-label">{{ $cross['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            </ul>
        @endif
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
