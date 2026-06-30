<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">📋</div>
      <h2>لوحة موظف الاستقبال</h2>
      <span>الجدولة والموافقات</span>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-tab="appointments"><span class="nav-icon">📅</span> جدولة المواعيد</a></li>
      <li><a href="#" data-tab="ocr"><span class="nav-icon">📄</span> رفع موافقة</a></li>
      <li><a href="#" data-tab="quote"><span class="nav-icon">💰</span> عروض الأسعار</a></li>
      <li><a href="#" data-tab="delivery"><span class="nav-icon">✅</span> تسليم للمريض</a></li>
      <li><a href="#" data-tab="selfservice"><span class="nav-icon">📱</span> متابعة الحالة (خدمة ذاتية)</a></li>
      <li><a href="#" data-tab="patients"><span class="nav-icon">👤</span> المرضى</a></li>
    </ul>

  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1>مكتب الاستقبال والجدولة</h1>
        <p></p>
      </div>
      <div class="user-chip">
        <div class="avatar"></div>
        <span></span>
      </div>
    </div>

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
              <label>اسم المريض <span style="color:red">*</span></label>
              <input type="text" class="form-control" id="newPatientName" placeholder="الاسم الكامل">
            </div>
            <div class="form-group">
              <label>رقم الهاتف <span class="field-optional">(اختياري)</span></label>
              <input type="tel" class="form-control" id="newPhone" placeholder="01xxxxxxxxx — يمكن تركه فارغاً" maxlength="11">
            </div>
            <div class="form-group">
              <label>الرقم القومي</label>
              <input type="text" class="form-control" id="newNationalId" placeholder="14 رقم" maxlength="20">
            </div>
            <div class="form-group">
              <label>تصنيف المريض <span style="color:red">*</span></label>
              <select class="form-control" id="newPatientType">
                <option value="civilian">🌐 مدني</option>
                <option value="military">🪖 عسكري</option>
              </select>
            </div>
            <div class="form-group" id="grpRank" style="display:none;">
              <label>الرتبة العسكرية <span style="color:red">*</span></label>
              <select class="form-control" id="newRankId">
                <option value="">— اختر الرتبة —</option>
              </select>
            </div>
            <div class="form-group" id="grpCompany">
              <label>جهة التعاقد <span id="companyRequired" style="color:red">*</span></label>
              <select class="form-control" id="newCompanyId">
                <option value="">— اختر الجهة —</option>
              </select>
            </div>
          </div>
          <div id="patientFormError" style="color:#dc2626;font-size:13px;margin:0 0 8px;display:none;"></div>
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

    <!-- Appointments Tab -->
    <div class="tab-content active" id="tab-appointments">
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
            <table data-paginate="10">
              <thead>
                <tr>
                  <th>الوقت</th>
                  <th>اسم المريض</th>
                  <th>نوع الزيارة</th>
                  <th>رقم الهاتف</th>
                  <th>جهة التعاقد / الرتبة</th>
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

    <!-- OCR Tab -->
    <div class="tab-content" id="tab-ocr">
      <div class="panel">
        <div class="panel-header">
          <h3>📄 رفع خطاب الموافقة المالية</h3>
        </div>
        <div class="panel-body" style="padding:24px;">
          <div class="qr-scan-banner">
            <div>
              <strong>📱 مسار المريض بدون موافقة مسبقة</strong>
              <p>عند عودة المريض بعرض السعر المطبوع، امسح رمز QR المطبوع على الورقة لاسترجاع الطلب الأصلي والسعر المثبت فوراً</p>
            </div>
            <button type="button" class="btn btn-primary" id="btnScanQR">📱 مسح QR Code</button>
          </div>

          <div class="upload-zone" id="uploadZone">
            <div class="upload-icon">📤</div>
            <p><strong>اسحب صورة خطاب الموافقة هنا</strong> أو انقر للاختيار</p>
            <p class="hint">يدعم جميع أنواع الصور و PDF — قراءة تلقائية للنص العربي</p>
            <input type="file" id="fileInput" accept="image/*,.pdf" style="display:none;">
          </div>

          <div class="ocr-loading" id="ocrLoading">
            <div class="spinner"></div>
            <p>جاري القراءة الضوئية المحلية (OCR)...</p>
            <p style="font-size:12px;color:var(--text-muted);margin-top:8px;">استخراج: الاسم، القيمة المالية، جهة التعاقد</p>
          </div>

          <div class="ocr-results" id="ocrResults">
            <h4>✅ نتائج القراءة الضوئية التلقائية</h4>
            <div class="ocr-field">
              <span class="label">اسم المريض</span>
              <span class="value" id="ocrName">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">القيمة المالية المعتمدة</span>
              <span class="value" id="ocrAmount">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">جهة التعاقد</span>
              <span class="value" id="ocrCompany">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">رقم خطاب الموافقة</span>
              <span class="value" id="ocrRef">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">تاريخ الخطاب</span>
              <span class="value" id="ocrDate">—</span>
            </div>
          </div>

          <div class="form-row" id="ocrForm" style="display:none;margin-top:20px;">
            <div class="form-group" style="padding:0;">
              <label>تأكيد اسم المريض</label>
              <input type="text" class="form-control" id="confirmName" readonly>
            </div>
            <div class="form-group" style="padding:0;">
              <label>تأكيد القيمة (ج.م)</label>
              <input type="text" class="form-control" id="confirmAmount" readonly>
            </div>
          </div>

          <div class="form-actions" id="ocrActions" style="display:none;">
            <button class="btn btn-primary" id="btnBypass">
              ⚡ تأكيد وتخطي المسار — إرسال للمخزن
            </button>
            <button class="btn btn-secondary" id="btnResetOcr">إعادة الرفع</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Patients Tab -->
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
          <table data-paginate="10">
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

    <!-- Quote Tab -->
    <div class="tab-content" id="tab-quote">
      <div id="analytics-quote">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🧾', 'label' => 'عروض', 'value' => '0', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '💰', 'label' => 'إجمالي', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '✅', 'label' => 'معتمد', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'بانتظار', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>🧾 عروض الأسعار</h3>
          <span class="badge" id="quoteListCount">0</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="quoteSearch" placeholder="🔍 بحث برقم العرض أو اسم المريض...">
          <span class="toolbar-count" id="quoteFilterCount">0 عروض</span>
          <button type="button" class="btn btn-secondary" id="btnSimulateReturn" style="padding:8px 16px;font-size:12px;white-space:nowrap;">📱 محاكاة عودة المريض (QR)</button>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>رمز QR</th>
                <th>المريض</th>
                <th>جهة التعاقد</th>
                <th>التاريخ</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="quotesTable"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Delivery Tab -->
    <div class="tab-content" id="tab-delivery">
      <div class="panel">
        <div class="panel-header">
          <h3>✅ تسليم الطرف للمريض</h3>
          <span class="badge" id="deliveryReadyCount">0</span>
        </div>
        <p class="cases-panel-hint" style="display:block;padding:0 24px 12px;margin:0;">
          تظهر هنا الحالات التي اكتمل تصنيعها (BOM «تام») وجاهزة للتسليم — بعد تأكيد الاستلام تُغلق الحالة «تم التسليم».
        </p>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>المريض</th>
                <th>جهة التعاقد</th>
                <th>أمر التشغيل</th>
                <th>مرحلة BOM</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="deliveryTable"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Self-Service Tab -->
    <div class="tab-content" id="tab-selfservice">
      <div class="panel">
        <div class="panel-header">
          <h3>📱 متابعة حالة الطلب (خدمة ذاتية)</h3>
        </div>
        <div class="panel-body" style="padding:24px;">
          <div class="qr-scan-banner">
            <div>
              <strong>امسح بطاقة المريض أو أدخل Patient ID</strong>
              <p>يستطيع المريض معرفة حالة طلبه، ترتيبه في الطابور، والموعد المتوقع للتسليم — كما في شاشات الخدمة الذاتية.</p>
            </div>
          </div>
          <div class="search-bar" style="margin-top:16px;">
            <input type="text" id="ssInput" placeholder="🔍 رقم المريض (6 أرقام) أو الاسم">
            <button class="btn btn-primary" id="btnSelfService">استعلام</button>
          </div>
          <div id="ssResult"></div>
        </div>
      </div>
    </div>
  </main>

  <!-- QR Scan Modal -->
  <div class="modal-overlay" id="qrModal">
    <div class="modal">
      <div class="modal-header">
        <h3>📱 مسح QR Code</h3>
        <button class="modal-close" id="closeQrModal">&times;</button>
      </div>
      <div class="modal-body" style="text-align:center;">
        <div class="scan-animation">
          <div class="qr-mini"></div>
          <div class="scan-line"></div>
        </div>
        <p id="scanStatus">جاري المسح...</p>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px;" id="scanQuoteHint">—</p>
      </div>
    </div>
  </div>

  <!-- Quote Modal -->
  <div class="modal-overlay" id="quoteModal">
    <div class="modal quote-modal">
      <div class="modal-header">
        <h3 id="quoteModalTitle">🧾 عرض السعر</h3>
        <button class="modal-close" id="closeQuoteModal">&times;</button>
      </div>
      <div class="modal-body quote-modal-body">
        <div class="quote-document" id="quoteModalBody"></div>
      </div>
      <div class="modal-footer quote-modal-footer">
        <button class="btn btn-secondary" id="btnCloseQuoteModal">إغلاق</button>
        <button type="button" class="btn btn-primary" id="btnPrintQuoteModal">🖨️ طباعة عرض السعر</button>
      </div>
    </div>
  </div>

  <!-- Patient File Modal -->
  <div class="modal-overlay" id="patientFileModal">
    <div class="modal modal-wide">
      <div class="modal-header">
        <h3 id="patientFileTitle">👤 ملف المريض</h3>
        <button class="modal-close" id="closePatientFileModal">&times;</button>
      </div>
      <div class="modal-body">
        <div style="margin-bottom:16px;" id="patientFileStatus"></div>
        <div class="patient-file-meta" id="patientFileMeta"></div>
        <div class="patient-file-section">
          <h4>📋 آخر الزيارات</h4>
          <table data-paginate="10" class="patient-visits-table">
            <thead>
              <tr>
                <th>التاريخ</th>
                <th>الإجراء</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="patientFileVisits"></tbody>
          </table>
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
          <button class="btn btn-secondary" id="btnClosePatientFile">إغلاق</button>
          <button class="btn btn-primary" id="btnPrintPatientFile" onclick="window.print()">🖨️ طباعة الملف</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Patient QR Card Modal -->
  <div class="modal-overlay" id="patientCardModal">
    <div class="modal">
      <div class="modal-header">
        <h3>🆔 بطاقة المريض الرقمية</h3>
        <button class="modal-close" id="closePatientCardModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="patient-id-card" id="patientIdCard">
          <div class="pic-head">
            <span class="pic-logo">🦿 مركز الأطراف الصناعية</span>
            <span class="pic-type" id="picType">🌐 مدني</span>
          </div>
          <div class="pic-body">
            <div class="pic-info">
              <div class="pic-name" id="picName">—</div>
              <div class="pic-id">رقم المريض: <span id="picId">—</span></div>
              <div class="pic-queue" id="picQueueWrap">رقم الدور: <span id="picQueue">—</span></div>
              <div class="pic-company" id="picCompany">—</div>
              <div class="pic-rank" id="picRank" style="display:none;"></div>
            </div>
            <div class="pic-qr">
              <div class="pic-qr-image" id="picQr"></div>
            </div>
          </div>
          <div class="pic-foot">امسح الكود لمتابعة حالة الطلب وموعد التسليم</div>
        </div>
        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;">
          <button class="btn btn-secondary" id="btnClosePatientCard">إغلاق</button>
          <button class="btn btn-primary" id="btnPrintPatientCard" type="button">🖨️ طباعة البطاقة</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>