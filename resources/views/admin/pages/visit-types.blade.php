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
        <table class="bulk-select-table" data-bulk-bar="visitTypesBulkBar" data-bulk-delete-base="/admin/visit-types">
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th class="visit-type-drag-col" aria-label="سحب للترتيب"></th>
                    <th>#</th>
                    <th>اسم نوع الزيارة</th>
                    <th style="width:180px;white-space:nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody id="visitTypesTable" data-server-rendered="1">
                @forelse ($types as $type)
                    <tr class="visit-type-sortable-row"
                        data-visit-type-id="{{ $type->id }}"
                        data-name="{{ $type->name }}"
                        draggable="true">
                        @include('admin.partials.bulk-select-td', ['id' => $type->id])
                        <td class="visit-type-drag-handle" title="اسحب للأعلى أو الأسفل" aria-label="سحب للترتيب">⋮⋮</td>
                        <td class="visit-type-row-num">{{ $loop->iteration }}</td>
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
                        <td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">
                            لا توجد أنواع زيارات — أضف نوعاً من الزر أعلاه.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="visit-type-reorder-hint">💡 اسحب الصف من ⋮⋮ لأعلى أو لأسفل لتغيير الترتيب — يُحفظ تلقائياً ويظهر في الاستقبال.</p>
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

<style>
    .visit-type-drag-col { width: 36px; }
    .visit-type-drag-handle {
        cursor: grab;
        text-align: center;
        color: var(--text-muted, #94a3b8);
        user-select: none;
        letter-spacing: -2px;
        font-weight: 700;
        padding: 8px 4px !important;
    }
    .visit-type-sortable-row.is-dragging {
        opacity: 0.55;
        background: rgba(124, 58, 237, 0.08);
    }
    .visit-type-sortable-row.is-drag-over {
        box-shadow: inset 0 2px 0 var(--primary, #7c3aed);
    }
    .visit-type-reorder-hint {
        margin: 10px 16px 0;
        font-size: 12px;
        color: var(--text-muted, #64748b);
    }
    .visit-type-reorder-hint.is-disabled {
        opacity: 0.65;
    }
</style>

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

    function refreshVisitTypeRowNumbers() {
        var tbody = document.getElementById('visitTypesTable');
        if (!tbody) return;
        var index = 0;
        tbody.querySelectorAll('tr.visit-type-sortable-row').forEach(function (row) {
            if (row.style.display === 'none') return;
            index += 1;
            var cell = row.querySelector('.visit-type-row-num');
            if (cell) cell.textContent = String(index);
        });
    }

    function canReorderVisitTypes() {
        var search = document.getElementById('visitTypeSearch');
        return !search || search.value.trim() === '';
    }

    function collectVisitTypeIds() {
        var tbody = document.getElementById('visitTypesTable');
        if (!tbody) return [];
        return Array.from(tbody.querySelectorAll('tr.visit-type-sortable-row')).map(function (row) {
            return parseInt(row.dataset.visitTypeId, 10);
        });
    }

    function saveVisitTypeOrder() {
        if (!canReorderVisitTypes()) return;

        var ids = collectVisitTypeIds();
        if (!ids.length) return;

        fetch('{{ route('admin.visit-types.reorder') }}', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ids: ids }),
        })
        .then(function (r) {
            return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function () {
            refreshVisitTypeRowNumbers();
        })
        .catch(function () {
            window.location.reload();
        });
    }

    function asElement(node) {
        if (!node) return null;
        return node.nodeType === 1 ? node : node.parentElement;
    }

    function initVisitTypeDragReorder() {
        var tbody = document.getElementById('visitTypesTable');
        var hint = document.querySelector('.visit-type-reorder-hint');
        if (!tbody) return;

        var dragRow = null;
        var dragFromHandle = false;

        tbody.querySelectorAll('.visit-type-drag-handle').forEach(function (handle) {
            handle.addEventListener('mousedown', function () {
                dragFromHandle = true;
            });
            handle.addEventListener('touchstart', function () {
                dragFromHandle = true;
            }, { passive: true });
        });

        function clearDragOver() {
            tbody.querySelectorAll('.is-drag-over').forEach(function (row) {
                row.classList.remove('is-drag-over');
            });
        }

        tbody.addEventListener('dragstart', function (e) {
            if (!canReorderVisitTypes()) {
                e.preventDefault();
                return;
            }

            var row = asElement(e.target);
            row = row ? row.closest('tr.visit-type-sortable-row') : null;

            if (!row || !dragFromHandle) {
                e.preventDefault();
                dragFromHandle = false;
                return;
            }

            dragFromHandle = false;
            dragRow = row;
            row.classList.add('is-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', row.dataset.visitTypeId || '');
            }
        });

        tbody.addEventListener('dragover', function (e) {
            if (!dragRow) return;
            e.preventDefault();

            var el = asElement(e.target);
            var target = el ? el.closest('tr.visit-type-sortable-row') : null;
            clearDragOver();
            if (!target || target === dragRow) return;

            target.classList.add('is-drag-over');
            var rect = target.getBoundingClientRect();
            var after = e.clientY > rect.top + rect.height / 2;

            if (after) {
                target.parentNode.insertBefore(dragRow, target.nextSibling);
            } else {
                target.parentNode.insertBefore(dragRow, target);
            }
        });

        tbody.addEventListener('dragend', function () {
            dragFromHandle = false;
            if (dragRow) dragRow.classList.remove('is-dragging');
            dragRow = null;
            clearDragOver();
            saveVisitTypeOrder();
        });

        tbody.addEventListener('drop', function (e) {
            e.preventDefault();
        });

        var search = document.getElementById('visitTypeSearch');
        if (search) {
            search.addEventListener('input', function () {
                if (!hint) return;
                if (canReorderVisitTypes()) {
                    hint.textContent = '💡 اسحب الصف من ⋮⋮ لأعلى أو لأسفل لتغيير الترتيب — يُحفظ تلقائياً ويظهر في الاستقبال.';
                    hint.classList.remove('is-disabled');
                } else {
                    hint.textContent = '⚠️ أوقف البحث أولاً لتغيير ترتيب أنواع الزيارات بالسحب.';
                    hint.classList.add('is-disabled');
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVisitTypeDragReorder);
    } else {
        initVisitTypeDragReorder();
    }
})();
</script>
