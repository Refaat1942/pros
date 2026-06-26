<div class="panel inventory-wrap">
    <div class="panel-header">
        <h3>↩️ طلبات ارتجاع المواد — ورشة → مخزن</h3>
        <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" class="btn-view" id="btnRefreshReturns">↻ تحديث</button>
            <span class="badge" id="returnsBadge">0</span>
        </div>
    </div>
    <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.7;">
        مكتب التشغيل يُرسل مواداً زائدة أو غير مستخدمة إلى المخزن.
        بعد الإرسال تظهر الحالة <strong>بانتظار استلام المخزن</strong> حتى يؤكد أمين المخزن بالباركود.
    </p>
    <div class="bom-summary" id="returnsSummary"></div>
    <div class="inventory-toolbar bom-toolbar">
        <button type="button" class="btn-action primary" id="btnNewReturn">📤 طلب ارتجاع مواد للمخزن</button>
    </div>
    <div class="bom-table-wrap">
        <table data-paginate="10" class="bom-table">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>أمر التشغيل</th>
                    <th>المريض</th>
                    <th>البنود</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody id="returnsTable">
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">جاري تحميل الطلبات…</td></tr>
            </tbody>
        </table>
    </div>
</div>
