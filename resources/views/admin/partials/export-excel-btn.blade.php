@php
    $table = $table ?? '';
    $filename = $filename ?? 'تصدير';
@endphp
<button type="button"
        class="btn-export excel"
        data-export-table="{{ $table }}"
        data-export-filename="{{ $filename }}">
    📊 Excel
</button>
