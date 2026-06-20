@php
    $companyList = $companies ?? collect();
@endphp
<div class="section-view" id="section-companies">
    <div class="panel">
        <div class="panel-header">
            <h3>🏢 شركات التعاقد</h3>
            <span class="badge" id="companiesBadge">{{ $companyList->count() }} شركة</span>
        </div>
        <form method="POST" action="{{ route('admin.companies.store') }}" class="company-add-bar" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="company">
            <input type="hidden" name="is_military" value="0">
            <input type="text" name="name" placeholder="اسم الشركة / جهة التعاقد..." autocomplete="off"
                   data-v-rules="required,min:2,max:255" maxlength="255"
                   value="{{ old('name') }}">
            <button type="submit" class="btn-add-company">➕ إضافة شركة</button>
            <p class="company-hint">أضف اسم جهة التعاقد — تُستخدم في الاستقبال والتقارير</p>
        </form>
        <div class="data-toolbar">
            @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'companiesBulkBar'])
            <input type="text" id="companySearch" placeholder="🔍 بحث باسم الشركة...">
            <span class="toolbar-count" id="companiesCount">{{ $companyList->count() }} شركة</span>
        </div>
        <div class="panel-body">
            <table class="bulk-select-table" data-bulk-bar="companiesBulkBar" data-bulk-delete-base="/admin/companies" data-paginate="10">
                <thead>
                    <tr>
                        @include('admin.partials.bulk-select-th')
                        <th style="width:48px">#</th>
                        <th>اسم الشركة</th>
                        <th style="width:180px;white-space:nowrap">إجراء</th>
                    </tr>
                </thead>
                <tbody id="companiesTable" data-server-rendered="1">
                    @forelse ($companyList as $company)
                        <tr data-id="{{ $company->id }}" data-name="{{ $company->name }}">
                            @include('admin.partials.bulk-select-td', ['id' => $company->id])
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $company->name }}</strong></td>
                            <td>
                                <div class="table-actions">
                                    <button type="button"
                                            class="btn-action"
                                            onclick="openCompanyEditModal({{ $company->id }}, {{ json_encode($company->name) }})">
                                        ✏️ تعديل
                                    </button>
                                    <button type="button"
                                            class="btn-action danger"
                                            onclick="deleteCompany({{ $company->id }}, {{ json_encode($company->name) }})">
                                        🗑️ حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">
                                لا توجد شركات — أضف جهة تعاقد من الحقل أعلاه.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Edit Company Modal --}}
<div class="catalog-modal-overlay" id="companyEditModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>✏️ تعديل جهة التعاقد</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeCompanyEditModal" aria-label="إغلاق">&times;</button>
        </div>
        <input type="hidden" id="editCompanyId">
        <div class="catalog-modal-body">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم الشركة / جهة التعاقد</label>
                <input type="text" id="editCompanyName" maxlength="255"
                       class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div id="companyEditError"
                 style="display:none;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:13px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelCompanyEditModal">إلغاء</button>
            <button type="button" class="btn-action success" onclick="saveCompanyEdit()">💾 حفظ</button>
        </div>
    </div>
</div>

<script>
(function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.openCompanyEditModal = function (id, name) {
        document.getElementById('editCompanyId').value = id;
        document.getElementById('editCompanyName').value = name;
        document.getElementById('companyEditError').style.display = 'none';
        document.getElementById('companyEditModal').classList.add('open');
    };

    window.closeCompanyEditModal = function () {
        document.getElementById('companyEditModal').classList.remove('open');
    };

    window.saveCompanyEdit = function () {
        var id = document.getElementById('editCompanyId').value;
        var name = document.getElementById('editCompanyName').value.trim();
        var errEl = document.getElementById('companyEditError');
        if (!name || name.length < 2) {
            errEl.textContent = 'يرجى إدخال اسم صالح (حرفان على الأقل).';
            errEl.style.display = 'block';
            return;
        }
        fetch('/admin/companies/' + id, {
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
            closeCompanyEditModal();
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

    window.deleteCompany = function (id, name) {
        if (!confirm('حذف «' + name + '»؟')) return;
        fetch('/admin/companies/' + id, {
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

    var modal = document.getElementById('companyEditModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeCompanyEditModal();
        });
    }

    var closeBtn = document.getElementById('closeCompanyEditModal');
    var cancelBtn = document.getElementById('cancelCompanyEditModal');
    if (closeBtn) closeBtn.addEventListener('click', closeCompanyEditModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeCompanyEditModal);
})();
</script>
