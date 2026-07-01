@php
    $supplierList = $suppliers ?? collect();
    $filters = $supplier_filters ?? [];
    $openSupplierModal = old('form') === 'supplier';
@endphp
<div class="section-view" id="section-suppliers">
    <div class="panel">
        <div class="panel-header">
            <h3>🏭 الموردون</h3>
            <button type="button" class="btn-add-rank" id="btnAddSupplier">➕ إضافة مورد</button>
        </div>

        <form method="GET" action="{{ route('admin.suppliers') }}" class="supplier-filter-bar" id="supplierFilterForm">
            <input type="hidden" name="section" value="suppliers">
            <input type="text" name="search" id="supplierSearch" placeholder="🔍 بحث شامل..."
                   value="{{ $filters['search'] ?? '' }}">
            <select name="debt" id="supplierDebtFilter" aria-label="فلتر المديونية">
                <option value="">كل الموردين</option>
                <option value="with_debt" @selected(($filters['debt'] ?? '') === 'with_debt')>عليهم مديونية</option>
                <option value="no_debt" @selected(($filters['debt'] ?? '') === 'no_debt')>بدون مديونية</option>
            </select>
            <label class="supplier-date-field">
                <span>من</span>
                <input type="date" name="from" id="supplierFrom" value="{{ $filters['from'] ?? '' }}">
            </label>
            <label class="supplier-date-field">
                <span>إلى</span>
                <input type="date" name="to" id="supplierTo" value="{{ $filters['to'] ?? '' }}">
            </label>
            <button type="submit" class="btn-action primary">تطبيق</button>
            <a href="{{ route('admin.suppliers') }}" class="btn-action">مسح</a>
        </form>

        <div class="data-toolbar">
            @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'suppliersBulkBar'])
            <span class="toolbar-count" id="supplierCount">{{ $supplierList->count() }} مورد</span>
            <div class="export-btns">
                <button type="button" class="btn-export excel" id="btnExportSuppliersExcel">📊 Excel</button>
            </div>
        </div>

        <div class="panel-body">
            <table class="bulk-select-table" data-bulk-bar="suppliersBulkBar" data-bulk-delete-base="/admin/suppliers" data-paginate="10">
                <thead>
                    <tr>
                        @include('admin.partials.bulk-select-th')
                        <th>#</th>
                        <th>المورد / الشركة</th>
                        <th>التواصل</th>
                        <th>ضريبي / تجاري</th>
                        <th class="num">أصناف</th>
                        <th style="width:180px;white-space:nowrap">إجراء</th>
                    </tr>
                </thead>
                <tbody id="suppliersTable" data-server-rendered="1">
                    @forelse ($supplierList as $supplier)
                        <tr data-supplier-id="{{ $supplier->id }}"
                            data-can-delete="{{ ($supplier->can_delete ?? false) ? '1' : '0' }}">
                            @include('admin.partials.bulk-select-td', ['id' => $supplier->id])
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $supplier->name }}</strong>
                                @if ($supplier->address)
                                    <div class="supplier-sub">{{ Str::limit($supplier->address, 60) }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($supplier->phone)<div>📞 {{ $supplier->phone }}</div>@endif
                                @if ($supplier->fax)<div>📠 {{ $supplier->fax }}</div>@endif
                                @if ($supplier->email)<div>{{ $supplier->email }}</div>@endif
                                @if (! $supplier->phone && ! $supplier->fax && ! $supplier->email)—@endif
                            </td>
                            <td>
                                @if ($supplier->tax_number)<div>ض: {{ $supplier->tax_number }}</div>@endif
                                @if ($supplier->commercial_registry)<div>س: {{ $supplier->commercial_registry }}</div>@endif
                                @if (! $supplier->tax_number && ! $supplier->commercial_registry)—@endif
                            </td>
                            <td class="num">{{ (int) ($supplier->linked_items_count ?? 0) }}</td>
                            <td>
                                <div class="table-actions">
                                    <button type="button" class="btn-action" onclick="openSupplierEditModal({{ $supplier->id }})">✏️ تعديل</button>
                                    <button type="button" class="btn-action danger"
                                            onclick="deleteSupplier({{ $supplier->id }}, {{ json_encode($supplier->name) }}, {{ ($supplier->can_delete ?? false) ? 'true' : 'false' }})">
                                        🗑️ حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="suppliers-empty-row">
                            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:24px;">
                                لا يوجد موردون — أضف مورداً أو غيّر الفلاتر.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .supplier-filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-end;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
    }
    .supplier-filter-bar input[type="text"],
    .supplier-filter-bar select {
        min-width: 160px;
        padding: 9px 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
    }
    .supplier-date-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-muted);
    }
    .supplier-date-field input {
        padding: 9px 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
    }
    .supplier-sub {
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 4px;
    }
    th.num, td.num { text-align: center; white-space: nowrap; }
    .supplier-items-select {
        width: 100%;
        min-height: 120px;
        padding: 8px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
    }
