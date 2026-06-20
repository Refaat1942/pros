@php
    $categories = $stock_categories ?? collect();
    $openCategoryModal = old('form') === 'stock_category';
@endphp
<div class="panel">
    <div class="panel-header">
        <h3>🏷️ فئات الأصناف</h3>
        <button type="button" class="btn-add-rank" id="btnAddStockCategory">➕ إضافة فئة</button>
    </div>
    <div class="data-toolbar">
        @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'stockCategoriesBulkBar'])
        <input type="text" id="stockCategorySearch" placeholder="🔍 بحث باسم الفئة...">
        <span class="toolbar-count" id="stockCategoryCount">{{ $categories->count() }} فئة</span>
    </div>
    <div class="panel-body">
        <table class="bulk-select-table" data-bulk-bar="stockCategoriesBulkBar" data-bulk-delete-base="/admin/stock-categories" data-paginate="10">
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th>#</th>
                    <th>اسم الفئة</th>
                    <th style="width:140px">إجراء</th>
                </tr>
            </thead>
            <tbody id="stockCategoriesTable" data-server-rendered="1">
                @forelse ($categories as $category)
                    <tr data-category-id="{{ $category->id }}" data-name="{{ $category->name }}">
                        @include('admin.partials.bulk-select-td', ['id' => $category->id])
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $category->name }}</strong></td>
                        <td>
                            <div class="table-actions">
                                <button type="button"
                                        class="btn-action"
                                        onclick="openStockCategoryEditModal({{ $category->id }}, {{ json_encode($category->name) }})">
                                    ✏️ تعديل
                                </button>
                                <button type="button"
                                        class="btn-action danger"
                                        onclick="deleteStockCategory({{ $category->id }}, {{ json_encode($category->name) }})">
                                    🗑️ حذف
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="stock-categories-empty-row">
                        <td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">
                            لا توجد فئات — أضف فئة من الزر أعلاه.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay {{ $openCategoryModal ? 'open' : '' }}" id="stockCategoryModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>➕ إضافة فئة صنف</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeStockCategoryModal" aria-label="إغلاق">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.stock-categories.store') }}" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="stock_category">
            <div class="catalog-modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم الفئة <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                           data-v-rules="required,min:2,max:100" maxlength="100"
                           placeholder="مثال: مفاصل" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelStockCategoryModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Category Modal --}}
<div class="catalog-modal-overlay" id="stockCategoryEditModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>✏️ تعديل فئة الصنف</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeStockCategoryEditModal" aria-label="إغلاق">&times;</button>
        </div>
        <input type="hidden" id="editStockCategoryId">
        <div class="catalog-modal-body">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم الفئة</label>
                <input type="text" id="editStockCategoryName" maxlength="100"
                       class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div id="stockCategoryEditError"
                 style="display:none;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:13px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelStockCategoryEditModal">إلغاء</button>
            <button type="button" class="btn-action success" onclick="saveStockCategoryEdit()">💾 حفظ</button>
        </div>
    </div>
</div>

<script>
(function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.openStockCategoryEditModal = function (id, name) {
        document.getElementById('editStockCategoryId').value = id;
        document.getElementById('editStockCategoryName').value = name || '';
        document.getElementById('stockCategoryEditError').style.display = 'none';
        document.getElementById('stockCategoryEditModal').classList.add('open');
    };

    window.closeStockCategoryEditModal = function () {
        document.getElementById('stockCategoryEditModal').classList.remove('open');
    };

    window.saveStockCategoryEdit = function () {
        var id = document.getElementById('editStockCategoryId').value;
        var name = document.getElementById('editStockCategoryName').value.trim();
        var errEl = document.getElementById('stockCategoryEditError');

        if (!name || name.length < 2) {
            errEl.textContent = 'يرجى إدخال اسم صالح (حرفان على الأقل).';
            errEl.style.display = 'block';
            return;
        }

        fetch('/admin/stock-categories/' + id, {
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
            closeStockCategoryEditModal();
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

    window.deleteStockCategory = function (id, name) {
        if (!confirm('حذف «' + name + '»؟')) return;
        fetch('/admin/stock-categories/' + id, {
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

    var editModal = document.getElementById('stockCategoryEditModal');
    if (editModal) {
        editModal.addEventListener('click', function (e) {
            if (e.target === editModal) closeStockCategoryEditModal();
        });
    }

    var closeBtn = document.getElementById('closeStockCategoryEditModal');
    var cancelBtn = document.getElementById('cancelStockCategoryEditModal');
    if (closeBtn) closeBtn.addEventListener('click', closeStockCategoryEditModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeStockCategoryEditModal);
})();
</script>
