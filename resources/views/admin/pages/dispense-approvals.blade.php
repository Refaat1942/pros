<div class="panel">
    <div class="panel-header">
        <h3>✅ اعتمادات صرف المخزن</h3>
        <button type="button" class="btn-action" id="btnRefreshDispenseApprovals">↻ تحديث</button>
    </div>
    <p class="text-muted" style="padding:0 16px 8px;">
        طلبات الصرف المعلّقة من المخزن — راجع الأصناف قبل الاعتماد. الرفض يتطلب سبباً يُرسل للمخزن للتعديل.
    </p>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>الحالة</th>
                    <th>WO</th>
                    <th>المريض</th>
                    <th>BOM</th>
                    <th>مسح</th>
                    <th>طلب بواسطة</th>
                    <th>التاريخ</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="dispenseApprovalsTable"></tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="dispenseDetailModal">
    <div class="catalog-modal" style="max-width:720px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header"><h3>📦 تفاصيل طلب الصرف</h3></div>
        <div class="catalog-modal-body">
            <div id="dispenseDetailMeta" class="dispense-detail-meta"></div>
            <h4 style="margin:12px 0 8px;font-size:14px;">بنود BOM</h4>
            <table class="table-compact">
                <thead>
                    <tr><th>كود الصنف</th><th>الاسم</th><th>مطلوب</th><th>ممسوح</th><th></th></tr>
                </thead>
                <tbody id="dispenseDetailItems"></tbody>
            </table>
            <h4 style="margin:12px 0 8px;font-size:14px;">سجل المسح</h4>
            <div id="dispenseDetailScans" class="dispense-scans-wrap"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="closeDispenseDetail">إغلاق</button>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="dispenseRejectModal">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header"><h3>رفض طلب الصرف</h3></div>
        <input type="hidden" id="dispenseRejectId">
        <div class="catalog-modal-body">
            <label for="dispenseRejectReason" style="display:block;font-weight:700;margin-bottom:6px;">سبب الرفض (مطلوب)</label>
            <textarea id="dispenseRejectReason" class="form-control" rows="4" required placeholder="اكتب ما يجب تعديله في المخزن (أصناف، كميات، باركود...)"></textarea>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelDispenseReject">إلغاء</button>
            <button type="button" class="btn-action danger" id="confirmDispenseReject">رفض وإرسال للمخزن</button>
        </div>
    </div>
</div>

<style>
.dispense-detail-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; font-size: 13px; margin-bottom: 8px; }
.dispense-scans-wrap { display: flex; flex-wrap: wrap; gap: 6px; }
.dispense-scan-chip { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px; font-family: monospace; font-size: 12px; direction: ltr; }
</style>
