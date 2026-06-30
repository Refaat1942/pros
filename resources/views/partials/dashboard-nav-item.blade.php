@php
    $badgeCount = (int) (($sidebarBadges ?? [])[$slug] ?? 0);
    $showNavBadge = $badgeCount > 0
        || ($slug === 'queue' && array_key_exists('queue', $sidebarBadges ?? []));
    $badgeTitle = match ($slug) {
        'queue' => 'في الانتظار',
        'pricing' => 'بانتظار الاعتماد',
        default => '',
    };
@endphp
<li>
    <a href="{{ route($routePrefix . $slug) }}"
       class="{{ ($activePage ?? '') === $slug ? 'active' : '' }}">
        <span class="nav-icon">{{ $page['icon'] }}</span>
        <span class="nav-label">{{ $page['label'] }}</span>
        @if ($showNavBadge)
            <span class="nav-badge"
                  id="{{ $slug === 'pricing' ? 'sidebarPricingBadge' : ($slug === 'queue' ? 'sidebarQueueBadge' : '') }}"
                  @if ($badgeTitle) title="{{ $badgeTitle }}" @endif>{{ $badgeCount }}</span>
        @endif
    </a>
</li>
