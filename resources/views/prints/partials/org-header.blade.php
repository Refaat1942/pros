@php
    $branding = ['lines' => ['مركز الأطراف الصناعية'], 'center_name' => 'مركز الأطراف الصناعية', 'logo_path' => 'assets/img/logo.png'];
    try {
        $branding = app(\App\Services\SettingService::class)->branding();
    } catch (\Throwable $e) {
        report($e);
    }
    $dept = $dept ?? null;
    $logoSize = $logoSize ?? '30mm';
    $seal = $seal ?? true;
    $showLogo = $showLogo ?? true;
    $headerMeta = $headerMeta ?? null;
    $lines = $branding['lines'] ?? [];
    if (! is_array($lines) || $lines === []) {
        $lines = ['مركز الأطراف الصناعية'];
    }
@endphp
<header class="doc-header">
    <div class="header-right">
        @foreach ($lines as $line)
            <div>{{ $line }}</div>
        @endforeach
        @if ($dept)
            <div class="dept">{{ $dept }}</div>
        @endif
    </div>
    <div class="header-left">
        @if ($showLogo)
            @include('prints.partials.org-logo', ['logoSize' => $logoSize, 'seal' => $seal])
        @endif
        @if ($headerMeta)
            <div class="header-meta">{!! $headerMeta !!}</div>
        @endif
    </div>
</header>
