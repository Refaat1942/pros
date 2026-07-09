@php
    $branding = $branding ?? app(\App\Services\SettingService::class)->branding();
    $logoRel = $branding['logo_path'] ?? '';
    $logoExists = $logoRel !== '' && is_file(public_path($logoRel));
    $size = $size ?? 'md';
    $showLines = $showLines ?? true;
    $lineClass = $lineClass ?? '';
@endphp
<div class="org-brand-mark org-brand-mark--{{ $size }}" aria-label="{{ $branding['center_name'] }}">
    @if ($logoExists)
        <img class="org-brand-mark__logo" src="{{ asset($logoRel) }}" alt="{{ $branding['center_name'] }}">
    @else
        <div class="org-brand-mark__placeholder" aria-hidden="true">🦿</div>
    @endif
    <div class="org-brand-mark__text">
        @if ($showLines)
            @foreach ($branding['lines'] as $line)
                <p class="org-brand-mark__line {{ $lineClass }}">{{ $line }}</p>
            @endforeach
        @endif
        <p class="org-brand-mark__name">{{ $branding['center_name'] }}</p>
    </div>
</div>
