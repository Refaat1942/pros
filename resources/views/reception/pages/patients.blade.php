<div class="tab-content" id="tab-patients">
@include('reception.partials.add-patient-form', ['show_toggle' => false])
      <div class="panel">
        <div class="panel-header">
          <h3>👤 سجل المرضى المسجلين</h3>
          <span style="font-size:12px;font-weight:600;color:var(--primary);" id="patientsCount">0 مرضى</span>
        </div>
        <div class="search-bar">
          <input type="text" id="patientSearch" placeholder="🔍 بحث بالاسم أو رقم الهاتف...">
          <select id="patientStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="quoted">عرض سعر</option>
            <option value="done">مكتمل</option>
          </select>
          <div class="export-btns" style="margin-right:0;">
            <button class="btn-export excel" onclick="exportPatients('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportPatients('pdf')">📄 PDF</button>
          </div>
          <button class="btn btn-primary" style="padding:10px 20px;" onclick="openAddPatientForm()">➕ مريض جديد</button>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>اسم المريض</th>
                <th>رقم الهاتف</th>
                <th>جهة التعاقد</th>
                <th>تاريخ التسجيل</th>
                <th>آخر زيارة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="patientsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
<script>
window.__PATIENTS = @json(($patients ?? collect())->values());
</script>
