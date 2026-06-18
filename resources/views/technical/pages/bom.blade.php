<div class="section-view" id="section-bom">
      <div id="analytics-bom">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📦', 'label' => 'خام', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '🏭', 'label' => 'تحت التشغيل', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '✅', 'label' => 'تام', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '💰', 'label' => 'قيمة إجمالية', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📋 قائمة المواد (BOM) — خام → تحت التشغيل → تام</h3>
          <span class="badge" id="bomBadge">0 قوائم</span>
        </div>

        <div class="bom-summary" id="bomSummary"></div>

        <div class="inventory-toolbar bom-toolbar">
          <input type="text" id="bomSearch" placeholder="بحث بالمريض أو أمر التشغيل...">
          <div class="filter-pills" id="bomFilters">
            <button class="filter-pill active" data-bomfilter="all">الكل</button>
            <button class="filter-pill" data-bomfilter="raw">📦 خام</button>
            <button class="filter-pill" data-bomfilter="wip">🏭 تحت التشغيل</button>
            <button class="filter-pill" data-bomfilter="finished">✅ تام</button>
          </div>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportBom('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportBom('pdf')">📄 PDF</button>
          </div>
        </div>

        <div class="bom-table-wrap">
          <table class="bom-table">
            <thead>
              <tr>
                <th>رقم BOM</th>
                <th>المريض</th>
                <th>أمر التشغيل</th>
                <th>المرحلة</th>
                <th>البنود</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="bomTable"></tbody>
            <tfoot>
              <tr>
                <td colspan="6" id="bomFooter">—</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
