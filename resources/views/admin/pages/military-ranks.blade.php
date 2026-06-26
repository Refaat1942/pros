@php
    $ranks = $military_ranks ?? collect();
    $openRankModal = old('form') === 'rank';
@endphp
<div class="panel">
    <div class="panel-header">
        <h3>🪖 الرتب العسكرية</h3>
        <button type="button" class="btn-add-rank" id="btnAddRank">➕ إضافة رتبة</button>
    </div>
    <div class="data-toolbar">
        @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'militaryRanksBulkBar'])
        <input type="text" id="rankSearch" placeholder="🔍 بحث باسم الرتبة...">
        <span class="toolbar-count" id="rankCount">{{ $ranks->count() }} رتبة</span>
    </div>
    <div class="panel-body">
        <table class="bulk-select-table" data-bulk-bar="militaryRanksBulkBar" data-bulk-delete-base="/admin/military-ranks">
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th class="rank-drag-col" aria-label="سحب للترتيب"></th>
                    <th>#</th>
                    <th>اسم الرتبة</th>
                    <th style="width:180px;white-space:nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody id="ranksTable" data-server-rendered="1">
                @forelse ($ranks as $rank)
                    <tr class="rank-sortable-row"
                        data-rank-id="{{ $rank->id }}"
                        data-name="{{ $rank->name }}"
                        draggable="true">
                        @include('admin.partials.bulk-select-td', ['id' => $rank->id])
                        <td class="rank-drag-handle" title="اسحب للأعلى أو الأسفل" aria-label="سحب للترتيب">⋮⋮</td>
                        <td class="rank-row-num">{{ $loop->iteration }}</td>
                        <td><strong>{{ $rank->name }}</strong></td>
                        <td>
                            <div class="table-actions">
                                <button type="button"
                                        class="btn-action"
                                        onclick="openRankEditModal({{ $rank->id }}, {{ json_encode($rank->name) }})">
                                    ✏️ تعديل
                                </button>
                                <button type="button"
                                        class="btn-action danger"
                                        onclick="deleteRank({{ $rank->id }}, {{ json_encode($rank->name) }})">
                                    🗑️ حذف
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="ranks-empty-row">
                        <td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">
                            لا توجد رتب عسكرية — أضف رتبة من الزر أعلاه.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="rank-reorder-hint">💡 اسحب الصف من ⋮⋮ لأعلى أو لأسفل لتغيير الترتيب — يُحفظ تلقائياً.</p>
    </div>
</div>

{{-- Add Rank Modal --}}
<div class="catalog-modal-overlay {{ $openRankModal ? 'open' : '' }}" id="rankModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>➕ إضافة رتبة عسكرية</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeRankModal" aria-label="إغلاق">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.military-ranks.store') }}" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="rank">
            <div class="catalog-modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم الرتبة <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                           data-v-rules="required,min:2,max:100" maxlength="100"
                           placeholder="مثال: نقيب" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelRankModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Rank Modal --}}
<div class="catalog-modal-overlay" id="rankEditModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3>✏️ تعديل الرتبة العسكرية</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeRankEditModal" aria-label="إغلاق">&times;</button>
        </div>
        <input type="hidden" id="editRankId">
        <div class="catalog-modal-body">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم الرتبة</label>
                <input type="text" id="editRankName" maxlength="100"
                       class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div id="rankEditError"
                 style="display:none;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:13px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelRankEditModal">إلغاء</button>
            <button type="button" class="btn-action success" onclick="saveRankEdit()">💾 حفظ</button>
        </div>
    </div>
</div>

