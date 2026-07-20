<div class="panel">
    <div class="panel-header">
        <h3>✅ اعتمادات صرف المخزن</h3>
        <button type="button" class="btn-action" id="btnRefreshDispenseApprovals">↻ تحديث</button>
    </div>
    <p class="text-muted" style="padding:0 16px 8px;">
        طلبات الصرف المعلّقة من المخزن — الاعتماد ينفّذ الخصم الفعلي.
    </p>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>الحالة</th>
                    <th>WO</th>
                    <th>المريض</th>
                    <th>BOM</th>
                    <th>طلب بواسطة</th>
                    <th>التاريخ</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="dispenseApprovalsTable"></tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="dispenseRejectModal">
    <div class="catalog-modal" style="max-width:440px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header"><h3>رفض طلب الصرف</h3></div>
        <input type="hidden" id="dispenseRejectId">
        <div class="catalog-modal-body">
            <textarea id="dispenseRejectReason" class="form-control" rows="3" placeholder="سبب الرفض (اختياري)"></textarea>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelDispenseReject">إلغاء</button>
            <button type="button" class="btn-action danger" id="confirmDispenseReject">رفض</button>
        </div>
    </div>
</div>
