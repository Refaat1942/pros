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
        <h3>📑 العقود والاتفاقيات — تحكم كامل</h3>
        <span class="badge" id="contractsCount">{{ $contracts->count() }}</span>
    </div>
    <div class="data-toolbar">
        <input type="text" id="contractSearch" placeholder="🔍 بحث بالمريض أو الجهة أو رقم العقد..." autocomplete="off">
        <select id="contractCompanyFilter" style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;font-family:inherit;font-size:13px;">
            <option value="">كل الجهات</option>
            @foreach ($contracts->pluck('company_name')->unique()->sort()->values() as $company)
                <option value="{{ $company }}">{{ $company }}</option>
            @endforeach
        </select>
        <span class="toolbar-count" id="contractFilterCount">{{ $contracts->count() }} عقد</span>
    </div>
    <div class="panel-body">
        <table data-paginate="15">
            <thead>
                <tr>
                    <th>رقم العقد</th>
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
                                    $letterUrl = asset('storage/' . $contract->letter_path);
                                    $letterExt = strtolower(pathinfo($contract->letter_path, PATHINFO_EXTENSION));
                                @endphp
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <button type="button"
                                            class="btn btn-secondary"
                                            style="padding:4px 12px;font-size:11px;"
                                            onclick="openContractLetterView('{{ $letterUrl }}', '{{ addslashes($contract->contract_no) }}', '{{ $letterExt }}')">
                                        👁️ عرض
                                    </button>
                                    <a href="{{ route('admin.contracts.download', $contract) }}"
                                       class="btn btn-secondary"
                                       style="padding:4px 12px;font-size:11px;"
                                       target="_blank">📎 تحميل</a>
                                </div>
                            @else
                                <span style="color:var(--text-muted);font-size:12px;">لا يوجد</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <button type="button"
                                        class="btn btn-secondary"
                                        style="padding:4px 10px;font-size:11px;"
                                        onclick="openContractEditModal({{ $contract->id }}, {{ $contract->approved_amount }}, '{{ addslashes($contract->company_name) }}', '{{ $contract->letter_ref ?? '' }}')">
                                    ✏️ تعديل
                                </button>
                                <button type="button"
                                        class="btn"
                                        style="padding:4px 10px;font-size:11px;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;"
                                        onclick="deleteContract({{ $contract->id }}, '{{ addslashes($contract->contract_no) }}')">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">
                            لا توجد عقود مسجلة حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('partials.contract-letter-modal')

{{-- Edit Contract Modal --}}
<div class="modal-overlay" id="contractEditModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
    <div class="modal" style="max-width:460px;width:100%;background:#fff;border-radius:16px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h3 style="font-size:16px;font-weight:700;">✏️ تعديل بيانات العقد</h3>
            <button type="button" onclick="closeContractEditModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">×</button>
        </div>
        <input type="hidden" id="editContractId">
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">المبلغ المعتمد (ج.م)</label>
                <input type="number" id="editContractAmount" step="0.01" min="0"
                       class="form-control" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">جهة التعاقد</label>
                <input type="text" id="editContractCompany"
                       class="form-control" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">رقم خطاب الموافقة</label>
                <input type="text" id="editContractLetterRef"
                       class="form-control" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:14px;">
            </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
            <button type="button" onclick="closeContractEditModal()"
                    class="btn btn-secondary" style="padding:10px 20px;">إلغاء</button>
            <button type="button" id="btnSaveContract" onclick="saveContractEdit()"
                    class="btn btn-primary" style="padding:10px 20px;">💾 حفظ التعديلات</button>
        </div>
        <div id="contractEditError" style="display:none;margin-top:12px;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:13px;"></div>
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
        if (countEl) countEl.textContent = visible + ' عقد';
        if (window.TablePagination) TablePagination.refreshById('contractsTable');
    }

    if (searchEl) searchEl.addEventListener('input', applyFilters);
    if (filterEl) filterEl.addEventListener('change', applyFilters);

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
        var modal = document.getElementById('contractEditModal');
        modal.style.display = 'flex';
    };

    window.closeContractEditModal = function () {
        document.getElementById('contractEditModal').style.display = 'none';
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
