<div class="panel">
    <div class="panel-header">
        <h3>🪖 الرتب العسكرية</h3>
        <button type="button" class="btn-add-rank" id="btnAddRank">➕ إضافة رتبة</button>
    </div>
    <div class="data-toolbar">
        <input type="text" id="rankSearch" placeholder="🔍 بحث باسم الرتبة أو الكود...">
        <span class="toolbar-count" id="rankCount">{{ ($military_ranks ?? collect())->count() }} رتبة</span>
    </div>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم الرتبة</th>
                    <th>الكود</th>
                    <th>الترتيب</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="ranksTable" data-server-rendered="1">
                @forelse ($military_ranks ?? [] as $rank)
                    <tr data-rank-id="{{ $rank->id }}" data-name="{{ $rank->name }}" data-code="{{ $rank->rank_code }}">
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $rank->name }}</strong></td>
                        <td>{{ $rank->rank_code }}</td>
                        <td>{{ $rank->sort_order }}</td>
                        <td>
                            <span class="status-dot {{ $rank->is_active ? 'active' : 'inactive' }}">
                                {{ $rank->is_active ? 'فعّالة' : 'معطّلة' }}
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn-action" data-toggle-rank="{{ $rank->id }}" title="تفعيل/تعطيل">تبديل</button>
                        </td>
                    </tr>
                @empty
                    <tr class="ranks-empty-row">
                        <td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">
                            لا توجد رتب عسكرية — أضف رتبة من الزر أعلاه.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="rankModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="rankModalTitle">➕ إضافة رتبة عسكرية</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeRankModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="editRankId">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم الرتبة <span style="color:#dc2626">*</span></label>
                <input type="text" class="form-control" id="rankName" placeholder="مثال: نقيب" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">كود الرتبة <span style="color:#dc2626">*</span></label>
                <input type="text" class="form-control" id="rankCode" placeholder="مثال: CAPT" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;text-transform:uppercase;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">رقم الترتيب</label>
                <input type="number" class="form-control" id="rankSortOrder" placeholder="0" min="0" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div id="rankError" style="color:#dc2626;font-size:13px;margin-bottom:8px;display:none;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelRankModal">إلغاء</button>
            <button type="button" class="btn-action success" id="btnSaveRank">💾 حفظ</button>
        </div>
    </div>
</div>
