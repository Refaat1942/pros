<div class="section-view" id="section-transfer">
      <div id="analytics-transfer">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🔧', 'label' => 'محول', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '⚙️', 'label' => 'قيد التوصيف', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '🏭', 'label' => 'في الورشة', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '✅', 'label' => 'مكتمل', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>📦 الحالات المحولة للمخزون</h3>
          <span class="count-badge" id="transferredCount">0</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="transferSearch" placeholder="🔍 بحث بالاسم أو الجهة...">
          <select id="transferStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="قيد التوصيف">قيد التوصيف</option>
            <option value="في الورشة">في الورشة</option>
            <option value="مكتمل">مكتمل</option>
          </select>
          <span class="toolbar-count" id="transferCount">0 حالة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportTransferred('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportTransferred('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>المريض</th>
                <th>التوصيات الطبية</th>
                <th>الجهة</th>
                <th>تاريخ التحويل</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="transferredTable"></tbody>
          </table>
        </div>
      </div>
    </div>
