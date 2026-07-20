@php
    $roles = config('dev-role-switcher.roles', []);
    $user = auth()->user();
    $currentPrefix = request()->segment(1);
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

<aside class="dev-role-switcher is-hidden" aria-label="شريط التنقّل بين الأقسام">
    <button type="button"
            class="dev-role-switcher__fab"
            data-role-switcher-show
            aria-label="إظهار شريط التنقّل بين الأقسام"
            title="إظهار شريط التنقّل">
        <span aria-hidden="true">🧭</span>
    </button>

    <div class="dev-role-switcher__panel" role="group" aria-label="لوحات الأقسام">
        <button type="button"
                class="dev-role-switcher__drag"
                data-role-switcher-drag
                aria-label="اسحب لتحريك الشريط"
                title="اسحب للتحريك">
            ⠿
        </button>
        <span class="dev-role-switcher__label">
            <span class="dev-role-switcher__badge">ADMIN</span>
            تنقّل بين الأقسام
        </span>
        <div class="dev-role-switcher__track">
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
                class="dev-role-switcher__expand"
                data-role-switcher-expand
                aria-label="توسيع أو التفاف شريط التنقل"
                title="توسيع / التفاف">
            ↔
        </button>
        <button type="button"
                class="dev-role-switcher__hide"
                data-role-switcher-hide
                aria-label="إخفاء شريط التنقّل"
                title="إخفاء">
            ✕
        </button>
    </div>
</aside>
