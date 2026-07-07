@php
    $contracts = $contracts ?? collect();
@endphp

<div class="panel">
    <div class="panel-header">
        <h3>📑 العقود والاتفاقيات — عرض فقط</h3>
        <span class="badge" id="contractsCount">{{ $contracts->count() }}</span>
    </div>
    <div class="data-toolbar">
        <input type="text" id="contractSearch" placeholder="🔍 بحث بالمريض أو الجهة أو رقم العقد..." autocomplete="off">
        <span class="toolbar-count" id="contractFilterCount">{{ $contracts->count() }} عقد</span>
    </div>
    <div class="panel-body">
        <table data-paginate="10">
            <thead>
                <tr>
                    <th>رقم العقد</th>
                    <th>المريض</th>
                    <th>الجهة الضامنة</th>
                    <th>المبلغ المعتمد</th>
                    <th>تاريخ الاعتماد</th>
                    <th>أمر الشغل</th>
                    <th>المستند</th>
                </tr>
            </thead>
            <tbody id="contractsTable">
                @forelse ($contracts as $contract)
                    <tr class="contract-row"
                        data-search="{{ $contract->contract_no }} {{ $contract->patient_name }} {{ $contract->company_name }} {{ $contract->work_order_no }}">
                        <td><strong style="color:var(--primary);">{{ $contract->contract_no }}</strong></td>
                        <td><strong>{{ $contract->patient_name }}</strong></td>
                        <td>{{ $contract->company_name }}</td>
                        <td><strong>{{ number_format((float)$contract->approved_amount, 0) }} ج.م</strong></td>
                        <td>{{ $contract->approval_date?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="font-mono text-xs">{{ $contract->work_order_no ?? '—' }}</span></td>
                        <td>
                            @if ($contract->letter_path)
                                @php
                                    $letterUrl = route('reception.contracts.letter', $contract);
                                    $letterExt = strtolower(pathinfo($contract->letter_path, PATHINFO_EXTENSION));
                                @endphp
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <button type="button"
                                            class="btn btn-secondary"
                                            style="padding:4px 12px;font-size:11px;"
                                            onclick="openContractLetterView('{{ $letterUrl }}', '{{ addslashes($contract->contract_no) }}', '{{ $letterExt }}')">
                                        👁️ عرض
                                    </button>
                                    <a href="{{ route('reception.contracts.download', $contract) }}"
                                       class="btn btn-secondary"
                                       style="padding:4px 12px;font-size:11px;"
                                       target="_blank">📎 تحميل</a>
                                </div>
                            @else
                                <span style="color:var(--text-muted);font-size:12px;">لا يوجد</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted);">
                            لا توجد عقود مسجلة حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('partials.contract-letter-modal')

@push('scripts')
<script>
(function () {
    var searchEl = document.getElementById('contractSearch');
    var rows     = document.querySelectorAll('.contract-row');
    var countEl  = document.getElementById('contractFilterCount');

    if (!searchEl) return;

    searchEl.addEventListener('input', function () {
        var term = searchEl.value.trim().toUpperCase();
        var visible = 0;
        rows.forEach(function (row) {
            var match = !term || (row.dataset.search || '').toUpperCase().indexOf(term) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (countEl) countEl.textContent = visible + ' عقد';
        if (window.TablePagination) TablePagination.refreshById('contractsTable');
    });
})();
</script>
@endpush
