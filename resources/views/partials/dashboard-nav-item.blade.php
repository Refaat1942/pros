@php
    $badgeCount = (int) (($sidebarBadges ?? [])[$slug] ?? 0);
    $showNavBadge = $badgeCount > 0
        || ($slug === 'queue' && array_key_exists('queue', $sidebarBadges ?? []));
    $badgeId = match ($slug) {
        'pricing' => 'sidebarPricingBadge',
        'queue' => 'sidebarQueueBadge',
        'spec-edit-requests' => 'sidebarSpecEditReqBadge',
        default => '',
    };
    $badgeTitle = match ($slug) {
        'queue' => 'في الانتظار',
        'pricing' => 'بانتظار الاعتماد',
        'spec-edit-requests' => 'بانتظار الموافقة',
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
                  @if ($badgeId) id="{{ $badgeId }}" @endif
                  @if ($badgeTitle) title="{{ $badgeTitle }}" @endif>{{ $badgeCount }}</span>
        @endif
    </a>
</li>
