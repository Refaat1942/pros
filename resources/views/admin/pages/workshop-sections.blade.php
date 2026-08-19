@if (empty($workshop_technicians ?? []))
    <div class="panel" style="margin-bottom:16px;border:1px solid #fdba74;background:#fff7ed;">
        <div class="panel-body" style="padding:16px 20px;font-size:13px;line-height:1.7;color:#9a3412;">
            <strong>⚠️ لا يوجد فنيون إنتاج مسجّلون بعد.</strong>
            <p style="margin:8px 0 0;">
                أضف الموظفين أولاً من
                <a href="{{ route('admin.employees') }}" style="color:#c2410c;font-weight:700;text-decoration:underline;">الإدارة → الموظفون</a>
                واختر لهم دور <strong>«قسم الإنتاج»</strong>، ثم ارجع هنا لربطهم بالأقسام من قائمة «الفنيون».
            </p>
        </div>
    </div>
@endif

<div class="panel">
    <div class="panel-header">
        <h3>🏭 أقسام قسم الإنتاج</h3>
        <button type="button" class="btn-add-rank" id="btnAddWorkshopSection">➕ إضافة قسم</button>
    </div>
    <div class="data-toolbar">
        <input type="text" id="workshopSectionSearch" placeholder="🔍 بحث...">
        <span class="toolbar-count" id="workshopSectionCount">0 قسم</span>
    </div>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>القسم</th>
                    <th>الكود</th>
                    <th>الفنيون</th>
                    <th>الحالة</th>
                    <th style="width:160px">إجراء</th>
                </tr>
            </thead>
            <tbody id="workshopSectionsTable"></tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="workshopSectionModal" role="dialog" aria-modal="true" aria-labelledby="workshopSectionModalTitle">
    <div class="catalog-modal" style="max-width:520px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="workshopSectionModalTitle">➕ قسم ورشة</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeWorkshopSectionModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="workshopSectionId">
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionName">اسم القسم <span style="color:#dc2626">*</span></label>
                <input type="text" id="workshopSectionName" class="form-control" maxlength="100" placeholder="مثال: تركيب المفاصل">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionCode">الكود (اختياري)</label>
                <input type="text" id="workshopSectionCode" class="form-control" maxlength="50" placeholder="JOINTS" dir="ltr">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionDescription">الوصف</label>
                <textarea id="workshopSectionDescription" class="form-control" rows="3" placeholder="وصف مختصر للقسم..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionTechnicians">الفنيون</label>
                <select id="workshopSectionTechnicians" class="form-control" multiple size="5"></select>
                <small style="display:block;margin-top:6px;color:var(--text-muted);font-size:12px;">
                    اضغط Ctrl (أو Cmd) لاختيار أكثر من فني.
                    @if (empty($workshop_technicians ?? []))
                        القائمة فارغة — <a href="{{ route('admin.employees') }}">أضف موظفاً بدور قسم الإنتاج</a> أولاً.
                    @endif
                </small>
            </div>
            <label class="form-check-row" for="workshopSectionActive">
                <input type="checkbox" id="workshopSectionActive" checked>
                <span>نشط</span>
            </label>
            <div id="workshopSectionError" style="display:none;color:#dc2626;margin-top:12px;font-size:13px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelWorkshopSectionModal">إلغاء</button>
            <button type="button" class="btn-action success" id="saveWorkshopSectionBtn">💾 حفظ</button>
        </div>
    </div>
</div>

<script>
window.__WORKSHOP_SECTIONS = @json($workshop_sections ?? []);
window.__WORKSHOP_TECHNICIANS = @json($workshop_technicians ?? []);
</script>
