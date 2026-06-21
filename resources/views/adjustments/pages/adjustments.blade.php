<div class="section-view" id="section-adjustments">
      <div id="analytics-adjustments">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📏', 'label' => 'حالات للمعد', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
        ['icon' => '1️⃣', 'label' => 'تجربة أولى', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '2️⃣', 'label' => 'تجربة ثانية', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'بانتظار تجربة', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📏 جدول المعدلات والتجارب</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <input type="search" id="adjSearch" placeholder="🔍 بحث WO / مريض / حالة..."
                   class="form-control" style="max-width:220px;">
            <button type="button" class="btn-action primary" id="btnRefreshAdj">↻ تحديث</button>
            <span class="badge" id="adjBadge">0</span>
          </div>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.7;">
          تظهر الحالة هنا بعد <strong>صرف BOM للورشة</strong> (BOM تحت التشغيل أو تام) — أي بعد خروجها من لوحة المخزون.
          يلتقي هنا المساران: <strong>مدني</strong> (بعد موافقة العقد) و<strong>عسكري</strong> (بعد التسعير الخلفي).
          تبقى الحالة ظاهرة حتى <strong>جاهزة للتسليم</strong> لتسجيل التجربة الأولى والثانية وملاحظات المقاسات.
        </p>
        <div class="bom-summary" id="adjSummary"></div>
        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>أمر التشغيل</th>
                <th>المريض</th>
                <th>المرحلة</th>
                <th>تجربة 1</th>
                <th>تجربة 2</th>
                <th>ملاحظات</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="adjustmentsTable">
              <tr><td colspan="7" class="empty-cell">جاري تحميل الحالات…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
