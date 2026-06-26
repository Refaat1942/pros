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
        'technical'   => '📦',
    ];
@endphp

<aside class="dev-role-switcher" aria-label="شريط التبديل السريع بين الأدوار">
    <div class="dev-role-switcher__inner">
        <span class="dev-role-switcher__label">
            <span class="dev-role-switcher__badge">LOCAL</span>
            {{ $isAdmin ? 'تنقّل بين اللوحات' : 'تبديل سريع' }}
        </span>
        <div class="dev-role-switcher__track" role="group" aria-label="أدوار العرض التوضيحي">
            @if ($isAdmin && $currentPrefix !== 'admin')
                <a href="{{ route('admin.dashboard') }}"
                   class="dev-role-switcher__btn dev-role-switcher__link"
                   title="لوحة الإدارة">
                    <span class="dev-role-switcher__icon" aria-hidden="true">⚙️</span>
                    <span>الإدارة</span>
                </a>
            @endif
            @foreach ($roles as $slug => $meta)
                @php
                    $isActive = $currentPrefix === $slug || ($currentSlug === $slug && ! $isAdmin);
                    $canVisit = $isAdmin ? $user->canAccessDashboard($slug) : false;
                @endphp

                @if ($isAdmin)
                    @if ($canVisit)
                        <a href="{{ route($meta['route']) }}"
                           class="dev-role-switcher__btn dev-role-switcher__link{{ $isActive ? ' is-active' : '' }}"
                           title="{{ $meta['label'] }}">
                            <span class="dev-role-switcher__icon" aria-hidden="true">{{ $icons[$slug] ?? '👤' }}</span>
                            <span>{{ $meta['label'] }}</span>
                        </a>
                    @endif
                @else
                    <form method="POST" action="{{ route('dev.role-switch', $slug) }}" class="dev-role-switcher__form">
                        @csrf
                        <button type="submit"
                                class="dev-role-switcher__btn{{ $isActive ? ' is-active' : '' }}"
                                title="{{ $meta['label'] }} — {{ $slug }}@clinic.com">
                            <span class="dev-role-switcher__icon" aria-hidden="true">{{ $icons[$slug] ?? '👤' }}</span>
                            <span>{{ $meta['label'] }}</span>
                        </button>
                    </form>
                @endif
            @endforeach
        </div>
    </div>
</aside>
