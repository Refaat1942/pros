@php
    $companyList = $companies ?? collect();

    function formatCompanyDiscount($value): string
    {
        $pct = (float) ($value ?? 0);

        return $pct > 0 ? rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') . '%' : '—';
    }
@endphp
<div class="section-view" id="section-companies">
    <div class="panel">
        <div class="panel-header">
                <h3>🏢 جهات التعاقد</h3>
            <span class="badge" id="companiesBadge">{{ $companyList->count() }} جهة</span>
        </div>
        <form method="POST" action="{{ route('admin.companies.store') }}" class="company-add-bar" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="company">
            <input type="hidden" name="is_military" value="0">
            <input type="hidden" name="is_contracted" value="1">
            <input type="text" name="name" placeholder="اسم الهيئة..." autocomplete="off"
                   data-v-rules="required,min:2,max:255" maxlength="255"
                   value="{{ old('name') }}">
            <label class="company-discount-field" title="نسبة ما تتحمّله الجهة من إجمالي الفاتورة — الباقي على المريض">
                <span>نسبة تحمّل الجهة %</span>
                <input type="number" name="discount_percent" min="0" max="100" step="0.01" value="{{ old('discount_percent', '0') }}" placeholder="0">
            </label>
            <button type="submit" class="btn-add-company">➕ إضافة جهة</button>
            <p class="company-hint">أضف هيئة — متعاقدة (مديونية) أو غير متعاقدة (مرجع فقط). نسبة تحمّل الجهة = حصة المديونية على الشركة؛ المريض يدفع الباقي كاش والسعر الكامل يبقى على العرض.</p>
        </form>
        <div class="data-toolbar">
            @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'companiesBulkBar'])
            <input type="text" id="companySearch" placeholder="🔍 بحث باسم الجهة...">
            <span class="toolbar-count" id="companiesCount">{{ $companyList->count() }} شركة</span>
        </div>
        <div class="panel-body">
            <table class="bulk-select-table" data-bulk-bar="companiesBulkBar" data-bulk-delete-base="/admin/companies" data-paginate="10">
                <thead>
                    <tr>
                        @include('admin.partials.bulk-select-th')
                        <th style="width:48px">#</th>
                        <th>اسم الجهة</th>
                        <th>نوع الهيئة</th>
                        <th style="width:110px">تحمّل الجهة</th>
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
                                @if ($company->is_contracted)
                                    <span class="entity-badge entity-badge--contracted">📑 متعاقدة</span>
                                @else
                                    <span class="entity-badge entity-badge--non-contracted">🏷️ غير متعاقدة</span>
                                @endif
                            </td>
                            <td>{{ formatCompanyDiscount($company->discount_percent) }}</td>
                            <td>
                                <div class="table-actions">
                                    <button type="button"
                                            class="btn-action"
                                            onclick="openCompanyEditModal({{ $company->id }}, {{ json_encode($company->name) }}, {{ json_encode((float) ($company->discount_percent ?? 0)) }})">
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
                            <td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">
                                لا توجد جهات — أضف جهة تعاقد من الحقل أعلاه.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .company-add-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 10px;
    }
    .company-discount-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-muted);
    }
    .company-discount-field input {
        width: 110px;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
    }
</style>

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
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم الجهة / جهة التعاقد</label>
                <input type="text" id="editCompanyName" maxlength="255"
                       class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">نسبة تحمّل الجهة %</label>
                <input type="number" id="editCompanyDiscount" min="0" max="100" step="0.01" value="0"
                       class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">مثال 20% → الجهة 200 ج.م والمريض 800 من 1000 · 0 = المريض يدفع الكامل</p>
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

    window.openCompanyEditModal = function (id, name, discountPercent) {
        document.getElementById('editCompanyId').value = id;
        document.getElementById('editCompanyName').value = name;
        document.getElementById('editCompanyDiscount').value = discountPercent != null ? discountPercent : 0;
        document.getElementById('companyEditError').style.display = 'none';
        document.getElementById('companyEditModal').classList.add('open');
    };

    window.closeCompanyEditModal = function () {
        document.getElementById('companyEditModal').classList.remove('open');
    };

    window.saveCompanyEdit = function () {
        var id = document.getElementById('editCompanyId').value;
        var name = document.getElementById('editCompanyName').value.trim();
        var discount = parseFloat(document.getElementById('editCompanyDiscount').value || '0');
        var errEl = document.getElementById('companyEditError');
        if (!name || name.length < 2) {
            errEl.textContent = 'يرجى إدخال اسم صالح (حرفان على الأقل).';
            errEl.style.display = 'block';
            return;
        }
        if (isNaN(discount) || discount < 0 || discount > 100) {
            errEl.textContent = 'نسبة تحمّل الجهة يجب أن تكون بين 0 و 100.';
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
            body: JSON.stringify({ name: name, discount_percent: discount }),
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
            if (err && err.errors && err.errors.discount_percent && err.errors.discount_percent[0]) {
                msg = err.errors.discount_percent[0];
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
