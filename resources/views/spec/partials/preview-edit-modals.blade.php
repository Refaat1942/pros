<div class="modal-overlay" id="specEditModal" role="dialog" aria-modal="true" aria-labelledby="specEditModalTitle">
    <div class="modal" style="max-width:760px;">
        <div class="modal-header">
            <div>
                <h3 id="specEditModalTitle" style="margin:0;">✏️ طلب تعديل التوصيف</h3>
                <p id="specEditModalMeta" style="margin:4px 0 0;font-size:12px;color:var(--text-muted);font-weight:600;"></p>
            </div>
            <button type="button" class="modal-close" id="specEditModalClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal-body" style="padding:20px 24px;">
            <p style="margin:0 0 16px;font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:12px 14px;line-height:1.6;">
                التعديل لا يُطبَّق مباشرة — يُرسل للإدارة للموافقة أو الرفض مع إشعار لك بالنتيجة.
            </p>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">ملاحظات فنية</label>
                <textarea id="specEditNotes" rows="2" class="form-control"></textarea>
            </div>
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;gap:8px;">
                    <label style="font-size:12px;font-weight:700;color:#475569;">البنود المقترحة</label>
                    <button type="button" id="specEditAddItem" class="btn-action" style="font-size:12px;">+ إضافة صنف</button>
                </div>
                <div class="bom-table-wrap">
                    <table class="bom-table bom-table--compact">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>الصنف</th>
                                <th>الكمية</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="specEditItemsBody"></tbody>
                    </table>
                </div>
            </div>
            <p id="specEditError" style="display:none;margin:14px 0 0;font-size:13px;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:12px 14px;"></p>
        </div>
        <div class="modal-footer" style="padding:14px 20px;border-top:1px solid var(--border,#e2e8f0);display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" id="specEditCancel" class="btn-view">إلغاء</button>
            <button type="button" id="specEditSubmit" class="btn-action primary">📤 إرسال للإدارة</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="specEditCatalogModal" role="dialog" aria-modal="true" style="z-index:1100;">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <h3 style="margin:0;">اختر صنفاً</h3>
            <button type="button" class="modal-close" id="specEditCatalogClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal-body" style="padding:16px 20px 0;">
            <input type="search" id="specEditCatalogSearch" placeholder="بحث بالكود أو الاسم..." class="form-control table-search-input">
        </div>
        <div id="specEditCatalogList" style="overflow-y:auto;max-height:50vh;padding:12px 20px 20px;"></div>
    </div>
</div>
