<div class="section-view" id="section-records">
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
