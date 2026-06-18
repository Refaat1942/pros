<div class="appointments-calendar-top visible" id="appointmentsCalendarWrap">
      <div class="calendar-panel">
        <div class="calendar-toolbar">
          <div class="calendar-header">
            <button type="button" class="cal-nav-btn" id="calPrev" aria-label="الشهر السابق">›</button>
            <h3 id="calMonthLabel"></h3>
            <button type="button" class="cal-nav-btn" id="calNext" aria-label="الشهر التالي">‹</button>
          </div>
          <p class="calendar-hint">اضغط على أي يوم لعرض مواعيده في الجدول أدناه</p>
          <button type="button" class="calendar-today-btn" id="calToday">📅 مواعيد اليوم</button>
        </div>
        <div class="calendar-body">
          <div class="calendar-weekdays">
            <span>أ</span><span>إ</span><span>ث</span><span>أ</span><span>خ</span><span>ج</span><span>س</span>
          </div>
          <div class="calendar-grid" id="calendarGrid"></div>
        </div>
      </div>
    </div>
<section class="add-patient-section" id="addPatientSection">
      <button type="button" class="add-patient-toggle" id="btnAddPatient" aria-expanded="false" aria-controls="addPatientFormWrap">
        <span class="add-patient-toggle-icon">➕</span>
        <span class="add-patient-toggle-text">
          <strong>إضافة مريض</strong>
          <small>تسجيل ملف جديد — اضغط لفتح النموذج</small>
        </span>
        <span class="add-patient-chevron" id="addPatientChevron">▼</span>
      </button>
      <div class="add-patient-form-wrap" id="addPatientFormWrap">
        <div class="add-patient-form-body">
          <h4>➕ تسجيل مريض جديد</h4>
          <div class="add-patient-form-grid">
            <div class="form-group">
              <label>اسم المريض</label>
              <input type="text" class="form-control" id="newPatientName" placeholder="الاسم الكامل">
            </div>
            <div class="form-group">
              <label>رقم الهاتف</label>
              <input type="tel" class="form-control" id="newPhone" placeholder="01xxxxxxxxx" maxlength="11">
            </div>
            <div class="form-group">
              <label>تصنيف المريض</label>
              <select class="form-control" id="newPatientType">
                <option value="civilian">🌐 مدني</option>
                <option value="military">🪖 عسكري</option>
              </select>
            </div>
            <div class="form-group" id="grpRank" style="display:none;">
              <label>الرتبة العسكرية</label>
              <input type="text" class="form-control" id="newRank" placeholder="مثال: نقيب / رائد">
            </div>
            <div class="form-group">
              <label>جهة التعاقد</label>
              <select class="form-control" id="newCompany">
                <option value="">— اختر الجهة —</option>
                <option>شركة التأمين الوطني</option>
                <option>هيئة التأمين الصحي</option>
                <option>صندوق رعاية ذوي الإعاقة</option>
                <option>شركة مصر للتأمين</option>
                <option>مجلس الدفاع المدني</option>
                <option>إدارة القوات المسلحة الطبية</option>
                <option>الحرس الجمهوري — الخدمات الطبية</option>
              </select>
            </div>
          </div>
          <div class="add-patient-form-actions">
            <button class="btn btn-secondary" type="button" id="btnCancelAddPatient">إلغاء</button>
            <button class="btn btn-primary" type="button" id="btnSavePatient">💾 حفظ وإضافة للجدولة</button>
          </div>
        </div>
      </div>
    </section>
<div id="analytics-reception-main">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '📅', 'label' => 'مواعيد اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '⏳', 'label' => 'انتظار', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ['icon' => '👤', 'label' => 'مرضى', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
      ['icon' => '🧾', 'label' => 'عروض سعر', 'value' => '0', 'bg' => 'rgba(5,150,105,0.1)'],
    ]])</div>

<div class="tab-content" id="tab-appointments">
      <div id="analytics-appointments">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📅', 'label' => 'إجمالي', 'value' => '0', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🏥', 'label' => 'في العيادة', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '⏳', 'label' => 'انتظار', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '✅', 'label' => 'مكتمل', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ]])</div>
      <div class="appointments-layout">
        <div class="panel">
          <div class="panel-header">
            <h3 id="apptPanelTitle">📅 مواعيد</h3>
            <span style="font-size:12px;font-weight:600;color:var(--primary);" id="apptHeaderCount">0 موعد</span>
          </div>
          <div class="data-toolbar">
            <input type="text" id="apptSearch" placeholder="🔍 بحث بالاسم أو رقم الهاتف...">
            <select id="apptStatusFilter">
              <option value="all">كل الحالات</option>
              <option value="waiting">انتظار</option>
              <option value="in_clinic">في العيادة</option>
              <option value="quoted">عرض سعر</option>
              <option value="done">مكتمل</option>
            </select>
            <span class="toolbar-count" id="apptCount">0 موعد</span>
            <div class="export-btns">
              <button class="btn-export excel" onclick="exportAppointments('excel')">📊 Excel</button>
              <button class="btn-export pdf" onclick="exportAppointments('pdf')">📄 PDF</button>
            </div>
          </div>
          <div class="panel-body">
            <table>
              <thead>
                <tr>
                  <th>الوقت</th>
                  <th>اسم المريض</th>
                  <th>نوع الزيارة</th>
                  <th>رقم الهاتف</th>
                  <th>جهة التعاقد</th>
                  <th>الحالة</th>
                  <th>إجراء</th>
                </tr>
              </thead>
              <tbody id="appointmentsTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
