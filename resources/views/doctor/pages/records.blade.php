<div class="section-view" id="section-records">
      <div id="analytics-records">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📁', 'label' => 'تقارير', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '✅', 'label' => 'معتمد', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '💊', 'label' => 'متوسط التوصيات', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '📦', 'label' => 'أصناف مختلفة', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>📁 السجل الطبي — التقارير المعتمدة</h3>
          <span class="count-badge">0 تقرير</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="recordsSearch" placeholder="🔍 بحث بالاسم أو التوصيات...">
          <span class="toolbar-count" id="recordsCount">0 تقرير</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportRecords('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportRecords('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>المريض</th>
                <th>التوصيات الطبية</th>
                <th>الطبيب</th>
                <th>التاريخ</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="recordsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
