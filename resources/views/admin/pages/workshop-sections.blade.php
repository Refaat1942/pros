<div class="panel">
    <div class="panel-header">
        <h3>🏭 أقسام الورشة</h3>
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

<div class="catalog-modal-overlay" id="workshopSectionModal">
    <div class="catalog-modal" style="max-width:520px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <h3 id="workshopSectionModalTitle">➕ قسم ورشة</h3>
            <button type="button" class="catalog-modal-close" id="closeWorkshopSectionModal">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="workshopSectionId">
            <div class="form-group" style="margin-bottom:12px;">
                <label>اسم القسم</label>
                <input type="text" id="workshopSectionName" class="form-control" maxlength="100">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label>الكود (اختياري)</label>
                <input type="text" id="workshopSectionCode" class="form-control" maxlength="50">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label>الوصف</label>
                <textarea id="workshopSectionDescription" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label>الفنيون</label>
                <select id="workshopSectionTechnicians" class="form-control" multiple size="5"></select>
            </div>
            <label><input type="checkbox" id="workshopSectionActive" checked> نشط</label>
            <div id="workshopSectionError" style="display:none;color:#dc2626;margin-top:10px;"></div>
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
