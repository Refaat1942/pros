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
        <input type="text" id="visitTypeSearch" placeholder="🔍 بحث باسم نوع الزيارة...">
        <span class="toolbar-count" id="visitTypeCount">{{ $types->count() }} نوع</span>
    </div>
    <div class="panel-body">
        <table data-paginate="10">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المعرف</th>
                    <th>اسم نوع الزيارة</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="visitTypesTable" data-server-rendered="1">
                @forelse ($types as $type)
                    <tr data-visit-type-id="{{ $type->id }}" data-name="{{ $type->name }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $type->id }}</td>
                        <td><strong>{{ $type->name }}</strong></td>
                        <td>
                            <span class="status-dot {{ $type->is_active ? 'active' : 'inactive' }}">
                                {{ $type->is_active ? 'فعّال' : 'معطّل' }}
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.visit-types.toggle', $type) }}" style="display:inline;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-action" title="تفعيل/تعطيل">تبديل</button>
                            </form>
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
