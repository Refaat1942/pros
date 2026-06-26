{{-- شعار الجهة — نسخة حرارية (أبيض وأسود) للطباعة الرسمية --}}
@php
    $logoSize = $logoSize ?? '32mm';
    $seal = $seal ?? false;
    $logoClass = trim('org-logo-thermal ' . ($logoClass ?? '') . ($seal ? ' org-logo-thermal--seal' : ''));
@endphp
<div class="{{ $logoClass }}" style="--org-logo-size: {{ $logoSize }};" aria-hidden="true">
    <div class="org-logo-thermal__inner">
        <img src="{{ asset('assets/images/org-logo.png') }}"
             alt="شعار مركز الطب الطبيعي والتأهيلي — القوات المسلحة"
             width="340"
             height="340"
             decoding="async">
    </div>
</div>
