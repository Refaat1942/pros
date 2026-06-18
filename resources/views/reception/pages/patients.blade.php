<div class="tab-content" id="tab-patients">
      <div id="analytics-patients">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '👤', 'label' => 'مرضى', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '✅', 'label' => 'نشط', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '💰', 'label' => 'عرض سعر', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '⏸️', 'label' => 'غير نشط', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>👤 سجل المرضى المسجلين</h3>
          <span style="font-size:12px;font-weight:600;color:var(--primary);" id="patientsCount">0 مرضى</span>
        </div>
        <div class="search-bar">
          <input type="text" id="patientSearch" placeholder="🔍 بحث بالاسم أو رقم الهاتف...">
          <select id="patientStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="active">نشط</option>
            <option value="quoted">عرض سعر</option>
            <option value="done">مكتمل</option>
            <option value="inactive">غير نشط</option>
          </select>
          <div class="export-btns" style="margin-right:0;">
            <button class="btn-export excel" onclick="exportPatients('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportPatients('pdf')">📄 PDF</button>
          </div>
          <button class="btn btn-primary" style="padding:10px 20px;" onclick="openAddPatientForm()">➕ مريض جديد</button>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>اسم المريض</th>
                <th>رقم الهاتف</th>
                <th>جهة التعاقد</th>
                <th>تاريخ التسجيل</th>
                <th>آخر زيارة</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="patientsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
