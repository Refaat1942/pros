<div class="section-view" id="section-employees">
      <div id="analytics-employees">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '👥', 'label' => 'الموظفون', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '✅', 'label' => 'نشط', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏸️', 'label' => 'غير نشط', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
        ['icon' => '🩺', 'label' => 'أطباء', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>👥 إدارة الموظفين والصلاحيات</h3>
          <span class="badge" id="employeesSectionBadge">0 موظف</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="empSearch" placeholder="🔍 بحث بالاسم...">
          <select id="empRoleFilter">
            <option value="all">كل الأدوار</option>
            <option value="admin">إدارة النظام</option>
            <option value="doctor">طبيب</option>
            <option value="technical">فني</option>
            <option value="reception">استقبال</option>
            <option value="store">مخزن</option>
          </select>
          <select id="empStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="active">نشط</option>
            <option value="inactive">غير نشط</option>
          </select>
          <span class="toolbar-count" id="empCount">0 موظف</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportEmployees('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportEmployees('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>الاسم</th>
                <th>الدور</th>
                <th>الحالة</th>
                <th>آخر دخول</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="employeesTableFull"></tbody>
          </table>
        </div>
      </div>
    </div>
