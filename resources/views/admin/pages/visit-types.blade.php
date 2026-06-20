@php
    $types = $visit_types ?? collect();
    $openVisitTypeModal = old('form') === 'visit_type';
@endphp
<div class="panel">
    <div class="panel-header">
        <h3>📋 أنواع الزيارات</h3>
        <button type="button" class="btn-add-rank" id="btnAddVisitType">➕ إضافة نوع</button>
    </div>
    <div class="data-toolbar">
        @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'visitTypesBulkBar'])
        <input type="text" id="visitTypeSearch" placeholder="🔍 بحث باسم نوع الزيارة...">
        <span class="toolbar-count" id="visitTypeCount">{{ $types->count() }} نوع</span>
    </div>
    <div class="panel-body">
        <table class="bulk-select-table" data-bulk-bar="visitTypesBulkBar" data-bulk-delete-base="/admin/visit-types" data-paginate="10">
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th>#</th>
                    <th>اسم نوع الزيارة</th>
                    <th style="width:180px;white-space:nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody id="visitTypesTable" data-server-rendered="1">
                @forelse ($types as $type)
                    <tr data-visit-type-id="{{ $type->id }}" data-name="{{ $type->name }}">
                        @include('admin.partials.bulk-select-td', ['id' => $type->id])
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $type->name }}</strong></td>
                        <td>
                            <div class="table-actions">
                                <button type="button"
                                        class="btn-action"
                                        onclick="openVisitTypeEditModal({{ $type->id }}, {{ json_encode($type->name) }})">
                                    ✏️ تعديل
                                </button>
                                <button type="button"
                                        class="btn-action danger"
                                        onclick="deleteVisitType({{ $type->id }}, {{ json_encode($type->name) }})">
                                    🗑️ حذف
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="visit-types-empty-row">
                        <td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">
                            لا توجد أنواع زيارات — أضف نوعاً من الزر أعلاه.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay {{ $openVisitTypeModal ? 'open' : '' }}" id="visitTypeModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>➕ إضافة نوع زيارة</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeVisitTypeModal" aria-label="إغلاق">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.visit-types.store') }}" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="visit_type">
            <div class="catalog-modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم نوع الزيارة <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                           data-v-rules="required,min:2,max:100" maxlength="100"
                           placeholder="مثال: كشف أولي" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelVisitTypeModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Visit Type Modal --}}
<div class="catalog-modal-overlay" id="visitTypeEditModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>✏️ تعديل نوع الزيارة</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeVisitTypeEditModal" aria-label="إغلاق">&times;</button>
        </div>
        <input type="hidden" id="editVisitTypeId">
        <div class="catalog-modal-body">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم نوع الزيارة</label>
                <input type="text" id="editVisitTypeName" maxlength="100"
                       class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div id="visitTypeEditError"
                 style="display:none;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:13px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelVisitTypeEditModal">إلغاء</button>
            <button type="button" class="btn-action success" onclick="saveVisitTypeEdit()">💾 حفظ</button>
        </div>
    </div>
</div>

<script>
(function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.openVisitTypeEditModal = function (id, name) {
        document.getElementById('editVisitTypeId').value = id;
        document.getElementById('editVisitTypeName').value = name || '';
        document.getElementById('visitTypeEditError').style.display = 'none';
        document.getElementById('visitTypeEditModal').classList.add('open');
    };

    window.closeVisitTypeEditModal = function () {
        document.getElementById('visitTypeEditModal').classList.remove('open');
    };

    window.saveVisitTypeEdit = function () {
        var id = document.getElementById('editVisitTypeId').value;
        var name = document.getElementById('editVisitTypeName').value.trim();
        var errEl = document.getElementById('visitTypeEditError');

        if (!name || name.length < 2) {
            errEl.textContent = 'يرجى إدخال اسم صالح (حرفان على الأقل).';
            errEl.style.display = 'block';
            return;
        }

        fetch('/admin/visit-types/' + id, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name: name }),
        })
        .then(function (r) {
            return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function () {
            closeVisitTypeEditModal();
            window.location.reload();
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'تعذّر حفظ التعديل.';
            if (err && err.errors && err.errors.name && err.errors.name[0]) {
                msg = err.errors.name[0];
            }
            errEl.textContent = msg;
            errEl.style.display = 'block';
        });
    };

    window.deleteVisitType = function (id, name) {
        if (!confirm('حذف «' + name + '»؟')) return;
        fetch('/admin/visit-types/' + id, {
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

    var editModal = document.getElementById('visitTypeEditModal');
    if (editModal) {
        editModal.addEventListener('click', function (e) {
            if (e.target === editModal) closeVisitTypeEditModal();
        });
    }

    var closeBtn = document.getElementById('closeVisitTypeEditModal');
    var cancelBtn = document.getElementById('cancelVisitTypeEditModal');
    if (closeBtn) closeBtn.addEventListener('click', closeVisitTypeEditModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeVisitTypeEditModal);
})();
</script>
