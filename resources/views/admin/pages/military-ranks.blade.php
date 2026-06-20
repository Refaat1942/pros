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
        <table class="bulk-select-table" data-bulk-bar="militaryRanksBulkBar" data-bulk-delete-base="/admin/military-ranks" data-paginate="10">
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th>#</th>
                    <th>اسم الرتبة</th>
                    <th>الترتيب</th>
                    <th style="width:180px;white-space:nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody id="ranksTable" data-server-rendered="1">
                @forelse ($ranks as $rank)
                    <tr data-rank-id="{{ $rank->id }}"
                        data-name="{{ $rank->name }}"
                        data-sort-order="{{ $rank->sort_order }}">
                        @include('admin.partials.bulk-select-td', ['id' => $rank->id])
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $rank->name }}</strong></td>
                        <td>{{ $rank->sort_order }}</td>
                        <td>
                            <div class="table-actions">
                                <button type="button"
                                        class="btn-action"
                                        onclick="openRankEditModal({{ $rank->id }}, {{ json_encode($rank->name) }}, {{ (int) $rank->sort_order }})">
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
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">رقم الترتيب</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}"
                           data-v-rules="integer,minValue:0,maxValue:9999" min="0" max="9999" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
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
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">رقم الترتيب</label>
                <input type="number" id="editRankSortOrder" min="0" max="9999"
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

    window.openRankEditModal = function (id, name, sortOrder) {
        document.getElementById('editRankId').value = id;
        document.getElementById('editRankName').value = name || '';
        document.getElementById('editRankSortOrder').value = sortOrder != null ? sortOrder : 0;
        document.getElementById('rankEditError').style.display = 'none';
        openEditModal();
    };

    window.saveRankEdit = function () {
        var id = document.getElementById('editRankId').value;
        var name = document.getElementById('editRankName').value.trim();
        var sortOrder = parseInt(document.getElementById('editRankSortOrder').value, 10);
        var errEl = document.getElementById('rankEditError');

        if (!name || name.length < 2) {
            errEl.textContent = 'يرجى إدخال اسم الرتبة (حرفان على الأقل).';
            errEl.style.display = 'block';
            return;
        }
        if (isNaN(sortOrder) || sortOrder < 0) sortOrder = 0;

        fetch('/admin/military-ranks/' + id, {
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
                sort_order: sortOrder,
            }),
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
