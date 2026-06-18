<div class="section-view" id="section-operations">
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>🎯 مكتب التشغيل — أوامر الصرف والإنتاج</h3>
          <span class="badge" id="opsBadge">0</span>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
          يلتقي هنا المساران (مدني بعد الموافقة · عسكري مباشرة). كل حالة لها <strong>رقم أمر تشغيل مركزي</strong>. الصرف للمخزن يتم بمسح الباركود.
        </p>
        <div class="bom-summary" id="opsSummary"></div>
        <div class="bom-table-wrap">
          <table class="bom-table">
            <thead>
              <tr>
                <th>أمر التشغيل</th>
                <th>المريض</th>
                <th>التصنيف</th>
                <th>مرحلة BOM / الشغل</th>
                <th>البنود</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="opsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
