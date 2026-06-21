<div class="section-view" id="section-returns">
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>↩️ إذن ارتجاع — ورشة → مخزن</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" class="btn-view" id="btnRefreshReturns">↻ تحديث</button>
            <span class="badge" id="returnsBadge">0</span>
          </div>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.7;">
          ارتجاع داخلي مرتبط بـ <strong>BOM وأمر التشغيل</strong> — كامل أو جزئي.
          متاح فقط لـ <strong>BOM في «تحت التشغيل» (WIP)</strong> وبنود <strong>اتصرفت فعلاً</strong> للورشة.
          لا يؤثر على مديونية الجهة.
        </p>
        <div class="bom-summary" id="returnsSummary"></div>
        <div class="inventory-toolbar bom-toolbar">
          <button type="button" class="btn-view" id="btnNewReturn">➕ إنشاء إذن ارتجاع</button>
        </div>
        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>رقم الإذن</th>
                <th>أمر التشغيل</th>
                <th>المريض</th>
                <th>البنود</th>
                <th>الحالة</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="returnsTable">
              <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">جاري تحميل الأذونات…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
