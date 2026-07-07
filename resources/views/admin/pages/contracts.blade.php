@php
    $contracts = $contracts ?? collect();
@endphp

<div id="analytics-contracts" class="ck-analytics" data-static-ui="1">
    <div class="ck-stats">
        @foreach ($contracts_stats ?? [] as $stat)
            <div class="ck-stat">
                <div class="ck-stat-icon" style="background:{{ $stat['bg'] ?? 'rgba(100,116,139,0.1)' }}">{{ $stat['icon'] }}</div>
                <div>
                    <div class="ck-stat-label">{{ $stat['label'] }}</div>
                    <div class="ck-stat-value" @if(!empty($stat['color'])) style="color:{{ $stat['color'] }}" @endif>{{ $stat['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h3>📑 موافقات جهات التعاقد — تحكم كامل</h3>
        <span class="badge" id="contractsCount">{{ $contracts->count() }}</span>
    </div>
    <div class="data-toolbar">
        <input type="text" id="contractSearch" placeholder="🔍 بحث بالمريض أو الجهة أو رقم الموافقة..." autocomplete="off">
        <select id="contractCompanyFilter" style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;font-family:inherit;font-size:13px;">
            <option value="">كل الجهات</option>
            @foreach ($contracts->pluck('company_name')->unique()->sort()->values() as $company)
                <option value="{{ $company }}">{{ $company }}</option>
            @endforeach
        </select>
        <span class="toolbar-count" id="contractFilterCount">{{ $contracts->count() }} موافقة</span>
    </div>
    <div class="panel-body">
        <table data-paginate="15">
            <thead>
                <tr>
                    <th>رقم الموافقة</th>
                    <th>المريض</th>
                    <th>الجهة الضامنة</th>
                    <th>المبلغ المعتمد</th>
                    <th>تاريخ الاعتماد</th>
                    <th>أمر الشغل</th>
                    <th>المستند</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="contractsTable">
                @forelse ($contracts as $contract)
                    <tr class="contract-row"
                        data-id="{{ $contract->id }}"
                        data-company="{{ $contract->company_name }}"
                        data-search="{{ $contract->contract_no }} {{ $contract->patient_name }} {{ $contract->company_name }} {{ $contract->work_order_no }}">
                        <td><strong style="color:var(--primary);">{{ $contract->contract_no }}</strong></td>
                        <td><strong>{{ $contract->patient_name }}</strong></td>
                        <td class="contract-company">{{ $contract->company_name }}</td>
                        <td class="contract-amount">
                            <strong>{{ number_format((float)$contract->approved_amount, 0) }} ج.م</strong>
                        </td>
                        <td>{{ $contract->approval_date?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="font-mono text-xs">{{ $contract->work_order_no ?? '—' }}</span></td>
                        <td>
                            @if ($contract->letter_path)
                                @php
                                    $letterUrl = route('admin.contracts.letter', $contract);
                                    $letterExt = strtolower(pathinfo($contract->letter_path, PATHINFO_EXTENSION));
                                @endphp
                                <div class="contract-actions">
                                    <button type="button"
                                            class="admin-table-btn admin-table-btn--view"
                                            onclick="openContractLetterView('{{ $letterUrl }}', '{{ addslashes($contract->contract_no) }}', '{{ $letterExt }}')">
                                        <span aria-hidden="true">👁️</span><span>عرض</span>
                                    </button>
                                    <a href="{{ route('admin.contracts.download', $contract) }}"
                                       class="admin-table-btn admin-table-btn--download"
                                       target="_blank" rel="noopener">
                                        <span aria-hidden="true">📎</span><span>تحميل</span>
                                    </a>
                                </div>
                            @else
                                <span class="contract-doc-missing">لا يوجد</span>
                            @endif
                        </td>
                        <td>
                            <div class="contract-actions">
                                <button type="button"
                                        class="admin-table-btn admin-table-btn--edit"
                                        onclick="openContractEditModal({{ $contract->id }}, {{ $contract->approved_amount }}, '{{ addslashes($contract->company_name) }}', '{{ $contract->letter_ref ?? '' }}')">
                                    <span aria-hidden="true">✏️</span><span>تعديل</span>
                                </button>
                                <button type="button"
                                        class="admin-table-btn admin-table-btn--delete"
                                        title="حذف العقد"
                                        aria-label="حذف العقد"
                                        onclick="deleteContract({{ $contract->id }}, '{{ addslashes($contract->contract_no) }}')">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">
                            لا توجد موافقات مسجلة حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('partials.contract-letter-modal')

{{-- Edit Contract Modal --}}
<div class="catalog-modal-overlay" id="contractEditModal" role="dialog" aria-modal="true">
    <div class="catalog-modal contract-edit-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <h3>✏️ تعديل بيانات العقد</h3>
            <button type="button" class="catalog-modal-close" onclick="closeContractEditModal()" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="editContractId">
            <div class="contract-edit-form">
                <label class="contract-edit-label">
                    <span>المبلغ المعتمد (ج.م)</span>
                    <input type="number" id="editContractAmount" step="0.01" min="0" class="contract-edit-input">
                </label>
                <label class="contract-edit-label">
                    <span>جهة التعاقد</span>
                    <input type="text" id="editContractCompany" class="contract-edit-input">
                </label>
                <label class="contract-edit-label">
                    <span>رقم خطاب الموافقة</span>
                    <input type="text" id="editContractLetterRef" class="contract-edit-input">
                </label>
            </div>
            <div id="contractEditError" class="contract-edit-error" style="display:none"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" onclick="closeContractEditModal()">إلغاء</button>
            <button type="button" class="btn-action primary" id="btnSaveContract" onclick="saveContractEdit()">💾 حفظ التعديلات</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var searchEl  = document.getElementById('contractSearch');
    var filterEl  = document.getElementById('contractCompanyFilter');
    var rows      = document.querySelectorAll('.contract-row');
    var countEl   = document.getElementById('contractFilterCount');

    function applyFilters() {
        var term    = (searchEl ? searchEl.value.trim().toUpperCase() : '');
        var company = (filterEl ? filterEl.value.trim() : '');
        var visible = 0;
        rows.forEach(function (row) {
            var matchSearch  = !term    || (row.dataset.search  || '').toUpperCase().indexOf(term) !== -1;
            var matchCompany = !company || (row.dataset.company || '') === company;
            var show = matchSearch && matchCompany;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (countEl) countEl.textContent = visible + ' موافقة';
        if (window.TablePagination) TablePagination.refreshById('contractsTable');
    }

    if (searchEl) searchEl.addEventListener('input', applyFilters);
    if (filterEl) filterEl.addEventListener('change', applyFilters);

    var editModal = document.getElementById('contractEditModal');
    if (editModal) {
        editModal.addEventListener('click', function (e) {
            if (e.target === editModal) closeContractEditModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && editModal && editModal.classList.contains('open')) closeContractEditModal();
    });

    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.openContractEditModal = function (id, amount, company, ref) {
        document.getElementById('editContractId').value    = id;
        document.getElementById('editContractAmount').value  = amount;
        document.getElementById('editContractCompany').value = company;
        document.getElementById('editContractLetterRef').value = ref || '';
        document.getElementById('contractEditError').style.display = 'none';
        document.getElementById('contractEditModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeContractEditModal = function () {
        document.getElementById('contractEditModal').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.saveContractEdit = function () {
        var id      = document.getElementById('editContractId').value;
        var amount  = document.getElementById('editContractAmount').value;
        var company = document.getElementById('editContractCompany').value.trim();
        var ref     = document.getElementById('editContractLetterRef').value.trim();
        var errEl   = document.getElementById('contractEditError');
        var btn     = document.getElementById('btnSaveContract');

        if (!amount || isNaN(parseFloat(amount)) || !company) {
            errEl.textContent = 'يرجى ملء المبلغ وجهة التعاقد.';
            errEl.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'جاري الحفظ...';

        fetch('/admin/contracts/' + id, {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ approved_amount: parseFloat(amount), company_name: company, letter_ref: ref })
        })
        .then(function (r) {
            return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function (res) {
            var row = document.querySelector('.contract-row[data-id="' + id + '"]');
            if (row && res.contract) {
                var amtCell = row.querySelector('.contract-amount');
                if (amtCell) amtCell.innerHTML = '<strong>' + Math.round(res.contract.approved_amount).toLocaleString() + ' ج.م</strong>';
                var coCell = row.querySelector('.contract-company');
                if (coCell) coCell.textContent = res.contract.company_name;
            }
            closeContractEditModal();
        })
        .catch(function (err) {
            errEl.textContent = (err && err.message) ? err.message : 'تعذّر حفظ التعديلات.';
            errEl.style.display = 'block';
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = '💾 حفظ التعديلات';
        });
    };

    window.deleteContract = function (id, contractNo) {
        if (!confirm('تأكيد حذف العقد ' + contractNo + '؟ لا يمكن التراجع عن هذا الإجراء.')) return;

        fetch('/admin/contracts/' + id, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(function (r) {
            return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function () {
            var row = document.querySelector('.contract-row[data-id="' + id + '"]');
            if (row) row.remove();
            var badge = document.getElementById('contractsCount');
            if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) - 1);
            applyFilters();
        })
        .catch(function (err) {
            alert((err && err.message) ? err.message : 'تعذّر حذف العقد.');
        });
    };
})();
</script>
@endpush
