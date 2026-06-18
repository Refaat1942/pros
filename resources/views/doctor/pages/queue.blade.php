<div class="stats-row">
      <div class="stat-mini">
        <div class="icon">📅</div>
        <div class="info">
          <div class="label">حالات اليوم</div>
          <div class="value" id="todayCount">0</div>
        </div>
      </div>
      <div class="stat-mini">
        <div class="icon">⏳</div>
        <div class="info">
          <div class="label">في الانتظار</div>
          <div class="value" id="waitingCount">0</div>
        </div>
      </div>
      <div class="stat-mini">
        <div class="icon">✅</div>
        <div class="info">
          <div class="label">تم الفحص</div>
          <div class="value">0</div>
        </div>
      </div>
    </div>

<div class="section-view" id="section-queue">
    <div id="analytics-queue">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '📋', 'label' => 'قائمة الانتظار', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
      ['icon' => '🚨', 'label' => 'عاجل', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
      ['icon' => '⏳', 'label' => 'عادي', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '⏱️', 'label' => 'متوسط الانتظار', 'value' => '—', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
    ]])</div>
    <div class="content-grid">
      <div class="panel">
        <div class="panel-header">
          <h3>📋 قائمة الانتظار الرقمية</h3>
          <span class="count-badge" id="queueBadge">0</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="queueSearch" placeholder="🔍 بحث بالاسم أو الجهة...">
          <select id="queuePriorityFilter">
            <option value="all">كل الأولويات</option>
            <option value="urgent">عاجل</option>
            <option value="normal">عادي</option>
          </select>
          <span class="toolbar-count" id="queueCount">0 مريض</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportQueue('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportQueue('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>اسم المريض</th>
                <th>الجهة</th>
                <th>الأولوية</th>
                <th>الانتظار</th>
              </tr>
            </thead>
            <tbody id="queueTable"></tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h3>📝 اختيار مريض للفحص</h3>
        </div>
        <div class="panel-body" style="padding:24px;">
          <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">اختر مريضاً من قائمة الانتظار ثم انتقل لقسم "التشخيص الطبي" لإدخال التقرير.</p>
          <div class="patient-info-bar" id="patientBarQueue">
            <h4 id="selectedPatientNameQueue">—</h4>
            <p id="selectedPatientInfoQueue">—</p>
          </div>
          <button class="btn btn-primary" id="goToDiagnosis" disabled onclick="switchSection('diagnosis')">📝 الانتقال للتشخيص</button>
        </div>
      </div>
    </div>
    </div>
