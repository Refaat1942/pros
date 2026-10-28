@php
    $autoPrint = $autoPrint ?? true;
    $docTitle = $docTitle ?? '';
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/print-a4.css') }}?v={{ filemtime(public_path('assets/css/print-a4.css')) }}">
{{-- ورقة A4 واحدة لكل وثيقة — تصغير تلقائي لو المحتوى أطول من الصفحة. --}}
<script src="{{ asset('assets/js/shared/print-fit.js') }}?v={{ filemtime(public_path('assets/js/shared/print-fit.js')) }}" defer></script>
