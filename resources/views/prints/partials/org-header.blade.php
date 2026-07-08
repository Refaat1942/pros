{{-- ترويسة رسمية موحّدة للمطبوعات: أسطر الجهة + القسم + الشعار + بيانات جانبية اختيارية --}}
@php
    $branding = app(\App\Services\SettingService::class)->branding();
    $dept = $dept ?? null;
    $logoSize = $logoSize ?? '30mm';
    $seal = $seal ?? true;
    $headerMeta = $headerMeta ?? null;
@endphp
<header class="doc-header">
    <div class="header-right">
        @foreach ($branding['lines'] as $line)
            <div>{{ $line }}</div>
        @endforeach
        @if ($dept)
            <div class="dept">{{ $dept }}</div>
        @endif
    </div>
    <div class="header-left">
        @include('prints.partials.org-logo', ['logoSize' => $logoSize, 'seal' => $seal])
        @if ($headerMeta)
            <div class="header-meta">{!! $headerMeta !!}</div>
        @endif
    </div>
</header>
