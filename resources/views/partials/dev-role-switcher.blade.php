@php
    $roles = config('dev-role-switcher.roles', []);
    $user = auth()->user();
    $currentSlug = $user->role?->slug;
    $currentPrefix = request()->segment(1);
    $isAdmin = $user?->isAdmin() ?? false;
    $icons = [
        'reception'   => '📋',
        'doctor'      => '🩺',
        'spec'        => '📐',
        'adjustments' => '📏',
        'costing'     => '💰',
        'operations'  => '🎯',
        'cashier'     => '💵',
        'workshop'    => '🏭',
        'technical'   => '📦',
    ];
@endphp

<aside class="dev-role-switcher is-collapsed" aria-label="شريط التنقّل بين الأقسام">
    <div class="dev-role-switcher__inner">
        <button type="button"
                class="dev-role-switcher__toggle"
                data-role-switcher-toggle
                aria-expanded="false"
                title="إظهار شريط التنقّل">
            <span aria-hidden="true">🧭</span>
        </button>
        <span class="dev-role-switcher__label">
            <span class="dev-role-switcher__badge">ADMIN</span>
            تنقّل بين الأقسام
        </span>
        <div class="dev-role-switcher__track" role="group" aria-label="لوحات الأقسام" hidden>
            @if ($currentPrefix !== 'admin')
                <a href="{{ route('admin.dashboard') }}"
                   class="dev-role-switcher__btn dev-role-switcher__link"
                   title="لوحة الإدارة">
                    <span class="dev-role-switcher__icon" aria-hidden="true">⚙️</span>
                    <span>الإدارة</span>
                </a>
            @endif
            @foreach ($roles as $slug => $meta)
                @php
                    $isActive = $currentPrefix === $slug;
                    $canVisit = $user->canAccessDashboard($slug);
                @endphp
                @if ($canVisit)
                    <a href="{{ route($meta['route']) }}"
                       class="dev-role-switcher__btn dev-role-switcher__link{{ $isActive ? ' is-active' : '' }}"
                       title="{{ $meta['label'] }}">
                        <span class="dev-role-switcher__icon" aria-hidden="true">{{ $icons[$slug] ?? '👤' }}</span>
                        <span>{{ $meta['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
        <button type="button"
                class="dev-role-switcher__collapse"
                data-role-switcher-toggle
                aria-label="إخفاء شريط التنقّل"
                title="إخفاء">
            ▾
        </button>
    </div>
</aside>
