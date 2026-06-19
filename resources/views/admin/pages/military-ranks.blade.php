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
        <input type="text" id="rankSearch" placeholder="🔍 بحث باسم الرتبة أو الكود...">
        <span class="toolbar-count" id="rankCount">{{ $ranks->count() }} رتبة</span>
    </div>
    <div class="panel-body">
        <table data-paginate="10">
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
                @forelse ($ranks as $rank)
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
                            <form method="POST" action="{{ route('admin.military-ranks.toggle', $rank) }}" style="display:inline;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-action" title="تفعيل/تعطيل">تبديل</button>
                            </form>
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
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">كود الرتبة <span style="color:#dc2626">*</span></label>
                    <input type="text" name="rank_code" class="form-control" value="{{ old('rank_code') }}"
                           data-v-rules="required,rankCode" maxlength="30"
                           placeholder="مثال: CAPT" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;text-transform:uppercase;">
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
