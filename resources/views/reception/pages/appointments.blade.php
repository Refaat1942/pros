<div class="appointments-calendar-top visible" id="appointmentsCalendarWrap">
      <div class="calendar-panel">
        <div class="calendar-toolbar">
          <div class="calendar-header">
            <button type="button" class="cal-nav-btn" id="calPrev" aria-label="الشهر السابق">›</button>
            <h3 id="calMonthLabel"></h3>
            <button type="button" class="cal-nav-btn" id="calNext" aria-label="الشهر التالي">‹</button>
          </div>
          <p class="calendar-hint">اختر يوماً من اليوم أو ما قبله (حتى سنة) — الأيام المستقبلية غير متاحة</p>
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
@include('reception.partials.add-patient-form')

<div class="tab-content" id="tab-appointments">
      <div id="analytics-appointments">@include('partials.dashboard-analytics-empty', [
        'hide_charts' => true,
        'stats' => [
        ['icon' => '📅', 'label' => 'مواعيد اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🏥', 'label' => 'في العيادة', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '⏳', 'label' => 'انتظار', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
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
            <table data-paginate="10">
              <thead>
                <tr>
                  <th>الوقت</th>
                  <th>اسم المريض</th>
                  <th>نوع الزيارة</th>
                  <th>رقم الهاتف</th>
                  <th>جهة التعاقد / الرتبة</th>
                  <th>إجراء</th>
                </tr>
              </thead>
              <tbody id="appointmentsTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

@if (session('show_patient_card'))
<script>window.__SHOW_PATIENT_CARD_ID = {{ (int) session('show_patient_card') }};</script>
@endif
