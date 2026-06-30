<div class="section-view" id="section-adjustments">
      <div id="analytics-adjustments">@include('partials.dashboard-analytics-empty', ['hide_charts' => true, 'stats' => [
        ['icon' => '📏', 'label' => 'حالات بالمعدلات', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📏 المعدلات</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <input type="search" id="adjSearch" placeholder="🔍 بحث رقم الحالة / الطلب / مريض..."
                   class="form-control" style="max-width:220px;">
            <button type="button" class="btn-action primary" id="btnRefreshAdj">↻ تحديث</button>
            <span class="badge" id="adjBadge">0</span>
          </div>
        </div>
        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>الحالة / الطلب</th>
                <th>المريض</th>
                <th>النوع</th>
                <th>عدد البنود</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="adjustmentsTable">
              <tr><td colspan="5" class="empty-cell">جاري تحميل الحالات…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
