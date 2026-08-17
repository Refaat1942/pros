<div class="panel inventory-wrap">
    <div class="panel-header">
        <h3>↩️ استلام ارتجاع المواد — من قسم الإنتاج</h3>
        <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" class="btn-view" id="btnRefreshReturns">↻ تحديث</button>
            <span class="badge" id="returnsBadge">0</span>
        </div>
    </div>
    <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.7;">
        طلبات الارتجاع الواردة من مكتب التشغيل — أكّد الاستلام بمسح الباركود ومطابقة الأصناف.
        لا يُنشأ الارتجاع من المخزن؛ قسم الإنتاج هي التي تُرسل الطلب.
    </p>
    <div class="bom-summary" id="returnsSummary"></div>
    <div class="bom-table-wrap">
        <table data-paginate="10" class="bom-table">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>أمر التشغيل</th>
                    <th>المريض</th>
                    <th>البنود</th>
                    <th>الحالة</th>
                    <th class="col-actions">إجراء</th>
                </tr>
            </thead>
            <tbody id="returnsTable">
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">جاري تحميل الطلبات…</td></tr>
            </tbody>
        </table>
    </div>
</div>
