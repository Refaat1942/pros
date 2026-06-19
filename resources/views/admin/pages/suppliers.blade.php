<div class="section-view" id="section-suppliers">
      <div id="analytics-suppliers">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🏭', 'label' => 'موردون', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'فواتير', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '✅', 'label' => 'مسددة', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'معلقة', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>🏭 الموردون وفواتير المشتريات</h3>
          <span class="badge" id="suppliersSectionBadge">0 مورد</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="supplierSearch" placeholder="🔍 بحث بالمورد أو التخصص...">
          <select id="supplierStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="paid">مسددة</option>
            <option value="partial">جزئية</option>
            <option value="pending">معلقة</option>
          </select>
          <span class="toolbar-count" id="supplierCount">0 مورد</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportSuppliers('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportSuppliers('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>المورد</th>
                <th>التخصص</th>
                <th>آخر فاتورة</th>
                <th>قيمة الفاتورة</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="suppliersTable"></tbody>
          </table>
        </div>
      </div>
    </div>