</style>

@include('admin.partials.supplier-modals', [
    'openSupplierModal' => $openSupplierModal,
])

<script>
(function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function buildExportUrl() {
        var form = document.getElementById('supplierFilterForm');
        if (!form) return '{{ route('admin.suppliers.export') }}';
        var params = new URLSearchParams(new FormData(form));
        params.delete('section');
        var qs = params.toString();
        return '{{ route('admin.suppliers.export') }}' + (qs ? '?' + qs : '');
    }

    var exportBtn = document.getElementById('btnExportSuppliersExcel');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            window.location.href = buildExportUrl();
        });
    }

    window.openSupplierEditModal = function (id) {
        fetch('/admin/suppliers/' + id, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function (res) {
            var s = res.supplier;
            document.getElementById('editSupplierId').value = s.id;
            document.getElementById('editSupplierName').value = s.name || '';
            document.getElementById('editSupplierPhone').value = s.phone || '';
            document.getElementById('editSupplierFax').value = s.fax || '';
            document.getElementById('editSupplierEmail').value = s.email || '';
            document.getElementById('editSupplierAddress').value = s.address || '';
            document.getElementById('editSupplierTax').value = s.tax_number || '';
            document.getElementById('editSupplierCommercial').value = s.commercial_registry || '';
            document.getElementById('editSupplierBankName').value = s.bank_name || '';
            document.getElementById('editSupplierBankBranch').value = s.bank_branch || '';
            document.getElementById('editSupplierBankAccount').value = s.bank_account || '';
            document.getElementById('editSupplierIban').value = s.iban || '';
            document.getElementById('editSupplierNotes').value = s.notes || '';
            document.getElementById('supplierEditError').style.display = 'none';
            document.getElementById('supplierEditModal').classList.add('open');
        })
        .catch(function () {
            alert('تعذّر تحميل بيانات المورد.');
        });
    };

    window.closeSupplierEditModal = function () {
        document.getElementById('supplierEditModal').classList.remove('open');
    };

    window.saveSupplierEdit = function () {
        var id = document.getElementById('editSupplierId').value;
        var name = document.getElementById('editSupplierName').value.trim();
        var errEl = document.getElementById('supplierEditError');

        if (!name || name.length < 2) {
            errEl.textContent = 'يرجى إدخال اسم المورد (حرفان على الأقل).';
            errEl.style.display = 'block';
            return;
        }

        fetch('/admin/suppliers/' + id, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: name,
                phone: document.getElementById('editSupplierPhone').value.trim() || null,
                fax: document.getElementById('editSupplierFax').value.trim() || null,
                email: document.getElementById('editSupplierEmail').value.trim() || null,
                address: document.getElementById('editSupplierAddress').value.trim() || null,
                tax_number: document.getElementById('editSupplierTax').value.trim() || null,
                commercial_registry: document.getElementById('editSupplierCommercial').value.trim() || null,
                bank_name: document.getElementById('editSupplierBankName').value.trim() || null,
                bank_branch: document.getElementById('editSupplierBankBranch').value.trim() || null,
                bank_account: document.getElementById('editSupplierBankAccount').value.trim() || null,
                iban: document.getElementById('editSupplierIban').value.trim() || null,
                notes: document.getElementById('editSupplierNotes').value.trim() || null,
            }),
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function () {
            closeSupplierEditModal();
            window.location.reload();
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'تعذّر حفظ التعديل.';
            if (err && err.errors) {
                var first = Object.values(err.errors)[0];
                if (first && first[0]) msg = first[0];
            }
            errEl.textContent = msg;
            errEl.style.display = 'block';
        });
    };

    window.deleteSupplier = function (id, name, canDelete) {
        if (!canDelete) {
            alert('لا يمكن حذف هذا المورد — له حركات مالية أو مديونية مسجّلة.');
            return;
        }
        if (!confirm('حذف «' + name + '»؟')) return;
        fetch('/admin/suppliers/' + id, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function () { window.location.reload(); })
        .catch(function (err) {
            alert((err && err.message) ? err.message : 'تعذّر الحذف.');
        });
    };

    var editModal = document.getElementById('supplierEditModal');
    if (editModal) {
        editModal.addEventListener('click', function (e) {
            if (e.target === editModal) closeSupplierEditModal();
        });
    }
})();
</script>
