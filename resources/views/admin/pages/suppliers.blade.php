@php
    $supplierList = $suppliers ?? collect();
    $openSupplierModal = old('form') === 'supplier';
@endphp
<div class="section-view" id="section-suppliers">
    <div class="panel">
        <div class="panel-header">
            <h3>🏭 الموردون</h3>
            <button type="button" class="btn-add-rank" id="btnAddSupplier">➕ إضافة مورد</button>
        </div>
        <div class="data-toolbar">
            @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'suppliersBulkBar'])
            <input type="text" id="supplierSearch" placeholder="🔍 بحث بالاسم أو الهاتف أو البريد...">
            <span class="toolbar-count" id="supplierCount">{{ $supplierList->count() }} مورد</span>
            <div class="export-btns">
                <button class="btn-export excel" onclick="exportSuppliers('excel')">📊 Excel</button>
                <button class="btn-export pdf" onclick="exportSuppliers('pdf')">📄 PDF</button>
            </div>
        </div>
        <div class="panel-body">
            <table class="bulk-select-table" data-bulk-bar="suppliersBulkBar" data-bulk-delete-base="/admin/suppliers" data-paginate="10">
                <thead>
                    <tr>
                        @include('admin.partials.bulk-select-th')
                        <th>#</th>
                        <th>اسم المورد</th>
                        <th>الهاتف</th>
                        <th>البريد</th>
                        <th style="width:180px;white-space:nowrap">إجراء</th>
                    </tr>
                </thead>
                <tbody id="suppliersTable" data-server-rendered="1">
                    @forelse ($supplierList as $supplier)
                        <tr data-supplier-id="{{ $supplier->id }}"
                            data-name="{{ e($supplier->name) }}"
                            data-phone="{{ e($supplier->phone ?? '') }}"
                            data-email="{{ e($supplier->email ?? '') }}"
                            data-address="{{ e($supplier->address ?? '') }}"
                            data-notes="{{ e($supplier->notes ?? '') }}">
                            @include('admin.partials.bulk-select-td', ['id' => $supplier->id])
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $supplier->name }}</strong></td>
                            <td>{{ $supplier->phone ?: '—' }}</td>
                            <td>{{ $supplier->email ?: '—' }}</td>
                            <td>
                                <div class="table-actions">
                                    <button type="button"
                                            class="btn-action"
                                            onclick="openSupplierEditModal({{ $supplier->id }})">
                                        ✏️ تعديل
                                    </button>
                                    <button type="button"
                                            class="btn-action danger"
                                            onclick="deleteSupplier({{ $supplier->id }}, {{ json_encode($supplier->name) }})">
                                        🗑️ حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="suppliers-empty-row">
                            <td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">
                                لا يوجد موردون — أضف مورداً من الزر أعلاه.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add Supplier Modal --}}
<div class="catalog-modal-overlay {{ $openSupplierModal ? 'open' : '' }}" id="supplierModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:480px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>➕ إضافة مورد</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeSupplierModal" aria-label="إغلاق">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.suppliers.store') }}" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="supplier">
            <div class="catalog-modal-body">
                @if ($errors->any() && old('form') === 'supplier')
                    <div class="v-error-msg" style="margin-bottom:12px;" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم المورد <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                           data-v-rules="required,min:2,max:255" maxlength="255"
                           placeholder="مثال: Ottobock Egypt"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الهاتف</label>
                    <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}"
                           data-v-rules="egyptian-mobile" maxlength="11" inputmode="numeric"
                           placeholder="01xxxxxxxxx"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                           maxlength="191" placeholder="supplier@example.com"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">العنوان</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}"
                           maxlength="500" placeholder="العنوان (اختياري)"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2" maxlength="1000"
                              placeholder="ملاحظات إضافية (اختياري)"
                              style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;resize:vertical;">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelSupplierModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Supplier Modal --}}
<div class="catalog-modal-overlay" id="supplierEditModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:480px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>✏️ تعديل المورد</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeSupplierEditModal" aria-label="إغلاق">&times;</button>
        </div>
        <input type="hidden" id="editSupplierId">
        <div class="catalog-modal-body">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم المورد</label>
                <input type="text" id="editSupplierName" maxlength="255" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الهاتف</label>
                <input type="tel" id="editSupplierPhone" maxlength="11" inputmode="numeric" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">البريد الإلكتروني</label>
                <input type="email" id="editSupplierEmail" maxlength="191" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">العنوان</label>
                <input type="text" id="editSupplierAddress" maxlength="500" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">ملاحظات</label>
                <textarea id="editSupplierNotes" rows="2" maxlength="1000" class="form-control"
                          style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;resize:vertical;"></textarea>
            </div>
            <div id="supplierEditError"
                 style="display:none;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:13px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelSupplierEditModal">إلغاء</button>
            <button type="button" class="btn-action success" onclick="saveSupplierEdit()">💾 حفظ</button>
        </div>
    </div>
</div>

<script>
(function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.openSupplierEditModal = function (id) {
        var row = document.querySelector('tr[data-supplier-id="' + id + '"]');
        if (!row) return;
        document.getElementById('editSupplierId').value = id;
        document.getElementById('editSupplierName').value = row.getAttribute('data-name') || '';
        document.getElementById('editSupplierPhone').value = row.getAttribute('data-phone') || '';
        document.getElementById('editSupplierEmail').value = row.getAttribute('data-email') || '';
        document.getElementById('editSupplierAddress').value = row.getAttribute('data-address') || '';
        document.getElementById('editSupplierNotes').value = row.getAttribute('data-notes') || '';
        document.getElementById('supplierEditError').style.display = 'none';
        document.getElementById('supplierEditModal').classList.add('open');
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
                email: document.getElementById('editSupplierEmail').value.trim() || null,
                address: document.getElementById('editSupplierAddress').value.trim() || null,
                notes: document.getElementById('editSupplierNotes').value.trim() || null,
            }),
        })
        .then(function (r) {
            return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
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

    window.deleteSupplier = function (id, name) {
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
        .then(function (r) {
            return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function () {
            window.location.reload();
        })
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

    var closeBtn = document.getElementById('closeSupplierEditModal');
    var cancelBtn = document.getElementById('cancelSupplierEditModal');
    if (closeBtn) closeBtn.addEventListener('click', closeSupplierEditModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeSupplierEditModal);
})();
</script>
