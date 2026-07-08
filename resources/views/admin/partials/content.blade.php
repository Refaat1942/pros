<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">⚙️</div>
      <h2>لوحة إدارة النظام</h2>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="overview"><span class="nav-icon">📊</span> نظرة عامة</a></li>
      <li><a href="#" data-section="bi"><span class="nav-icon">📡</span> لوحات القيادة (BI)</a></li>
      <li><a href="#" data-section="catalog"><span class="nav-icon">📦</span> الأصناف والأسعار</a></li>
      <li><a href="#" data-section="cases"><span class="nav-icon">📁</span> متابعة الحالات</a></li>
      <li><a href="#" data-section="employees"><span class="nav-icon">👥</span> الموظفون</a></li>
      <li><a href="#" data-section="companies"><span class="nav-icon">🏢</span> جهات التعاقد</a></li>
      <li><a href="#" data-section="debts"><span class="nav-icon">💰</span> المديونيات</a></li>
      <li><a href="#" data-section="audit"><span class="nav-icon">🔒</span> سجل الرقابة</a></li>
      <li><a href="#" data-section="reports"><span class="nav-icon">📈</span> التقارير</a></li>
      <li><a href="#" data-section="suppliers"><span class="nav-icon">🏭</span> الموردون</a></li>
      <li><a href="#" data-section="military-ranks"><span class="nav-icon">🪖</span> الرتب العسكرية</a></li>
    </ul>

  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">لوحة المعلومات — الإدارة العليا</h1>
        <p></p>
      </div>
      <div class="user-chip">
        <div class="avatar">م</div>
        <span>مدير النظام</span>
      </div>
    </div>

    <!-- Overview Section -->
    <div class="section-view active" id="section-overview">
    <div id="analytics-overview">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '💵', 'label' => 'إيرادات', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '👤', 'label' => 'مرضى', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ['icon' => '📦', 'label' => 'صحة المخزون', 'value' => '0%', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ['icon' => '💰', 'label' => 'مديونيات', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
    ]])</div>

    <div class="overview-cases-strip" id="overviewCasesStrip">
      <button type="button" class="overview-case-link" data-goto-cases="waiting_return">
        <strong>⏳ بانتظار موافقة الجهة</strong>
        <span id="overviewWaitingCount" style="color:#d97706">0</span>
      </button>
      <button type="button" class="overview-case-link" data-goto-cases="in_progress">
        <strong>🏭 تحت التنفيذ</strong>
        <span id="overviewProgressCount" style="color:#0e7490">0</span>
      </button>
      <button type="button" class="overview-case-link" data-goto-cases="delivered">
        <strong>✅ تم التسليم</strong>
        <span id="overviewDeliveredCount" style="color:#059669">0</span>
      </button>
    </div>

    <div class="panels-grid">
      <div class="panel" id="employees">
        <div class="panel-header">
          <h3>👥 إدارة الموظفين</h3>
          <span class="badge" id="employeesOverviewBadge">0 موظف</span>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>الاسم</th>
                <th>الدور</th>
                <th>الحالة</th>
                <th>آخر دخول</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="employeesTable">
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel" id="debts">
        <div class="panel-header">
          <h3>💰 مديونيات جهات التعاقد</h3>
          <span class="badge" id="debtsOverviewBadge">0 جهة</span>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>جهة التعاقد</th>
                <th>المستحق</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="debtsTable">
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="panel audit-panel">
      <div class="panel-header">
        <h3>🔒 آخر حركات — سجل الرقابة</h3>
        <span class="badge">آخر ٥</span>
      </div>
      <div class="panel-body" id="auditPreview"></div>
    </div>
    </div>

    <!-- BI Command Boards Section -->
    <div class="section-view" id="section-bi">
      <div class="panel" style="margin-bottom:16px;">
        <div class="panel-header">
          <h3>📡 لوحات القيادة وذكاء الأعمال — 5 لوحات لحظية</h3>
        </div>
        <p style="padding:0 20px 14px;margin:0;color:var(--text-muted);font-size:13px;">
          مؤشرات إستراتيجية لحظية: توزيع مدني/عسكري، زمن التنفيذ (SLA)، قيمة المخزون (WAC)، أوامر التشغيل، تكاليف الجهات، ومقارنة WAC ↔ أعلى سعر.
        </p>
      </div>
      <div id="biContent">@include('partials.dashboard-bi-empty')</div>
    </div>

    <!-- Catalog Section -->
    <div class="section-view" id="section-catalog">
      <div id="analytics-catalog">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📦', 'label' => 'أصناف', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'أسعار مسجلة', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '🏷️', 'label' => 'متعدد الأسعار', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '📊', 'label' => 'فئات', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>📦 الأصناف والأسعار</h3>
          <span class="badge" id="catalogCount">0 صنف</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="catalogSearch" placeholder="🔍 بحث بالصنف أو الكود...">
          <select id="catalogCategoryFilter">
            <option value="all">كل الفئات</option>
            <option value="مفاصل">مفاصل</option>
            <option value="أقدام">أقدام</option>
            <option value="بطانات">بطانات</option>
            <option value="محولات">محولات</option>
            <option value="إكسسوارات">إكسسوارات</option>
          </select>
          <button type="button" class="btn-action" id="btnToggleCatalogForm" style="background:var(--primary);color:white;border:none;padding:9px 16px;border-radius:8px;cursor:pointer;font-family:'Tajawal',sans-serif;font-weight:600;">➕ إضافة صنف</button>
          <span class="toolbar-count" id="catalogFilteredCount">0 صنف</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportCatalog('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportCatalog('pdf')">📄 PDF</button>
          </div>
        </div>

        <div class="catalog-form" id="catalogForm">
          <input type="hidden" id="catalogEditCode" value="">
          <div class="catalog-form-grid">
            <div>
              <label>اسم الصنف *</label>
              <input type="text" id="catalogName" placeholder="مثال: ركبة هيدروليكية">
            </div>
            <div>
              <label>المواصفات</label>
              <input type="text" id="catalogSpec" placeholder="Medium — Ottobock">
            </div>
            <div>
              <label>الفئة *</label>
              <select id="catalogCategory">
                <option value="مفاصل">مفاصل</option>
                <option value="أقدام">أقدام</option>
                <option value="بطانات">بطانات</option>
                <option value="محولات">محولات</option>
                <option value="إكسسوارات">إكسسوارات</option>
              </select>
            </div>
            <div>
              <label>الكمية الابتدائية</label>
              <input type="number" id="catalogQty" min="0" value="0">
            </div>
            <div style="display:flex;align-items:center;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                <input type="checkbox" id="catalogIsQuickDispense" style="width:auto;">
                <span>صنف صرف سريع (ربح مباشر 40%)</span>
              </label>
            </div>
          </div>
          <div class="prices-block">
            <h4>💰 أسعار الصنف (متعددة — أكواد / موردين)</h4>
            <div id="itemPricesList"></div>
            <button type="button" class="btn-add-price" id="btnAddPriceRow">+ إضافة سعر</button>
          </div>
          <div class="catalog-form-actions">
            <button type="button" class="btn-action" id="btnCancelCatalog">إلغاء</button>
            <button type="button" class="btn-action" id="btnSaveCatalog" style="background:var(--primary);color:white;border:none;">💾 حفظ الصنف</button>
          </div>
        </div>

        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>الكود</th>
                <th>الصنف</th>
                <th>الفئة</th>
                <th>المواصفات</th>
                <th>الكمية</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="catalogTable"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Cases Tracking Section -->
    <div class="section-view" id="section-cases">
      <div id="analytics-cases">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '⏳', 'label' => 'بانتظار الرجوع', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '🏭', 'label' => 'تحت التنفيذ', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '✅', 'label' => 'تم التسليم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏱', 'label' => 'متوسط انتظار', 'value' => '—', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
      ]])</div>
      <div class="cases-quick-grid" id="casesQuickGrid">
        <button type="button" class="cases-quick-btn waiting active" data-cases-filter="waiting_return">
          <span class="cq-icon">⏳</span>
          <span class="cq-title">بانتظار موافقة الجهة</span>
          <span class="cq-desc">تم إصدار عرض السعر وخرج المريض — لم يعد بعد بخطاب الموافقة</span>
          <span class="cq-count" id="casesWaitingCount">0</span>
        </button>
        <button type="button" class="cases-quick-btn progress" data-cases-filter="in_progress">
          <span class="cq-icon">🏭</span>
          <span class="cq-title">تحت التنفيذ</span>
          <span class="cq-desc">رجع بخطاب الموافقة — جاري التصنيع والصرف</span>
          <span class="cq-count" id="casesProgressCount">0</span>
        </button>
        <button type="button" class="cases-quick-btn delivered" data-cases-filter="delivered">
          <span class="cq-icon">✅</span>
          <span class="cq-title">تم التسليم</span>
          <span class="cq-desc">حالات مكتملة — تقرير مالي (تكلفة / مدفوع / مديونية)</span>
          <span class="cq-count" id="casesDeliveredCount">0</span>
        </button>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h3 id="casesPanelTitle">📁 الحالات — بانتظار موافقة الجهة</h3>
          <span class="badge" id="casesPanelBadge">0</span>
        </div>
        <p class="cases-panel-hint" id="casesPanelHint" style="display:none"></p>
        <div class="data-toolbar">
          <input type="text" id="casesSearch" placeholder="🔍 بحث بالمريض أو رقم عرض السعر...">
          <span class="toolbar-count" id="casesFilterCount">0 حالة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportCases('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportCases('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead id="casesTableHead"></thead>
            <tbody id="casesTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Employees Section -->
    <div class="section-view" id="section-employees">
      <div id="analytics-employees">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '👥', 'label' => 'الموظفون', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '✅', 'label' => 'نشط', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏸️', 'label' => 'غير نشط', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
        ['icon' => '🩺', 'label' => 'أطباء', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>👥 إدارة الموظفين</h3>
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
          <table data-paginate="10">
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

    <!-- Companies Section -->
    <div class="section-view" id="section-companies">
      <div id="analytics-companies">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🏢', 'label' => 'شركات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'لها مديونيات', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '➕', 'label' => 'بدون مديونيات', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
        ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>🏢 جهات التعاقد</h3>
          <span class="badge" id="companiesBadge">0 جهة</span>
        </div>
        <div class="company-add-bar">
          <input type="text" id="companyNameInput" placeholder="اسم الجهة / جهة التعاقد..." autocomplete="off">
          <button type="button" class="btn-add-company" id="btnAddCompany">➕ إضافة جهة</button>
          <p class="company-hint">أضف اسم جهة التعاقد فقط — تُستخدم في قسم المديونيات والتقارير</p>
        </div>
        <div class="data-toolbar">
          <input type="text" id="companySearch" placeholder="🔍 بحث باسم الجهة...">
          <span class="toolbar-count" id="companiesCount">0 جهة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportCompanies('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportCompanies('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th style="width:48px">#</th>
                <th>اسم الجهة</th>
                <th style="width:100px">إجراء</th>
              </tr>
            </thead>
            <tbody id="companiesTable"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Debts Section -->
    <div class="section-view" id="section-debts">
      <div id="analytics-debts">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📋', 'label' => 'جهات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💳', 'label' => 'المستحق', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '✅', 'label' => 'المحصّل', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'المتبقي', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>💰 مديونيات جهات التعاقد</h3>
          <span class="badge" id="debtsSectionBadge">0 جهة</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="debtSearch" placeholder="🔍 بحث بجهة التعاقد...">
          <select id="debtStatusFilter">
            <option value="paid">مسدد</option>
          </select>
          <span class="toolbar-count" id="debtCount">0 جهة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportDebts('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportDebts('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>جهة التعاقد</th>
                <th>المستحق</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="debtsTableFull"></tbody>
          </table>
        </div>
      </div>
      <div class="panel" style="margin-top:20px;">
        <div class="panel-header">
          <h3>📄 إشعارات الدائن (Credit Notes) — مسار مدني بعد التسليم</h3>
          <span class="badge" id="creditNotesBadge">0</span>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
          كامل أو جزئي — يخصم من مديونية جهة التعاقد. يتطلب <strong>موافقة الإدارة</strong>. المسار العسكري: تكلفة سيادية (لا Credit Note).
        </p>
        <div class="data-toolbar">
          <button type="button" class="btn-action" id="btnNewCreditNote">➕ إنشاء إشعار دائن</button>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>رقم CN</th>
                <th>الحالة / المريض</th>
                <th>جهة التعاقد</th>
                <th>النوع</th>
                <th>المبلغ</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="creditNotesTable"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Audit Section -->
    <div class="section-view" id="section-audit">
      <div id="analytics-audit">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📝', 'label' => 'عمليات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '➕', 'label' => 'إنشاء', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '✏️', 'label' => 'تحديث', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '👁️', 'label' => 'عرض', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="immutable-audit-banner">
        ⚠️ <span><strong>سجل تدقيق حصين (Immutable Audit Log):</strong> جداول «للكتابة فقط» (Append-Only). لا يملك أي مستخدم — بما في ذلك مدير الـ IT أو المدير العام — صلاحية تعديل أو حذف أي سطر. يلتقط كل حركة: المستخدم، IP/MAC، الطابع الزمني بالثانية، وقيمة البيانات قبل/بعد.</span>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h3>🔒 سجل الرقابة الكامل — Immutable Audit Log</h3>
          <span class="badge">آخر ٢٤ ساعة</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="auditSearch" placeholder="🔍 بحث بالمستخدم أو الوصف...">
          <select id="auditActionFilter">
            <option value="all">كل العمليات</option>
            <option value="إنشاء">إنشاء</option>
            <option value="تحديث">تحديث</option>
            <option value="تعديل">تعديل</option>
            <option value="عرض">عرض</option>
          </select>
          <span class="toolbar-count" id="auditCount">0 حركة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportAudit('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportAudit('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body" id="auditListFull"></div>
      </div>
    </div>

    <!-- Reports Section -->
    <div class="section-view" id="section-reports">
      <div class="reports-section-title">💰 التقارير المالية والتشغيلية</div>
      <div class="report-cards" id="financialReportCards">
        <div class="report-card"><h4>📈 الإيرادات الشهرية</h4></div>
        <div class="report-card"><h4>🔥 الأصناف الأكثر طلباً</h4></div>
        <div class="report-card"><h4>📋 أوامر التشغيل — هذا الشهر</h4></div>
      </div>

      <div class="reports-section-title">📦 تقارير المخزون والتحليلات الذكية</div>
      <div class="report-cards" id="inventoryReportCards">
        <div class="report-card wide"><h4>💚 صحة المخزون الإجمالية</h4></div>
        <div class="report-card"><h4>⚠️ الأصناف الراكدة</h4></div>
        <div class="report-card"><h4>🔴 تحت الحد الأدنى</h4></div>
        <div class="report-card"><h4>📤 حركات الصرف</h4></div>
        <div class="report-card"><h4>📥 استلام من الموردين</h4></div>
        <div class="report-card"><h4>🏷️ الدفعات النشطة (Batch Tracking)</h4></div>
        <div class="report-card wide" id="bomAdminPanel">
          <h4>📋 BOM — خام / تحت التشغيل / تام (قيمة Highest Batch Cost)</h4>
          <div id="bomAdminSummary" class="bom-admin-summary">
            <div class="bom-admin-stat raw">
              <div class="bas-label">خام</div>
              <div class="bas-value">0 قائمة</div>
              <div class="bas-money">0 ج.م</div>
              <div class="bas-sub">0 بند</div>
            </div>
            <div class="bom-admin-stat wip">
              <div class="bas-label">تحت التشغيل</div>
              <div class="bas-value">0 قائمة</div>
              <div class="bas-money">0 ج.م</div>
              <div class="bas-sub">0 بند</div>
            </div>
            <div class="bom-admin-stat finished">
              <div class="bas-label">تام</div>
              <div class="bas-value">0 قائمة</div>
              <div class="bas-money">0 ج.م</div>
              <div class="bas-sub">0 بند</div>
            </div>
          </div>
          <div class="bom-admin-table-wrap">
            <table data-paginate="10" class="data-table bom-admin-table">
              <thead>
                <tr>
                  <th>المريض</th>
                  <th>أمر التشغيل</th>
                  <th>المرحلة</th>
                  <th>البنود</th>
                  <th>قيمة BOM</th>
                </tr>
              </thead>
              <tbody id="bomAdminTable">
                <tr><td colspan="5" class="empty-cell">لا توجد قوائم BOM</td></tr>
              </tbody>
            </table>
          </div>
          <div class="card-footer" id="bomAdminFooter"></div>
        </div>
        <div class="report-card"><h4>⏳ أوامر تحضير معلقة</h4></div>
      </div>
    </div>

    <!-- Suppliers Section -->
    <div class="section-view" id="section-suppliers">
      <div id="analytics-suppliers">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🏭', 'label' => 'موردون', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'فواتير', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '✅', 'label' => 'مسددة', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'معلقة', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>🏭 الموردون وفواتير المشتريات</h3>
          <span class="badge" id="suppliersSectionBadge">0 مورد</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="supplierSearch" placeholder="🔍 بحث بالمورد أو التخصص...">
          <select id="supplierStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="paid">مسددة</option>
            <option value="partial">جزئية</option>
            <option value="pending">معلقة</option>
          </select>
          <span class="toolbar-count" id="supplierCount">0 مورد</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportSuppliers('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportSuppliers('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>المورد</th>
                <th>التخصص</th>
                <th>آخر فاتورة</th>
                <th>قيمة الفاتورة</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="suppliersTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- تفاصيل الصنف — Popup -->
  <div class="catalog-modal-overlay" id="catalogDetailModal" role="dialog" aria-modal="true" aria-labelledby="catalogModalTitle">
    <div class="catalog-modal" onclick="event.stopPropagation()">
      <div class="catalog-modal-header">
        <div>
          <h3 id="catalogModalTitle">—</h3>
          <div class="modal-code" id="catalogModalCode">—</div>
        </div>
        <button type="button" class="catalog-modal-close" id="catalogModalClose" aria-label="إغلاق">&times;</button>
      </div>
      <div class="catalog-modal-body" id="catalogModalBody"></div>
      <div class="catalog-modal-footer">
        <button type="button" class="btn-action" id="catalogModalEdit">✏️ تعديل الصنف</button>
        <button type="button" class="btn-action" id="catalogModalCloseBtn">إغلاق</button>
      </div>
    </div>
  </div>

  </div>

  @include('admin.pages.military-ranks')

  <!-- Credit Note Modal -->
  <div class="catalog-modal-overlay" id="creditNoteModal">
    <div class="catalog-modal">
      <div class="catalog-modal-header">
        <div>
          <h3>📄 إنشاء إشعار دائن</h3>
        </div>
        <button type="button" class="catalog-modal-close" id="closeCreditNoteModal">&times;</button>
      </div>
      <div class="catalog-modal-body">
        <div style="margin-bottom:14px;"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">حالة مسلّمة (مدني)</label>
          <select id="cnCaseSelect" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;"></select>
        </div>
        <div style="margin-bottom:14px;"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">نوع الإشعار</label>
          <select id="cnType" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            <option value="partial">جزئي</option>
            <option value="full">كامل — إلغاء المطالبة بالكامل</option>
          </select>
        </div>
        <div style="margin-bottom:14px;" id="cnAmountGroup"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">مبلغ الخصم (ج.م)</label>
          <input type="number" id="cnAmount" min="1" value="10000" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
        </div>
        <div style="margin-bottom:14px;"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">السبب</label>
          <input type="text" id="cnReason" placeholder="مثال: رفض جزئي لبند غير مطابق" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
        </div>
        <div id="cnPreview" style="font-size:13px;color:var(--text-muted);margin-top:8px;">—</div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelCreditNote">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmCreditNote">إرسال للموافقة</button>
        </div>
      </div>
    </div>
  </div>