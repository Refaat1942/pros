{{-- شعار الجهة — نسخة حرارية (أبيض وأسود) للطباعة الرسمية مع بديل عند غياب الملف --}}
@php
    $branding = app(\App\Services\SettingService::class)->branding();
    $logoSize = $logoSize ?? '32mm';
    $seal = $seal ?? false;
    $logoClass = trim('org-logo-thermal ' . ($logoClass ?? '') . ($seal ? ' org-logo-thermal--seal' : ''));
    $logoRel = $branding['logo_path'];
    $logoExists = app(\App\Services\SettingService::class)->brandingLogoExists($logoRel);
@endphp
@if ($logoExists)
    <div class="{{ $logoClass }}" style="--org-logo-size: {{ $logoSize }};" aria-hidden="true">
        <div class="org-logo-thermal__inner">
            <img src="{{ asset($logoRel) }}"
                 alt="{{ $branding['center_name'] }}"
                 width="340"
                 height="340"
                 decoding="async">
        </div>
    </div>
@else
    <div class="logo-placeholder" style="width: {{ $logoSize }}; height: {{ $logoSize }};" aria-hidden="true">
        {{ $branding['center_name'] }}
    </div>
@endif
