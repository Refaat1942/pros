{{--
    توقيعات إضافية من مركز الوثائق — مشتركة لكل الوثائق المطبوعة.
    @param array $tpl  قالب الوثيقة (المفتاح extra_signatures: list<{title, name}>)
--}}
@php
    $extraSignatures = array_values(array_filter(
        is_array($tpl[\App\Services\DocumentTemplateService::SIGNATURES_KEY] ?? null)
            ? $tpl[\App\Services\DocumentTemplateService::SIGNATURES_KEY]
            : [],
        fn ($s) => is_array($s) && (trim((string) ($s['title'] ?? '')) !== '' || trim((string) ($s['name'] ?? '')) !== ''),
    ));
@endphp
@if ($extraSignatures !== [])
    <div class="extra-signatures" style="display:flex;flex-wrap:wrap;justify-content:space-around;gap:6mm 10mm;margin:6mm 0 4mm;page-break-inside:avoid;">
        @foreach ($extraSignatures as $sig)
            <div class="extra-signatures__item" style="min-width:42mm;text-align:center;font-weight:700;font-size:10.5pt;">
                <div style="border-bottom:1px solid #111;height:12mm;margin-bottom:2mm;"></div>
                @if (trim((string) ($sig['title'] ?? '')) !== '')
                    <div>{{ $sig['title'] }}</div>
                @endif
                @if (trim((string) ($sig['name'] ?? '')) !== '')
                    <div style="font-weight:600;font-size:10pt;">{{ $sig['name'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endif
