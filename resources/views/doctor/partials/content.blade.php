<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">🩺</div>
      <h2>لوحة الطبيب المعالج</h2>
      <span>العيادة والتشخيص</span>
    </div>
    <!-- <div class="privacy-notice">
      <strong>🔒 حجب مالي مفعّل</strong>
      جميع الأسعار والتكاليف محجوبة عن هذه اللوحة وفقاً لسياسة فصل الاختصاصات.
    </div> -->
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="queue"><span class="nav-icon">📋</span> قائمة الانتظار</a></li>
      <li><a href="#" data-section="diagnosis"><span class="nav-icon">📝</span> التشخيص الطبي</a></li>
      <li><a href="#" data-section="records"><span class="nav-icon">📁</span> السجل الطبي</a></li>
      <li><a href="#" data-section="transfer"><span class="nav-icon">📦</span> المحولون للمخزون</a></li>
    </ul>

  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">العيادة الطبية — قائمة الانتظار</h1>
        <p></p>
      </div>
      <div class="user-chip">
        <div class="avatar"></div>
        <span></span>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-mini">
        <div class="icon">📅</div>
        <div class="info">
          <div class="label">حالات اليوم</div>
          <div class="value" id="todayCount">8</div>
        </div>
      </div>
      <div class="stat-mini">
        <div class="icon">⏳</div>
        <div class="info">
          <div class="label">في الانتظار</div>
          <div class="value" id="waitingCount">5</div>
        </div>
      </div>
      <div class="stat-mini">
        <div class="icon">✅</div>
        <div class="info">
          <div class="label">تم الفحص</div>
          <div class="value">3</div>
        </div>
      </div>
    </div>

    <!-- Queue Section -->
    <div class="section-view active" id="section-queue">
    <div id="analytics-queue">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '📋', 'label' => 'قائمة الانتظار', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
      ['icon' => '🚨', 'label' => 'عاجل', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
      ['icon' => '⏳', 'label' => 'عادي', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '⏱️', 'label' => 'متوسط الانتظار', 'value' => '—', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
    ]])</div>
    <div class="panel">
      <div class="panel-header">
        <h3>📋 قائمة الانتظار الرقمية</h3>
        <span class="count-badge" id="queueBadge">0</span>
      </div>
      <div class="data-toolbar">
        <input type="text" id="queueSearch" placeholder="🔍 بحث بالاسم أو الجهة...">
        <span class="toolbar-count" id="queueCount">0 مريض</span>
        <div class="export-btns">
          <button class="btn-export excel" onclick="exportQueue('excel')">📊 Excel</button>
          <button class="btn-export pdf" onclick="exportQueue('pdf')">📄 PDF</button>
        </div>
      </div>
      <div class="panel-body">
        <table data-paginate="10">
          <thead>
            <tr>
              <th>#</th>
              <th>اسم المريض</th>
              <th>الجهة</th>
              <th>الانتظار</th>
            </tr>
          </thead>
          <tbody id="queueTable"></tbody>
        </table>
      </div>
    </div>
    </div>

    <!-- Diagnosis Section -->
    <div class="section-view" id="section-diagnosis">
      <div id="analytics-diagnosis">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📝', 'label' => 'تقارير اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '📦', 'label' => 'أصناف المخزون', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '💊', 'label' => 'توصيات', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '📦', 'label' => 'محول للمخزون', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel form-panel">
        <div class="panel-header">
          <h3>📝 التشخيص الطبي</h3>
        </div>
        <div class="panel-body">
          <div class="patient-info-bar" id="patientBar">
            <h4 id="selectedPatientName">—</h4>
            <p id="selectedPatientInfo">—</p>
          </div>

          <div class="silent-clinic-note" id="silentClinicNote" style="display:none;">
            🪖 <span>مريض عسكري — <strong>عيادة صامتة</strong>: يُسجَّل الكشف والتوصيف ويتخطّى النظام عرض السعر والتحصيل (تقييم مالي صامت في الخلفية).</span>
          </div>

          <form id="diagnosisForm">
            <div class="form-group">
              <label>التوصيات الطبية <span class="required">*</span></label>
              <div class="stock-multi-select" id="medicalRecommendationsSelect">
                <div class="sms-selected"></div>
                <div class="sms-control">
                  <input type="text" class="sms-search" placeholder="🔍 ابحث واختر من أصناف المخزون..." autocomplete="off">
                  <button type="button" class="sms-toggle" aria-label="فتح القائمة">▼</button>
                </div>
                <div class="sms-dropdown"></div>
              </div>
              <p class="field-hint">اختيار متعدد من المخزون — حدّد <strong>الكمية</strong> لكل صنف (بحد أقصى المتوفر)</p>
            </div>

            <div class="form-group">
              <label>التشخيص الدقيق <span class="required">*</span></label>
              <textarea class="form-control" id="diagnosis" placeholder="أدخل التشخيص الطبي التفصيلي..." required></textarea>
            </div>

            <div class="form-group">
              <label>الروشتة الطبية</label>
              <textarea class="form-control" id="prescription" placeholder="الأدوية والإرشادات الطبية (اختياري)..."></textarea>
            </div>

            <!-- <div class="blocked-notice">
              🔒 الأسعار والتكاليف المالية محجوبة — لا تظهر في هذه الشاشة
            </div> -->

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" id="saveBtn" disabled>
                💾 حفظ واعتماد التقرير
              </button>
              <button type="button" class="btn btn-transfer" id="transferBtn" disabled>
                📦 تحويل للمخزون
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Records Section -->
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

    <!-- Transfer Section -->
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
  </main>

  <div class="toast" id="toast"></div>

  <div class="record-modal-overlay" id="recordDetailModal" role="dialog" aria-modal="true" aria-labelledby="recordModalTitle">
    <div class="record-modal" onclick="event.stopPropagation()">
      <div class="record-modal-header">
        <div>
          <h3 id="recordModalTitle">—</h3>
          <div class="modal-meta" id="recordModalMeta">—</div>
        </div>
        <button type="button" class="record-modal-close" id="recordModalClose" aria-label="إغلاق">&times;</button>
      </div>
      <div class="record-modal-body" id="recordModalBody"></div>
      <div class="record-modal-footer">
        <button type="button" class="btn-close-modal" id="recordModalCloseBtn">إغلاق</button>
      </div>
    </div>
  </div>