<style>
    .rank-drag-col { width: 36px; }
    .rank-drag-handle {
        cursor: grab;
        text-align: center;
        color: var(--text-muted, #94a3b8);
        user-select: none;
        letter-spacing: -2px;
        font-weight: 700;
        padding: 8px 4px !important;
    }
    .rank-sortable-row.is-dragging {
        opacity: 0.55;
        background: rgba(124, 58, 237, 0.08);
    }
    .rank-sortable-row.is-drag-over {
        box-shadow: inset 0 2px 0 var(--primary, #7c3aed);
    }
    .rank-reorder-hint {
        margin: 10px 16px 0;
        font-size: 12px;
        color: var(--text-muted, #64748b);
    }
    .rank-reorder-hint.is-disabled {
        opacity: 0.65;
    }
</style>

<script>
(function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function openEditModal() {
        document.getElementById('rankEditModal').classList.add('open');
    }

    window.closeRankEditModal = function () {
        document.getElementById('rankEditModal').classList.remove('open');
    };

    window.openRankEditModal = function (id, name) {
        document.getElementById('editRankId').value = id;
        document.getElementById('editRankName').value = name || '';
        document.getElementById('rankEditError').style.display = 'none';
        openEditModal();
    };

    window.saveRankEdit = function () {
        var id = document.getElementById('editRankId').value;
        var name = document.getElementById('editRankName').value.trim();
        var errEl = document.getElementById('rankEditError');

        if (!name || name.length < 2) {
            errEl.textContent = 'يرجى إدخال اسم الرتبة (حرفان على الأقل).';
            errEl.style.display = 'block';
            return;
        }

        fetch('/admin/military-ranks/' + id, {
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
            closeRankEditModal();
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

    window.deleteRank = function (id, name) {
        if (!confirm('حذف الرتبة «' + name + '»؟')) return;
        fetch('/admin/military-ranks/' + id, {
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
            alert((err && err.message) ? err.message : 'تعذّر حذف الرتبة.');
        });
    };

    function refreshRankRowNumbers() {
        var tbody = document.getElementById('ranksTable');
        if (!tbody) return;
        var index = 0;
        tbody.querySelectorAll('tr.rank-sortable-row').forEach(function (row) {
            if (row.style.display === 'none') return;
            index += 1;
            var cell = row.querySelector('.rank-row-num');
            if (cell) cell.textContent = String(index);
        });
    }

    function canReorderRanks() {
        var search = document.getElementById('rankSearch');
        return !search || search.value.trim() === '';
    }

    function collectRankIds() {
        var tbody = document.getElementById('ranksTable');
        if (!tbody) return [];
        return Array.from(tbody.querySelectorAll('tr.rank-sortable-row')).map(function (row) {
            return parseInt(row.dataset.rankId, 10);
        });
    }

    function saveRankOrder() {
        if (!canReorderRanks()) return;

        var ids = collectRankIds();
        if (!ids.length) return;

        fetch('{{ route('admin.military-ranks.reorder') }}', {
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
            refreshRankRowNumbers();
        })
        .catch(function () {
            window.location.reload();
        });
    }

    function initRankDragReorder() {
        var tbody = document.getElementById('ranksTable');
        var hint = document.querySelector('.rank-reorder-hint');
        if (!tbody) return;

        var dragRow = null;

        function clearDragOver() {
            tbody.querySelectorAll('.is-drag-over').forEach(function (row) {
                row.classList.remove('is-drag-over');
            });
        }

        tbody.addEventListener('dragstart', function (e) {
            if (!canReorderRanks()) {
                e.preventDefault();
                return;
            }

            var row = e.target.closest('tr.rank-sortable-row');
            if (!row || !e.target.closest('.rank-drag-handle')) {
                e.preventDefault();
                return;
            }

            dragRow = row;
            row.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.dataset.rankId || '');
        });

        tbody.addEventListener('dragover', function (e) {
            if (!dragRow) return;
            e.preventDefault();

            var target = e.target.closest('tr.rank-sortable-row');
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
            if (dragRow) dragRow.classList.remove('is-dragging');
            dragRow = null;
            clearDragOver();
            saveRankOrder();
        });

        tbody.addEventListener('drop', function (e) {
            e.preventDefault();
        });

        var search = document.getElementById('rankSearch');
        if (search) {
            search.addEventListener('input', function () {
                if (!hint) return;
                if (canReorderRanks()) {
                    hint.textContent = '💡 اسحب الصف من ⋮⋮ لأعلى أو لأسفل لتغيير الترتيب — يُحفظ تلقائياً.';
                    hint.classList.remove('is-disabled');
                } else {
                    hint.textContent = '⚠️ أوقف البحث أولاً لتغيير ترتيب الرتب بالسحب.';
                    hint.classList.add('is-disabled');
                }
            });
        }
    }

    initRankDragReorder();

    var editModal = document.getElementById('rankEditModal');
    if (editModal) {
        editModal.addEventListener('click', function (e) {
            if (e.target === editModal) closeRankEditModal();
        });
    }

    var closeBtn = document.getElementById('closeRankEditModal');
    var cancelBtn = document.getElementById('cancelRankEditModal');
    if (closeBtn) closeBtn.addEventListener('click', closeRankEditModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeRankEditModal);
})();
</script>
