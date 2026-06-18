<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">⚙️</div>
      <h2>لوحة إدارة النظام</h2>
      <span>المالك والرقابة</span>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="overview"><span class="nav-icon">📊</span> نظرة عامة</a></li>
      <li><a href="#" data-section="bi"><span class="nav-icon">📡</span> لوحات القيادة (BI)</a></li>
      <li><a href="#" data-section="catalog"><span class="nav-icon">📦</span> الأصناف والأسعار</a></li>
      <li><a href="#" data-section="pricing"><span class="nav-icon">✅</span> اعتماد التسعير</a></li>
      <li><a href="#" data-section="cases"><span class="nav-icon">📁</span> متابعة الحالات</a></li>
      <li><a href="#" data-section="employees"><span class="nav-icon">👥</span> الموظفون</a></li>
      <li><a href="#" data-section="companies"><span class="nav-icon">🏢</span> شركات التعاقد</a></li>
      <li><a href="#" data-section="debts"><span class="nav-icon">💰</span> المديونيات</a></li>
      <li><a href="#" data-section="audit"><span class="nav-icon">🔒</span> سجل الرقابة</a></li>
      <li><a href="#" data-section="reports"><span class="nav-icon">📈</span> التقارير</a></li>
      <li><a href="#" data-section="suppliers"><span class="nav-icon">🏭</span> الموردون</a></li>
    </ul>
    <div class="sidebar-footer">
      <a href="{{ route('home') }}" class="btn-back">← العودة للصفحة الرئيسية</a>
    </div>
  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">لوحة المعلومات — الإدارة العليا</h1>
        <p>مرحباً، المدير العام — آخر تحديث: اليوم 08/06/2026</p>
      </div>
      <div class="user-chip">
        <div class="avatar">م</div>
        <span>مدير النظام</span>
      </div>
    </div>

    <!-- Overview Section -->
    <div class="section-view active" id="section-overview">
    <div id="analytics-overview"></div>

    <div class="overview-cases-strip" id="overviewCasesStrip">
      <button type="button" class="overview-case-link" data-goto-cases="waiting_return">
        <strong>⏳ بانتظار رجوع العميل</strong>
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
          <h3>👥 إدارة الموظفين والصلاحيات</h3>
          <span class="badge">٢٨ موظف</span>
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
            <tbody id="employeesTable">
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel" id="debts">
        <div class="panel-header">
          <h3>💰 مديونيات شركات التعاقد</h3>
          <span class="badge">٦ جهات</span>
        </div>
        <div class="panel-body">
          <table>
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
      <div id="biContent"></div>
    </div>

    <!-- Catalog Section -->
    <div class="section-view" id="section-catalog">
      <div id="analytics-catalog"></div>
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
          <table>
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

    <!-- Pricing Approval Section -->
    <div class="section-view" id="section-pricing">
      <div id="analytics-pricing"></div>
      <div class="panel">
        <div class="panel-header">
          <h3>✅ اعتماد طلبات التسعير</h3>
          <span class="badge" id="pricingApprovalBadge">0</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="pricingApprovalSearch" placeholder="🔍 بحث برقم الطلب أو اسم المريض...">
          <select id="pricingApprovalFilter">
            <option value="pending">في انتظار موافقة الأدمن</option>
            <option value="sent">معتمد — جاهز لعرض السعر</option>
            <option value="all">الكل</option>
          </select>
          <span class="toolbar-count" id="pricingApprovalCount">0 طلب</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportPricingApproval('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportPricingApproval('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>رقم الطلب</th>
                <th>المريض</th>
                <th>التاريخ</th>
                <th>البنود</th>
                <th>التقدير (Highest Batch)</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="pricingApprovalTable"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Cases Tracking Section -->
    <div class="section-view" id="section-cases">
      <div id="analytics-cases"></div>
      <div class="cases-quick-grid" id="casesQuickGrid">
        <button type="button" class="cases-quick-btn waiting active" data-cases-filter="waiting_return">
          <span class="cq-icon">⏳</span>
          <span class="cq-title">بانتظار رجوع العميل</span>
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
          <h3 id="casesPanelTitle">📁 الحالات — بانتظار رجوع العميل</h3>
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
          <table>
            <thead id="casesTableHead"></thead>
            <tbody id="casesTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Employees Section -->
    <div class="section-view" id="section-employees">
      <div id="analytics-employees"></div>
      <div class="panel">
        <div class="panel-header">
          <h3>👥 إدارة الموظفين والصلاحيات</h3>
          <span class="badge">٢٨ موظف</span>
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
          <span class="toolbar-count" id="empCount">6 موظف</span>
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

    <!-- Companies Section -->
    <div class="section-view" id="section-companies">
      <div id="analytics-companies"></div>
      <div class="panel">
        <div class="panel-header">
          <h3>🏢 شركات التعاقد</h3>
          <span class="badge" id="companiesBadge">0 شركة</span>
        </div>
        <div class="company-add-bar">
          <input type="text" id="companyNameInput" placeholder="اسم الشركة / جهة التعاقد..." autocomplete="off">
          <button type="button" class="btn-add-company" id="btnAddCompany">➕ إضافة شركة</button>
          <p class="company-hint">أضف اسم جهة التعاقد فقط — تُستخدم في قسم المديونيات والتقارير</p>
        </div>
        <div class="data-toolbar">
          <input type="text" id="companySearch" placeholder="🔍 بحث باسم الشركة...">
          <span class="toolbar-count" id="companiesCount">0 شركة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportCompanies('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportCompanies('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th style="width:48px">#</th>
                <th>اسم الشركة</th>
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
      <div id="analytics-debts"></div>
      <div class="panel">
        <div class="panel-header">
          <h3>💰 مديونيات شركات التعاقد</h3>
          <span class="badge">٦ جهات</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="debtSearch" placeholder="🔍 بحث بجهة التعاقد...">
          <select id="debtStatusFilter">
            <option value="paid">مسدد</option>
          </select>
          <span class="toolbar-count" id="debtCount">6 جهات</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportDebts('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportDebts('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
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
          <table>
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
      <div id="analytics-audit"></div>
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
          <span class="toolbar-count" id="auditCount">8 حركات</span>
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
      <div class="report-cards">
        <div class="report-card">
          <h4>📈 الإيرادات الشهرية</h4>
          <div class="report-bar"><span>يناير</span><div class="bar-track"><div class="bar-fill" style="width:72%"></div></div><span>1,800 ألف ج.م</span></div>
          <div class="report-bar"><span>فبراير</span><div class="bar-track"><div class="bar-fill" style="width:85%"></div></div><span>2,100 ألف ج.م</span></div>
          <div class="report-bar"><span>مارس</span><div class="bar-track"><div class="bar-fill" style="width:68%"></div></div><span>1,700 ألف ج.م</span></div>
          <div class="report-bar"><span>أبريل</span><div class="bar-track"><div class="bar-fill" style="width:90%"></div></div><span>2,200 ألف ج.م</span></div>
          <div class="report-bar"><span>مايو</span><div class="bar-track"><div class="bar-fill" style="width:78%"></div></div><span>1,900 ألف ج.م</span></div>
          <div class="report-bar"><span>يونيو</span><div class="bar-track"><div class="bar-fill" style="width:95%"></div></div><span>2,500 ألف ج.م</span></div>
        </div>
        <div class="report-card">
          <h4>🔥 الأصناف الأكثر طلباً</h4>
          <div class="report-bar"><span>ركبة هيدروليكية</span><div class="bar-track"><div class="bar-fill" style="width:92%;background:#059669"></div></div><span>48</span></div>
          <div class="report-bar"><span>قدم Carbon Spring</span><div class="bar-track"><div class="bar-fill" style="width:78%;background:#059669"></div></div><span>41</span></div>
          <div class="report-bar"><span>بطانة Silicone</span><div class="bar-track"><div class="bar-fill" style="width:65%;background:#059669"></div></div><span>35</span></div>
          <div class="report-bar"><span>محول Pyramidal</span><div class="bar-track"><div class="bar-fill" style="width:55%;background:#059669"></div></div><span>29</span></div>
        </div>
        <div class="report-card">
          <h4>📋 أوامر التشغيل — هذا الشهر</h4>
          <div class="metric-grid">
            <div class="metric-box"><div class="mv">47</div><div class="ml">أوامر مكتملة</div></div>
            <div class="metric-box"><div class="mv">12</div><div class="ml">قيد التصنيع</div></div>
            <div class="metric-box"><div class="mv">8</div><div class="ml">بانتظار خامات</div></div>
            <div class="metric-box"><div class="mv">94%</div><div class="ml">نسبة الإنجاز</div></div>
          </div>
          <div class="card-footer">متوسط زمن التسليم: 14 يوم عمل</div>
        </div>
      </div>

      <div class="reports-section-title">📦 تقارير المخزون والتحليلات الذكية</div>
      <div class="report-cards">
        <div class="report-card wide">
          <h4>💚 صحة المخزون الإجمالية</h4>
          <div class="health-score-wrap">
            <div class="health-ring" style="background:conic-gradient(#059669 0 282deg,#e2e8f0 282deg 360deg)">
              <div class="health-ring-inner">78<span>/100</span></div>
            </div>
            <div class="health-factors">
              <div class="health-factor"><span>توفر الأصناف الحرجة</span><span class="val warn">71%</span></div>
              <div class="health-factor"><span>معدل دوران المخزون</span><span class="val good">4.2×/سنة</span></div>
              <div class="health-factor"><span>نسبة الركود (&gt;60 يوم)</span><span class="val bad">12%</span></div>
              <div class="health-factor"><span>دقة آخر جرد فعلي</span><span class="val good">98.5%</span></div>
            </div>
          </div>
          <div class="card-footer">آخر تحديث: 08/06/2026 08:10 — أمين المخزن: خالد عمر</div>
        </div>

        <div class="report-card">
          <h4>⚠️ الأصناف الراكدة (17 صنف)</h4>
          <div class="stagnant-item"><span>مفصل كوع ميكانيكي — Large</span><span style="color:var(--danger);font-weight:700;">120 يوم</span></div>
          <div class="stagnant-item"><span>غطاء تجميلي — Wide</span><span style="color:var(--danger);font-weight:700;">95 يوم</span></div>
          <div class="stagnant-item"><span>Pin Lock — 30mm</span><span style="color:var(--warning);font-weight:700;">78 يوم</span></div>
          <div class="stagnant-item"><span>بطانة Gel — Medium</span><span style="color:var(--warning);font-weight:700;">65 يوم</span></div>
          <div class="stagnant-item"><span>SACH Foot — Size 26</span><span style="color:var(--warning);font-weight:700;">52 يوم</span></div>
          <div class="card-footer">⚡ تجميد سيولة تقديري: ~185,000 ج.م</div>
        </div>

        <div class="report-card">
          <h4>🔴 تحت الحد الأدنى (5 أصناف)</h4>
          <div class="stagnant-item"><span>Pin Lock — 30mm</span><span style="color:var(--danger);font-weight:700;">2 / 15</span></div>
          <div class="stagnant-item"><span>مفصل كوع — Small</span><span style="color:var(--danger);font-weight:700;">1 / 10</span></div>
          <div class="stagnant-item"><span>ركبة Polycentric — Large</span><span style="color:var(--warning);font-weight:700;">3 / 15</span></div>
          <div class="stagnant-item"><span>Dynamic Response Foot</span><span style="color:var(--warning);font-weight:700;">4 / 12</span></div>
          <div class="stagnant-item"><span>محول Rotator — 30mm</span><span style="color:var(--warning);font-weight:700;">2 / 10</span></div>
          <div class="card-footer">🛒 3 أوامر شراء مقترحة للموردين</div>
        </div>

        <div class="report-card">
          <h4>📤 حركات الصرف — يونيو 2026</h4>
          <div class="report-bar"><span>أسبوع 1</span><div class="bar-track"><div class="bar-fill" style="width:70%;background:#0e7490"></div></div><span>34</span></div>
          <div class="report-bar"><span>أسبوع 2</span><div class="bar-track"><div class="bar-fill" style="width:85%;background:#0e7490"></div></div><span>41</span></div>
          <div class="report-bar"><span>أسبوع 3</span><div class="bar-track"><div class="bar-fill" style="width:60%;background:#0e7490"></div></div><span>29</span></div>
          <div class="report-bar"><span>أسبوع 4</span><div class="bar-track"><div class="bar-fill" style="width:92%;background:#0e7490"></div></div><span>48</span></div>
          <div class="card-footer">إجمالي صرف: 152 عملية — 8 معلقة</div>
        </div>

        <div class="report-card">
          <h4>📥 استلام من الموردين — يونيو</h4>
          <div class="report-bar"><span>Ottobock Egypt</span><div class="bar-track"><div class="bar-fill" style="width:88%;background:#7c3aed"></div></div><span>12 صنف</span></div>
          <div class="report-bar"><span>Össur Middle East</span><div class="bar-track"><div class="bar-fill" style="width:65%;background:#7c3aed"></div></div><span>8 أصناف</span></div>
          <div class="report-bar"><span>Proteor France</span><div class="bar-track"><div class="bar-fill" style="width:55%;background:#7c3aed"></div></div><span>6 أصناف</span></div>
          <div class="report-bar"><span>النيل للتوريدات</span><div class="bar-track"><div class="bar-fill" style="width:40%;background:#7c3aed"></div></div><span>4 أصناف</span></div>
          <div class="card-footer">4 فواتير مستلمة — 2 بانتظار التسكين</div>
        </div>

        <div class="report-card">
          <h4>🏷️ الدفعات النشطة (Batch Tracking)</h4>
          <div class="batch-item"><span>ركبة هيدروليكية — Ottobock</span><span class="batch-tag">دفعة #B-042 · 8 وحدات</span></div>
          <div class="batch-item"><span>قدم Carbon Spring — Össur</span><span class="batch-tag">دفعة #B-038 · 12 وحدة</span></div>
          <div class="batch-item"><span>بطانة Silicone — محلي</span><span class="batch-tag">دفعة #B-051 · 24 وحدة</span></div>
          <div class="batch-item"><span>Pin Lock — Proteor</span><span class="batch-tag">دفعة #B-029 · 2 وحدة ⚠</span></div>
          <div class="card-footer">⚡ Highest Batch Cost Logic — 47 دفعة نشطة</div>
        </div>

        <div class="report-card wide" id="bomAdminPanel">
          <h4>📋 BOM — خام / تحت التشغيل / تام (قيمة Highest Batch Cost)</h4>
          <div id="bomAdminSummary" class="bom-admin-summary"></div>
          <div class="bom-admin-table-wrap">
            <table class="data-table bom-admin-table">
              <thead>
                <tr>
                  <th>المريض</th>
                  <th>أمر التشغيل</th>
                  <th>المرحلة</th>
                  <th>البنود</th>
                  <th>قيمة BOM</th>
                </tr>
              </thead>
              <tbody id="bomAdminTable"></tbody>
            </table>
          </div>
          <div class="card-footer" id="bomAdminFooter">—</div>
        </div>

        <div class="report-card">
          <h4>⏳ أوامر تحضير معلقة</h4>
          <div class="dispense-item"><span>#WO-2026-0312 — محمود عبد الرحمن</span><span style="color:var(--warning);font-weight:700;">بانتظار صرف</span></div>
          <div class="dispense-item"><span>#WO-2026-0308 — فاطمة حسين</span><span style="color:var(--warning);font-weight:700;">ناقص Pin Lock</span></div>
          <div class="dispense-item"><span>#WO-2026-0305 — عبدالله سامي</span><span style="color:var(--primary);font-weight:700;">جاري التحضير</span></div>
          <div class="dispense-item"><span>#WO-2026-0301 — مريم خالد</span><span style="color:var(--primary);font-weight:700;">جاري التحضير</span></div>
          <div class="card-footer">4 أوامر — 2 تحتاج تدخل فوري</div>
        </div>

        <div class="report-card">
          <h4>📊 توزيع المخزون حسب الفئة</h4>
          <div class="report-bar"><span>مفاصل وأركام</span><div class="bar-track"><div class="bar-fill" style="width:38%;background:#d97706"></div></div><span>38%</span></div>
          <div class="report-bar"><span>أقدام صناعية</span><div class="bar-track"><div class="bar-fill" style="width:24%;background:#d97706"></div></div><span>24%</span></div>
          <div class="report-bar"><span>بطانات ومستلزمات</span><div class="bar-track"><div class="bar-fill" style="width:22%;background:#d97706"></div></div><span>22%</span></div>
          <div class="report-bar"><span>محولات وعدد</span><div class="bar-track"><div class="bar-fill" style="width:16%;background:#d97706"></div></div><span>16%</span></div>
          <div c  lass="card-footer">142 صنف مسجل — 1,840 وحدة إجمالي</div>
        </div>

        <div class="report-card">
          <h4>🔮 تنبؤ النقص — 30 يوم</h4>
          <div class="stagnant-item"><span>ركبة هيدروليكية</span><span style="color:var(--danger);font-weight:700;">نفاد متوقع 18/06</span></div>
          <div class="stagnant-item"><span>Pin Lock — 30mm</span><span style="color:var(--danger);font-weight:700;">نفاد متوقع 12/06</span></div>
          <div class="stagnant-item"><span>بطانة Gel — Medium</span><span style="color:var(--warning);font-weight:700;">نفاد متوقع 25/06</span></div>
          <div class="stagnant-item"><span>قدم Carbon Spring</span><span style="color:var(--warning);font-weight:700;">نفاد متوقع 02/07</span></div>
          <div class="card-footer">بناءً على معدل الطلب + أوامر التشغيل الجارية</div>
        </div>

        <div class="report-card">
          <h4>📋 نتائج آخر جرد فعلي</h4>
          <div class="metric-grid">
            <div class="metric-box"><div class="mv" style="color:#047857">98.5%</div><div class="ml">دقة الجرد</div></div>
            <div class="metric-box"><div class="mv" style="color:#b45309">7</div><div class="ml">فروقات</div></div>
            <div class="metric-box"><div class="mv">142</div><div class="ml">صنف مجرود</div></div>
            <div class="metric-box"><div class="mv" style="color:#b91c1c">3</div><div class="ml">تالف/نقص</div></div>
          </div>
          <div class="card-footer">تاريخ الجرد: 01/06/2026 — أمين المخزن: خالد عمر</div>
        </div>

        <div class="report-card">
          <h4>🔄 معدل دوران المخزون</h4>
          <div class="report-bar"><span>مفاصل</span><div class="bar-track"><div class="bar-fill" style="width:80%;background:#059669"></div></div><span>5.1×</span></div>
          <div class="report-bar"><span>أقدام</span><div class="bar-track"><div class="bar-fill" style="width:72%;background:#059669"></div></div><span>4.6×</span></div>
          <div class="report-bar"><span>بطانات</span><div class="bar-track"><div class="bar-fill" style="width:90%;background:#059669"></div></div><span>6.2×</span></div>
          <div class="report-bar"><span>إكسسوارات</span><div class="bar-track"><div class="bar-fill" style="width:35%;background:#dc2626"></div></div><span>1.8×</span></div>
          <div class="card-footer">المعدل العام: 4.2× — الهدف: ≥ 4.0×</div>
        </div>
      </div>
    </div>

    <!-- Suppliers Section -->
    <div class="section-view" id="section-suppliers">
      <div id="analytics-suppliers"></div>
      <div class="panel">
        <div class="panel-header">
          <h3>🏭 الموردون وفواتير المشتريات</h3>
          <span class="badge">8 موردين</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="supplierSearch" placeholder="🔍 بحث بالمورد أو التخصص...">
          <select id="supplierStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="paid">مسددة</option>
            <option value="partial">جزئية</option>
            <option value="pending">معلقة</option>
          </select>
          <span class="toolbar-count" id="supplierCount">8 موردين</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportSuppliers('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportSuppliers('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
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

  <!-- اعتماد التسعير — تفاصيل -->
  <div class="catalog-modal-overlay" id="pricingApprovalModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" onclick="event.stopPropagation()">
      <div class="catalog-modal-header">
        <div>
          <h3 id="pricingApprovalModalTitle">🧾 تفاصيل طلب التسعير</h3>
          <div class="modal-code" id="pricingApprovalModalRef"></div>
        </div>
        <button type="button" class="catalog-modal-close" id="closePricingApprovalModal">&times;</button>
      </div>
      <div class="catalog-modal-body">
        <div class="catalog-detail-grid" id="pricingApprovalModalMeta"></div>
        <h4 style="font-size:14px;font-weight:800;margin:16px 0 10px;color:var(--secondary);">📦 البنود والأسعار</h4>
        <table>
          <thead>
            <tr>
              <th>الصنف</th>
              <th>الكود</th>
              <th>الكمية</th>
              <th>أعلى سعر دفعة</th>
              <th>الإجمالي</th>
            </tr>
          </thead>
          <tbody id="pricingApprovalModalItems"></tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="text-align:left;font-weight:700;">الإجمالي التقديري</td>
              <td id="pricingApprovalModalTotal" style="font-weight:800;color:var(--primary-dark);"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="catalog-modal-footer">
        <button type="button" class="btn-action" id="btnClosePricingApprovalModal">إغلاق</button>
        <button type="button" class="btn-action approve" id="btnApprovePricingModal" style="display:none;">✅ موافقة الأدمن — إرسال للاستقبال</button>
      </div>
    </div>
  </div>

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