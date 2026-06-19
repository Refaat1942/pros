<div class="section-view" id="section-reports">
      <div class="reports-section-title">💰 التقارير المالية والتشغيلية</div>
      <div class="report-cards" id="financialReportCards">
        <div class="report-card"><h4>📈 الإيرادات الشهرية</h4></div>
        <div class="report-card"><h4>🔥 الأصناف الأكثر طلباً</h4></div>
        <div class="report-card"><h4>📋 أوامر التشغيل — هذا الشهر</h4></div>
      </div>

      <div class="reports-section-title">📦 تقارير المخزون والتحليلات الذكية</div>
      <div class="report-cards" id="inventoryReportCards">
        <div class="report-card wide"><h4>💚 صحة المخزون الإجمالية</h4></div>
        <div class="report-card"><h4>⚠️ الأصناف الراكدة</h4></div>
        <div class="report-card"><h4>🔴 تحت الحد الأدنى</h4></div>
        <div class="report-card"><h4>📤 حركات الصرف</h4></div>
        <div class="report-card"><h4>📥 استلام من الموردين</h4></div>
        <div class="report-card"><h4>🏷️ الدفعات النشطة (Batch Tracking)</h4></div>
        <div class="report-card wide" id="bomAdminPanel">
          <h4>📋 BOM — خام / تحت التشغيل / تام (قيمة Highest Batch Cost)</h4>
          <div id="bomAdminSummary" class="bom-admin-summary">
            <div class="bom-admin-stat raw">
              <div class="bas-label">خام</div>
              <div class="bas-value">0 قائمة</div>
              <div class="bas-money">0 ج.م</div>
              <div class="bas-sub">0 بند</div>
            </div>
            <div class="bom-admin-stat wip">
              <div class="bas-label">تحت التشغيل</div>
              <div class="bas-value">0 قائمة</div>
              <div class="bas-money">0 ج.م</div>
              <div class="bas-sub">0 بند</div>
            </div>
            <div class="bom-admin-stat finished">
              <div class="bas-label">تام</div>
              <div class="bas-value">0 قائمة</div>
              <div class="bas-money">0 ج.م</div>
              <div class="bas-sub">0 بند</div>
            </div>
          </div>
          <div class="bom-admin-table-wrap">
            <table data-paginate="10" class="data-table bom-admin-table">
              <thead>
                <tr>
                  <th>المريض</th>
                  <th>أمر التشغيل</th>
                  <th>المرحلة</th>
                  <th>البنود</th>
                  <th>قيمة BOM</th>
                </tr>
              </thead>
              <tbody id="bomAdminTable">
                <tr><td colspan="5" class="empty-cell">لا توجد قوائم BOM</td></tr>
              </tbody>
            </table>
          </div>
          <div class="card-footer" id="bomAdminFooter"></div>
        </div>
        <div class="report-card"><h4>⏳ أوامر تحضير معلقة</h4></div>
      </div>
    </div>
