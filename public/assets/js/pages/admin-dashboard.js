    StockCatalog.ensureSeeded();
    CasesWorkflow.ensureSeeded();
    PricingQueue.ensureSeeded();
    BomInventory.ensureSeeded();
    var ADMIN_USER = 'أحمد محمود حسن';
    var pricingApprovalSearch = '';
    var pricingApprovalFilter = 'pending';
    var selectedPricingId = null;
    var casesFilter = 'waiting_return';
    var casesSearchTerm = '';
    var catalogItems = StockCatalog.getAll();
    var catalogSearchTerm = '';
    var catalogCategoryFilter = 'all';
    var editingCatalogCode = null;
    var employees = [
      { name: 'أحمد محمود حسن', role: 'admin', roleLabel: 'إدارة النظام', status: 'active', lastLogin: '08/06/2026 09:15' },
      { name: 'د. سارة عبدالله', role: 'doctor', roleLabel: 'طبيب معالج', status: 'active', lastLogin: '08/06/2026 08:42' },
      { name: 'محمد فتحي إبراهيم', role: 'technical', roleLabel: 'أخصائي فني', status: 'active', lastLogin: '08/06/2026 08:30' },
      { name: 'نورهان علي سالم', role: 'reception', roleLabel: 'موظف استقبال', status: 'active', lastLogin: '08/06/2026 07:55' },
      { name: 'خالد عمر يوسف', role: 'store', roleLabel: 'أمين مخزن', status: 'active', lastLogin: '08/06/2026 08:10' },
      { name: 'د. ياسمين رشدي', role: 'doctor', roleLabel: 'طبيب معالج', status: 'inactive', lastLogin: '05/06/2026 16:20' }
    ];

    var COMPANIES_STORAGE_KEY = 'clinic_contract_companies';
    var companySearchTerm = '';

    var DEFAULT_COMPANY_NAMES = [
      'شركة التأمين الوطني',
      'هيئة التأمين الصحي',
      'مجلس الدفاع المدني',
      'شركة مصر للتأمين',
      'صندوق رعاية ذوي الإعاقة',
      'وزارة الداخلية — التأمين'
    ];

    function loadCompanies() {
      try {
        var raw = localStorage.getItem(COMPANIES_STORAGE_KEY);
        if (raw) {
          var parsed = JSON.parse(raw);
          if (Array.isArray(parsed) && parsed.length) return parsed;
        }
      } catch (e) { /* ignore */ }
      return DEFAULT_COMPANY_NAMES.map(function(name, i) {
        return { id: 'CO-' + String(i + 1).padStart(3, '0'), name: name };
      });
    }

    function saveCompanies(list) {
      localStorage.setItem(COMPANIES_STORAGE_KEY, JSON.stringify(list));
    }

    function ensureCompaniesSeeded() {
      if (!localStorage.getItem(COMPANIES_STORAGE_KEY)) {
        saveCompanies(loadCompanies());
      }
    }

    var contractCompanies = loadCompanies();
    ensureCompaniesSeeded();

    if (typeof CreditNotes !== 'undefined') CreditNotes.ensureSeeded();
    function reloadDebts() {
      return typeof CreditNotes !== 'undefined' ? CreditNotes.getDebts() : debts;
    }
    var debts = reloadDebts();

    var auditLogs = [
      { time: '08/06/2026 09:42:18', user: 'نورهان علي', action: 'إنشاء', desc: 'إنشاء ملف مريض جديد — الرقم القومي: 2980515...', tag: 'patients', ip: '192.168.1.21', mac: 'A4:5E:60:DC:11:02', before: '—', after: 'PT-CIV-0042' },
      { time: '08/06/2026 09:38:05', user: 'د. سارة عبدالله', action: 'تحديث', desc: 'اعتماد التقرير الطبي للمريض: محمود عبد الرحمن', tag: 'medical', ip: '192.168.1.34', mac: 'A4:5E:60:DC:22:7B', before: 'مسودة', after: 'معتمد' },
      { time: '08/06/2026 09:22:41', user: 'محمد فتحي', action: 'إنشاء', desc: 'حفظ التوصيف الفني — طلب #ORD-2026-0847', tag: 'technical', ip: '192.168.1.40', mac: 'A4:5E:60:DC:33:9C', before: '—', after: '3 بنود' },
      { time: '08/06/2026 08:55:33', user: 'خالد عمر', action: 'صرف مخزن', desc: 'تأكيد صرف خامات بالباركود — أمر تشغيل #WO-2026-0312', tag: 'inventory', ip: '192.168.1.55', mac: 'A4:5E:60:DC:44:1D', before: 'خام', after: 'تحت التشغيل' },
      { time: '08/06/2026 08:30:12', user: 'أحمد محمود', action: 'عرض', desc: 'استعراض تقرير الأصناف الراكدة — ١٧ صنف', tag: 'reports', ip: '192.168.1.10', mac: 'A4:5E:60:DC:55:2E', before: '—', after: '—' },
      { time: '08/06/2026 08:15:00', user: 'أحمد محمود', action: 'تعديل', desc: 'تحديث صلاحيات موظف: خالد عمر يوسف', tag: 'users', ip: '192.168.1.10', mac: 'A4:5E:60:DC:55:2E', before: 'مخزن', after: 'مخزن + جرد' },
      { time: '08/06/2026 07:50:22', user: 'نورهان علي', action: 'إنشاء', desc: 'إصدار عرض سعر QT-2026-0847 — 110,500 ج.م', tag: 'finance', ip: '192.168.1.21', mac: 'A4:5E:60:DC:11:02', before: '—', after: '110,500 ج.م' },
      { time: '07/06/2026 16:45:10', user: 'أحمد محمود', action: 'إنشاء', desc: 'تسجيل فاتورة مشتريات — مورد Ottobock', tag: 'suppliers', ip: '192.168.1.10', mac: 'A4:5E:60:DC:55:2E', before: '—', after: '485,000 ج.م' }
    ];

    var suppliers = [
      { name: 'Ottobock Egypt', specialty: 'مفاصل وأطراف صناعية', lastInvoice: '05/06/2026', amount: 485000, status: 'paid' },
      { name: 'Össur Middle East', specialty: 'أطراف carbon وprotheses', lastInvoice: '28/05/2026', amount: 320000, status: 'paid' },
      { name: 'شركة المستقبل الطبي', specialty: 'مستلزمات وbattانات', lastInvoice: '01/06/2026', amount: 95000, status: 'partial' },
      { name: 'Proteor France', specialty: 'مفاصل هيدروليكية', lastInvoice: '20/05/2026', amount: 560000, status: 'paid' },
      { name: 'Fillauer LLC', specialty: 'مكونات أمريكية', lastInvoice: '15/05/2026', amount: 275000, status: 'pending' },
      { name: 'شركة النيل للتوريدات', specialty: 'مواد خام محلية', lastInvoice: '08/06/2026', amount: 42000, status: 'paid' },
      { name: 'Blatchford Group', specialty: 'أقدam صناعية', lastInvoice: '30/04/2026', amount: 198000, status: 'paid' },
      { name: 'شركة الإسكندرية الطبية', specialty: 'أدوات وعدد', lastInvoice: '02/06/2026', amount: 35000, status: 'paid' }
    ];

    var sectionTitles = {
      overview: 'لوحة المعلومات — الإدارة العليا',
      bi: 'لوحات القيادة (BI) — 5 لوحات',
      catalog: 'الأصناف والأسعار',
      pricing: 'اعتماد طلبات التسعير',
      cases: 'متابعة الحالات',
      employees: 'إدارة الموظفين والصلاحيات',
      companies: 'شركات التعاقد',
      debts: 'مديونيات شركات التعاقد',
      audit: 'سجل الرقابة الحصين — Immutable Audit Log',
      reports: 'التقارير والتحليلات',
      suppliers: 'الموردون وفواتير المشتريات'
    };

    function switchSection(sectionId) {
      document.querySelectorAll('.section-view').forEach(function(el) {
        el.classList.toggle('active', el.id === 'section-' + sectionId);
      });
      document.querySelectorAll('.nav-menu a[data-section]').forEach(function(a) {
        a.classList.toggle('active', a.getAttribute('data-section') === sectionId);
      });
      document.getElementById('pageTitle').textContent = sectionTitles[sectionId] || sectionTitles.overview;
      if (sectionId === 'pricing') renderPricingApproval();
      if (sectionId === 'cases') renderCasesSection();
      if (sectionId === 'overview') renderOverviewCasesCounts();
      if (sectionId === 'reports') renderBomAdminReport();
      if (sectionId === 'bi') renderBI();
      if (sectionId === 'debts') { renderDebts(); renderCreditNotes(); }
    }

    function gotoCasesFilter(filter) {
      casesFilter = filter || 'waiting_return';
      switchSection('cases');
      document.querySelectorAll('.cases-quick-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.getAttribute('data-cases-filter') === casesFilter);
      });
      renderCasesSection();
    }

    document.querySelectorAll('[data-goto-cases]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        gotoCasesFilter(btn.getAttribute('data-goto-cases'));
      });
    });

    document.querySelectorAll('.cases-quick-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        casesFilter = btn.getAttribute('data-cases-filter');
        document.querySelectorAll('.cases-quick-btn').forEach(function(b) {
          b.classList.toggle('active', b === btn);
        });
        renderCasesSection();
      });
    });

    document.getElementById('casesSearch').addEventListener('input', function(e) {
      casesSearchTerm = e.target.value.trim();
      renderCasesSection();
    });

    document.querySelectorAll('.nav-menu a[data-section]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        switchSection(link.getAttribute('data-section'));
      });
    });

    window.addEventListener('storage', function(e) {
      if (e.key === PricingQueue.STORAGE_KEY || e.key === CasesWorkflow.STORAGE_KEY) {
        renderPricingApproval();
        renderCasesSection();
        renderOverviewCasesCounts();
        renderAdminAnalytics();
      }
      if (e.key === BomInventory.STORAGE_KEY || e.key === StockCatalog.STORAGE_KEY) {
        renderBomAdminReport();
      }
    });

    function renderOverviewCasesCounts() {
      var waiting = CasesWorkflow.getBucket('waiting_return').length;
      var progress = CasesWorkflow.getBucket('in_progress').length;
      var delivered = CasesWorkflow.getBucket('delivered').length;
      var ow = document.getElementById('overviewWaitingCount');
      var op = document.getElementById('overviewProgressCount');
      var od = document.getElementById('overviewDeliveredCount');
      if (ow) ow.textContent = waiting;
      if (op) op.textContent = progress;
      if (od) od.textContent = delivered;
    }

    function getFilteredCases() {
      var list = CasesWorkflow.getBucket(casesFilter);
      if (!casesSearchTerm) return list;
      return list.filter(function(c) {
        return (c.patient || '').indexOf(casesSearchTerm) !== -1 ||
          (c.quoteId || '').indexOf(casesSearchTerm) !== -1 ||
          (CasesWorkflow.getPricingRef(c) || '').indexOf(casesSearchTerm) !== -1 ||
          (c.company || '').indexOf(casesSearchTerm) !== -1 ||
          (c.orderRef || '').indexOf(casesSearchTerm) !== -1;
      });
    }

    function renderCasesSection() {
      var waiting = CasesWorkflow.getBucket('waiting_return');
      var progress = CasesWorkflow.getBucket('in_progress');
      var delivered = CasesWorkflow.getBucket('delivered');
      document.getElementById('casesWaitingCount').textContent = waiting.length;
      document.getElementById('casesProgressCount').textContent = progress.length;
      document.getElementById('casesDeliveredCount').textContent = delivered.length;
      renderOverviewCasesCounts();

      var filtered = getFilteredCases();
      var titles = {
        waiting_return: '📁 الحالات — بانتظار رجوع العميل',
        in_progress: '📁 الحالات — تحت التنفيذ',
        delivered: '📁 الحالات — تم التسليم'
      };
      document.getElementById('casesPanelTitle').textContent = titles[casesFilter] || titles.waiting_return;
      document.getElementById('casesPanelBadge').textContent = filtered.length + ' حالة';
      document.getElementById('casesFilterCount').textContent = filtered.length + ' حالة';

      var hintEl = document.getElementById('casesPanelHint');
      if (hintEl) {
        if (casesFilter === 'waiting_return') {
          hintEl.innerHTML = 'العميل خرج من المركز بـ <strong>عرض سعر رسمي</strong> (QR) — العمود «رقم عرض السعر» هو الوثيقة المطبوعة. «مرجع التسعير» يربط الطلب بخطوة حساب التكلفة وموافقة الأدمن.';
          hintEl.style.display = 'block';
        } else if (casesFilter === 'in_progress') {
          hintEl.innerHTML = 'العميل رجع بخطاب الموافقة — الشغل جاري في المخزن/الورشة. التسليم للمريض يتم بعد BOM «تام» فقط.';
          hintEl.style.display = 'block';
        } else {
          hintEl.innerHTML = 'تقرير مالي: إجمالي التكلفة، المدفوع، والمديونية المتبقية.';
          hintEl.style.display = 'block';
        }
      }

      var head = document.getElementById('casesTableHead');
      var body = document.getElementById('casesTableBody');
      var pipelineCol = '<th style="min-width:320px">مسار الحالة</th>';

      if (casesFilter === 'waiting_return') {
        head.innerHTML = '<tr><th>المريض</th><th>جهة التعاقد</th><th>رقم عرض السعر</th><th>تاريخ العرض</th><th>أيام الانتظار</th>' + pipelineCol + '</tr>';
        body.innerHTML = filtered.length ? filtered.map(function(c) {
          var days = CasesWorkflow.daysBetween(c.quoteDate);
          var daysCls = days >= 14 ? ' days-wait-badge urgent' : ' days-wait-badge';
          var tm = CasesWorkflow.getPatientTypeMeta(c.patientType);
          return '<tr>' +
            '<td><strong>' + c.patient + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
            '<td>' + c.company + '</td>' +
            '<td>' + CasesWorkflow.renderQuoteRefCell(c) + '</td>' +
            '<td>' + (c.quoteDate || '—') + '</td>' +
            '<td><span class="' + daysCls.trim() + '">⏱ ' + days + ' يوم</span></td>' +
            '<td><div class="wf-pipeline">' + CasesWorkflow.renderPipeline(c) + '</div></td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد حالات مطابقة</td></tr>';
      } else if (casesFilter === 'in_progress') {
        head.innerHTML = '<tr><th>المريض</th><th>جهة التعاقد</th><th>مرحلة الشغل</th><th>BOM</th><th>تاريخ الموافقة</th><th>إجراء</th>' + pipelineCol + '</tr>';
        body.innerHTML = filtered.length ? filtered.map(function(c) {
          var bom = BomInventory.getByCaseId(c.id);
          var bomLabel = bom ? BomInventory.getStageLabel(bom.stage) : '—';
          var bomCls = bom ? BomInventory.getStageBadgeClass(bom.stage) : 'default';
          var canDel = BomInventory.canDeliver(c.id);
          var actionBtn = canDel.ok
            ? '<button type="button" class="btn-action success" onclick="deliverCase(\'' + c.id + '\')">✅ تسليم</button>'
            : '<span class="stage-badge ' + bomCls + '" title="' + (canDel.reason || '') + '">' + bomLabel + '</span>';
          var tm = CasesWorkflow.getPatientTypeMeta(c.patientType);
          return '<tr>' +
            '<td><strong>' + c.patient + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
            '<td>' + c.company + '</td>' +
            '<td><span class="stage-badge progress">' + CasesWorkflow.getManufacturingLabel(c.manufacturingStage) + '</span></td>' +
            '<td><span class="stage-badge ' + bomCls + '">' + bomLabel + '</span></td>' +
            '<td>' + (c.approvalDate || '—') + '</td>' +
            '<td>' + actionBtn + '</td>' +
            '<td><div class="wf-pipeline">' + CasesWorkflow.renderPipeline(c) + '</div></td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد حالات مطابقة</td></tr>';
      } else {
        head.innerHTML = '<tr><th>المريض</th><th>جهة التعاقد</th><th>إجمالي التكلفة</th><th>المدفوع</th><th>الباقي (مديونية)</th><th>تاريخ التسليم</th>' + pipelineCol + '</tr>';
        body.innerHTML = filtered.length ? filtered.map(function(c) {
          var remaining = Math.max(0, (c.totalCost || 0) - (c.paid || 0));
          var tm = CasesWorkflow.getPatientTypeMeta(c.patientType);
          return '<tr>' +
            '<td><strong>' + c.patient + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
            '<td>' + c.company + '</td>' +
            '<td class="pricing-total-cell">' + CasesWorkflow.formatMoney(c.totalCost) + '</td>' +
            '<td style="color:#059669;font-weight:700">' + CasesWorkflow.formatMoney(c.paid) + '</td>' +
            '<td style="color:' + (remaining > 0 ? '#dc2626' : '#059669') + ';font-weight:700">' + CasesWorkflow.formatMoney(remaining) + '</td>' +
            '<td>' + (c.deliveredAt || '—') + '</td>' +
            '<td><div class="wf-pipeline">' + CasesWorkflow.renderPipeline(c) + '</div></td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد حالات مطابقة</td></tr>';
      }

      ChartKit.mount('analytics-cases', {
        stats: [
          { icon: '⏳', label: 'بانتظار الرجوع', value: waiting.length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '🏭', label: 'تحت التنفيذ', value: progress.length, color: '#0e7490', bg: 'rgba(14,116,144,0.1)' },
          { icon: '✅', label: 'تم التسليم', value: delivered.length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '⏱', label: 'متوسط انتظار', value: waiting.length ? Math.round(waiting.reduce(function(s,c){return s+CasesWorkflow.daysBetween(c.quoteDate);},0)/waiting.length) + ' يوم' : '—', color: '#7c3aed', bg: 'rgba(124,58,237,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'توزيع الحالات', items: [
            { label: 'بانتظار الرجوع', value: waiting.length, color: '#d97706' },
            { label: 'تحت التنفيذ', value: progress.length, color: '#0e7490' },
            { label: 'تم التسليم', value: delivered.length, color: '#059669' }
          ]},
          { type: 'bar', title: 'مديونيات مفتوحة (ألف ج.م)', color: '#dc2626', items: progress.slice(0, 5).map(function(c) {
            var rem = Math.max(0, (c.totalCost || 0) - (c.paid || 0));
            return { label: c.patient.slice(0, 10), value: Math.round(rem / 1000), display: Math.round(rem / 1000) + ' ألف' };
          })}
        ]
      });
    }

    function exportCases(type) {
      var filtered = getFilteredCases();
      var headers, rows, title;
      if (casesFilter === 'waiting_return') {
        title = 'حالات بانتظار رجوع العميل';
        headers = ['المريض', 'جهة التعاقد', 'رقم عرض السعر', 'مرجع التسعير', 'تاريخ العرض', 'أيام الانتظار', 'الحالة'];
        rows = filtered.map(function(c) {
          return [c.patient, c.company, c.quoteId, CasesWorkflow.getPricingRef(c), c.quoteDate, CasesWorkflow.daysBetween(c.quoteDate), c.stageLabel];
        });
      } else if (casesFilter === 'in_progress') {
        title = 'حالات تحت التنفيذ';
        headers = ['المريض', 'جهة التعاقد', 'مرحلة الشغل', 'BOM', 'تاريخ الموافقة'];
        rows = filtered.map(function(c) {
          var bom = BomInventory.getByCaseId(c.id);
          return [c.patient, c.company, CasesWorkflow.getManufacturingLabel(c.manufacturingStage), bom ? BomInventory.getStageLabel(bom.stage) : '—', c.approvalDate];
        });
      } else {
        title = 'تقرير الحالات المسلّمة';
        headers = ['المريض', 'جهة التعاقد', 'إجمالي التكلفة', 'المدفوع', 'الباقي', 'تاريخ التسليم'];
        rows = filtered.map(function(c) {
          var rem = Math.max(0, (c.totalCost || 0) - (c.paid || 0));
          return [c.patient, c.company, c.totalCost, c.paid, rem, c.deliveredAt];
        });
      }
      if (type === 'excel') ExportKit.toExcel('cases-' + casesFilter, headers, rows);
      else ExportKit.toPDF(title, headers, rows);
    }

    function deliverCase(caseId) {
      var check = BomInventory.canDeliver(caseId);
      if (!check.ok) {
        alert('⚠️ ' + check.reason);
        return;
      }
      var c = CasesWorkflow.getById(caseId);
      if (!c || !confirm('تأكيد تسليم الطرف للمريض:\n' + c.patient + '؟')) return;
      var result = CasesWorkflow.onDelivered(caseId, {
        paid: c.paid,
        totalCost: c.totalCost
      });
      if (result && result.error) {
        alert('⚠️ ' + result.error);
        return;
      }
      renderCasesSection();
      renderOverviewCasesCounts();
      renderAdminAnalytics();
    }
    window.deliverCase = deliverCase;

    function pricingTypeBadge(patientType) {
      if (typeof CasesWorkflow !== 'undefined' && CasesWorkflow.getPatientTypeMeta) {
        var tm = CasesWorkflow.getPatientTypeMeta(patientType || 'civilian');
        return '<span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span>';
      }
      var isMil = patientType === 'military';
      return '<span class="patient-type-badge ' + (isMil ? 'military' : 'civilian') + '">' +
        (isMil ? '🪖 عسكري' : '🌐 مدني') + '</span>';
    }

    function getFilteredPricingApproval() {
      return PricingQueue.getAll().filter(function(p) {
        var matchFilter = pricingApprovalFilter === 'all' || p.statusKey === pricingApprovalFilter;
        var matchSearch = !pricingApprovalSearch ||
          p.id.indexOf(pricingApprovalSearch) !== -1 ||
          p.patient.indexOf(pricingApprovalSearch) !== -1 ||
          p.orderRef.indexOf(pricingApprovalSearch) !== -1;
        return matchFilter && matchSearch;
      });
    }

    function renderPricingApproval() {
      var all = PricingQueue.getAll();
      var filtered = getFilteredPricingApproval();
      var pendingCount = all.filter(function(p) { return p.statusKey === 'pending'; }).length;

      document.getElementById('pricingApprovalBadge').textContent = pendingCount + ' بانتظار';
      document.getElementById('pricingApprovalCount').textContent = filtered.length + ' طلب';

      if (!filtered.length) {
        document.getElementById('pricingApprovalTable').innerHTML =
          '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">لا توجد طلبات مطابقة</td></tr>';
        return;
      }

      document.getElementById('pricingApprovalTable').innerHTML = filtered.map(function(p, idx) {
        var total = PricingQueue.estimateTotal(p.recommendations);
        var actions = '<div class="approval-actions">' +
          '<button type="button" class="btn-action" onclick="openPricingApprovalModal(\'' + p.id + '\')">عرض التفاصيل</button>';
        if (p.statusKey === 'pending') {
          actions += '<button type="button" class="btn-action approve" onclick="approvePricingRequest(\'' + p.id + '\')">✅ اعتماد</button>';
        }
        actions += '</div>';
        return '<tr>' +
          '<td style="color:var(--text-muted);font-weight:600">' + (idx + 1) + '</td>' +
          '<td><strong>' + p.id + '</strong><br><span style="font-size:11px;color:var(--text-muted);">' + p.orderRef + '</span></td>' +
          '<td><strong>' + p.patient + '</strong> ' + pricingTypeBadge(p.patientType) +
          '<br><span style="font-size:11px;color:var(--text-muted);">' + p.company + '</span></td>' +
          '<td>' + p.date + '</td>' +
          '<td style="text-align:center;font-weight:700;">' + p.items + '</td>' +
          '<td class="pricing-total-cell">' + PricingQueue.formatMoney(total) + '</td>' +
          '<td><span class="pricing-approval-status ' + p.statusKey + '">' + p.statusLabel + '</span></td>' +
          '<td>' + actions + '</td>' +
          '</tr>';
      }).join('');
    }

    function openPricingApprovalModal(id) {
      var p = PricingQueue.getById(id);
      if (!p) return;
      selectedPricingId = id;
      document.getElementById('pricingApprovalModalTitle').textContent = '🧾 ' + p.id;
      document.getElementById('pricingApprovalModalRef').textContent = p.orderRef + ' · ' + p.patient;
      document.getElementById('pricingApprovalModalMeta').innerHTML =
        '<div class="catalog-detail-box"><div class="dl">المريض</div><div class="dv">' + p.patient + '</div></div>' +
        '<div class="catalog-detail-box"><div class="dl">تصنيف المريض</div><div class="dv">' + pricingTypeBadge(p.patientType) + '</div></div>' +
        '<div class="catalog-detail-box"><div class="dl">جهة التعاقد</div><div class="dv" style="font-size:13px;font-weight:600">' + p.company + '</div></div>' +
        '<div class="catalog-detail-box"><div class="dl">الطبيب</div><div class="dv" style="font-size:13px;font-weight:600">' + (p.doctor || '—') + '</div></div>' +
        '<div class="catalog-detail-box"><div class="dl">التاريخ</div><div class="dv">' + p.date + '</div></div>' +
        '<div class="catalog-detail-box"><div class="dl">الحالة</div><div class="dv" style="font-size:13px">' + p.statusLabel + '</div></div>' +
        (p.approvedBy ? '<div class="catalog-detail-box"><div class="dl">اعتمد بواسطة</div><div class="dv" style="font-size:13px">' + p.approvedBy + '</div></div>' : '') +
        (p.approvedAt ? '<div class="catalog-detail-box"><div class="dl">تاريخ الاعتماد</div><div class="dv" style="font-size:13px">' + p.approvedAt + '</div></div>' : '');

      var rows = (p.recommendations || []).map(function(rec) {
        var name = typeof rec === 'string' ? rec : rec.name;
        var code = typeof rec === 'string' ? null : rec.code;
        var qty = typeof rec === 'string' ? 1 : (rec.qty || 1);
        var stock = PricingQueue.findStockItem(name, code);
        var unit = PricingQueue.highestUnitPrice(stock);
        return '<tr>' +
          '<td><strong>' + name + '</strong></td>' +
          '<td>' + (stock ? stock.code : (code || '—')) + '</td>' +
          '<td>' + qty + '</td>' +
          '<td>' + PricingQueue.formatMoney(unit) + '</td>' +
          '<td>' + PricingQueue.formatMoney(unit * qty) + '</td>' +
          '</tr>';
      }).join('');

      document.getElementById('pricingApprovalModalItems').innerHTML = rows ||
        '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">لا توجد بنود</td></tr>';
      document.getElementById('pricingApprovalModalTotal').textContent = PricingQueue.formatMoney(PricingQueue.estimateTotal(p.recommendations));

      var approveBtn = document.getElementById('btnApprovePricingModal');
      approveBtn.style.display = p.statusKey === 'pending' ? 'inline-flex' : 'none';

      document.getElementById('pricingApprovalModal').classList.add('open');
    }

    function closePricingApprovalModal() {
      document.getElementById('pricingApprovalModal').classList.remove('open');
      selectedPricingId = null;
    }

    function approvePricingRequest(id) {
      var p = PricingQueue.getById(id);
      if (!p || p.statusKey !== 'pending') return;
      if (!confirm('موافقة الأدمن على طلب ' + p.id + ' وإرساله للاستقبال لإصدار عرض السعر؟')) return;
      PricingQueue.approve(id, ADMIN_USER);
      closePricingApprovalModal();
      renderPricingApproval();
      renderCasesSection();
      renderOverviewCasesCounts();
      renderAdminAnalytics();
      alert('✅ تمت موافقة الأدمن — الطلب جاهز للاستقبال لإصدار عرض السعر');
    }

    function exportPricingApproval(type) {
      var data = getFilteredPricingApproval();
      var headers = ['رقم الطلب', 'أمر التشغيل', 'المريض', 'التصنيف', 'جهة التعاقد', 'التاريخ', 'البنود', 'التقدير', 'الحالة'];
      var rows = data.map(function(p) {
        var typeLabel = p.patientType === 'military' ? 'عسكري' : 'مدني';
        return [p.id, p.orderRef, p.patient, typeLabel, p.company, p.date, p.items, PricingQueue.estimateTotal(p.recommendations), p.statusLabel];
      });
      if (type === 'excel') ExportKit.toExcel('pricing-approval', headers, rows);
      else ExportKit.toPDF('اعتماد التسعير', headers, rows);
    }

    document.getElementById('pricingApprovalSearch').addEventListener('input', function(e) {
      pricingApprovalSearch = e.target.value.trim();
      renderPricingApproval();
    });

    document.getElementById('pricingApprovalFilter').addEventListener('change', function(e) {
      pricingApprovalFilter = e.target.value;
      renderPricingApproval();
    });

    document.getElementById('closePricingApprovalModal').addEventListener('click', closePricingApprovalModal);
    document.getElementById('btnClosePricingApprovalModal').addEventListener('click', closePricingApprovalModal);
    document.getElementById('btnApprovePricingModal').addEventListener('click', function() {
      if (selectedPricingId) approvePricingRequest(selectedPricingId);
    });
    document.getElementById('pricingApprovalModal').addEventListener('click', function(e) {
      if (e.target === this) closePricingApprovalModal();
    });

    function getFilteredCompanies() {
      return contractCompanies.filter(function(c) {
        return !companySearchTerm || c.name.indexOf(companySearchTerm) !== -1;
      });
    }

    function renderCompanies() {
      contractCompanies = loadCompanies();
      var filtered = getFilteredCompanies();
      document.getElementById('companiesBadge').textContent = contractCompanies.length + ' شركة';
      document.getElementById('companiesCount').textContent = filtered.length + ' شركة';
      document.getElementById('companiesTable').innerHTML = filtered.map(function(c, idx) {
        return '<tr>' +
          '<td style="color:var(--text-muted);font-weight:600">' + (idx + 1) + '</td>' +
          '<td><strong>' + c.name + '</strong></td>' +
          '<td><button class="btn-action" style="color:#b91c1c" onclick="deleteCompany(\'' + c.id + '\')">حذف</button></td>' +
          '</tr>';
      }).join('');
    }

    function addCompany() {
      var name = document.getElementById('companyNameInput').value.trim();
      if (!name) {
        alert('يرجى إدخال اسم الشركة');
        return;
      }
      contractCompanies = loadCompanies();
      if (contractCompanies.some(function(c) { return c.name === name; })) {
        alert('هذه الشركة مسجلة مسبقاً');
        return;
      }
      var maxNum = contractCompanies.reduce(function(m, c) {
        var n = parseInt(String(c.id || '').replace(/\D/g, ''), 10);
        return isNaN(n) ? m : Math.max(m, n);
      }, 0);
      contractCompanies.push({ id: 'CO-' + String(maxNum + 1).padStart(3, '0'), name: name });
      saveCompanies(contractCompanies);
      document.getElementById('companyNameInput').value = '';
      renderCompanies();
      renderAdminAnalytics();
    }

    function deleteCompany(id) {
      var company = contractCompanies.find(function(c) { return c.id === id; });
      if (!company) return;
      var inDebts = debts.some(function(d) { return d.company === company.name; });
      if (inDebts) {
        alert('لا يمكن حذف «' + company.name + '» — مرتبطة بسجل مديونيات');
        return;
      }
      if (!confirm('حذف «' + company.name + '»؟')) return;
      contractCompanies = loadCompanies().filter(function(c) { return c.id !== id; });
      saveCompanies(contractCompanies);
      renderCompanies();
      renderAdminAnalytics();
    }

    function exportCompanies(type) {
      var data = getFilteredCompanies();
      var headers = ['#', 'اسم الشركة'];
      var rows = data.map(function(c, i) { return [i + 1, c.name]; });
      if (type === 'excel') ExportKit.toExcel('contract-companies', headers, rows);
      else ExportKit.toPDF('شركات التعاقد', headers, rows);
    }

    document.getElementById('btnAddCompany').addEventListener('click', addCompany);
    document.getElementById('companyNameInput').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); addCompany(); }
    });
    document.getElementById('companySearch').addEventListener('input', function(e) {
      companySearchTerm = e.target.value.trim();
      renderCompanies();
    });

    function formatPriceRange(prices) {
      var s = StockCatalog.getPriceSummary(prices);
      if (!s.count) return '—';
      if (s.min === s.max) return s.count + ' سعر · ' + StockCatalog.formatPrice(s.min);
      return s.count + ' أسعار · ' + StockCatalog.formatPrice(s.min) + ' – ' + StockCatalog.formatPrice(s.max);
    }

    function getFilteredCatalog() {
      return catalogItems.filter(function(item) {
        var matchCat = catalogCategoryFilter === 'all' || item.category === catalogCategoryFilter;
        var matchSearch = !catalogSearchTerm ||
          item.name.indexOf(catalogSearchTerm) !== -1 ||
          item.code.indexOf(catalogSearchTerm) !== -1 ||
          item.spec.indexOf(catalogSearchTerm) !== -1;
        return matchCat && matchSearch;
      });
    }

    var SUPPLIER_TYPES = [
      { value: 'محلي', label: 'محلي', cls: 'local' },
      { value: 'مستورد', label: 'مستورد', cls: 'import' },
      { value: 'OEM', label: 'OEM', cls: 'oem' },
      { value: 'موزّع', label: 'موزّع', cls: 'distributor' }
    ];

    function supplierTypeOptions(selected) {
      return SUPPLIER_TYPES.map(function(t) {
        return '<option value="' + t.value + '"' + (selected === t.value ? ' selected' : '') + '>' + t.label + '</option>';
      }).join('');
    }

    function getSupplierTypeInfo(type) {
      var found = SUPPLIER_TYPES.find(function(t) { return t.value === type; });
      return found || { value: type || '—', label: type || '—', cls: 'import' };
    }

    function resolveSupplierType(price) {
      if (price.supplierType) return price.supplierType;
      if (price.label && price.label.indexOf('محلي') !== -1) return 'محلي';
      return 'مستورد';
    }

    function priceRowHtml(price, code) {
      var p = price || {};
      var st = resolveSupplierType(p);
      return '<div class="price-row" data-id="' + (p.id || '') + '">' +
        '<div><label>وصف الصنف</label><input type="text" class="price-label" value="' + (p.label || '') + '" placeholder="مثال: ركبة محلية"></div>' +
        '<div><label>المورد</label><input type="text" class="price-supplier" value="' + (p.supplier || '') + '" placeholder="Ottobock Egypt"></div>' +
        '<div><label>نوع المورد</label><select class="price-supplier-type">' + supplierTypeOptions(st) + '</select></div>' +
        '<div><label>كود الصنف</label><input type="text" class="price-item-code" value="' + (p.itemCode || p.batch || '') + '" placeholder="ITM-001-01"></div>' +
        '<div><label>السعر (ج.م)</label><input type="number" class="price-amount" min="0" value="' + (p.amount || '') + '" placeholder="45000"></div>' +
        '<button type="button" class="btn-remove-price" onclick="removePriceRow(this)" aria-label="حذف">&times;</button>' +
        '</div>';
    }

    function resetCatalogForm() {
      editingCatalogCode = null;
      document.getElementById('catalogEditCode').value = '';
      document.getElementById('catalogName').value = '';
      document.getElementById('catalogSpec').value = '';
      document.getElementById('catalogCategory').value = 'مفاصل';
      document.getElementById('catalogQty').value = '0';
      document.getElementById('itemPricesList').innerHTML = priceRowHtml({}, 'NEW');
    }

    function openCatalogForm(item) {
      document.getElementById('catalogForm').classList.add('open');
      if (item) {
        editingCatalogCode = item.code;
        document.getElementById('catalogEditCode').value = item.code;
        document.getElementById('catalogName').value = item.name;
        document.getElementById('catalogSpec').value = item.spec || '';
        document.getElementById('catalogCategory').value = item.category;
        document.getElementById('catalogQty').value = item.qty || 0;
        document.getElementById('itemPricesList').innerHTML = (item.prices && item.prices.length)
          ? item.prices.map(function(p) { return priceRowHtml(p, item.code); }).join('')
          : priceRowHtml({}, item.code);
      } else {
        resetCatalogForm();
      }
    }

    function closeCatalogForm() {
      document.getElementById('catalogForm').classList.remove('open');
      resetCatalogForm();
    }

    function removePriceRow(btn) {
      var list = document.getElementById('itemPricesList');
      if (list.querySelectorAll('.price-row').length <= 1) {
        btn.closest('.price-row').querySelectorAll('input, select').forEach(function(inp) {
          if (inp.tagName === 'SELECT') inp.selectedIndex = 0;
          else inp.value = '';
        });
        return;
      }
      btn.closest('.price-row').remove();
    }

    function collectPricesFromForm() {
      return Array.prototype.slice.call(document.querySelectorAll('#itemPricesList .price-row')).map(function(row) {
        var id = row.getAttribute('data-id');
        var code = editingCatalogCode || StockCatalog.nextCode();
        return {
          id: id || StockCatalog.nextPriceId(code),
          label: row.querySelector('.price-label').value.trim(),
          supplier: row.querySelector('.price-supplier').value.trim(),
          supplierType: row.querySelector('.price-supplier-type').value,
          itemCode: row.querySelector('.price-item-code').value.trim(),
          amount: parseInt(row.querySelector('.price-amount').value, 10) || 0
        };
      }).filter(function(p) { return p.label && p.itemCode && p.amount > 0; });
    }

    function renderCatalog() {
      catalogItems = StockCatalog.getAll();
      var filtered = getFilteredCatalog();
      document.getElementById('catalogCount').textContent = catalogItems.length + ' صنف';
      document.getElementById('catalogFilteredCount').textContent = filtered.length + ' صنف';
      document.getElementById('catalogTable').innerHTML = filtered.map(function(item) {
        return '<tr class="catalog-row-clickable" data-code="' + item.code + '" title="اضغط لعرض التفاصيل">' +
          '<td><strong>' + item.code + '</strong></td>' +
          '<td><strong>' + item.name + '</strong></td>' +
          '<td>' + item.category + '</td>' +
          '<td>' + (item.spec || '—') + '</td>' +
          '<td>' + (item.qty || 0) + '</td>' +
          '<td onclick="event.stopPropagation()">' +
            '<button class="btn-action" onclick="showCatalogDetail(\'' + item.code + '\')">عرض</button> ' +
            '<button class="btn-action" onclick="editCatalogItem(\'' + item.code + '\')">تعديل</button> ' +
            '<button class="btn-action" style="color:#b91c1c" onclick="deleteCatalogItem(\'' + item.code + '\')">حذف</button>' +
          '</td></tr>';
      }).join('');
    }

    var catalogModalItemCode = null;

    function buildPricesAccordionHtml(prices) {
      var count = prices.length;
      var summary = StockCatalog.getPriceSummary(prices);
      var subText = count
        ? (summary.min === summary.max
          ? StockCatalog.formatPrice(summary.min)
          : 'من ' + StockCatalog.formatPrice(summary.min) + ' إلى ' + StockCatalog.formatPrice(summary.max))
        : 'لا توجد أسعار';

      var panelContent = count
        ? prices.map(function(p) {
            var st = getSupplierTypeInfo(resolveSupplierType(p));
            return '<div class="price-supplier-card">' +
              '<div class="psc-main">' +
                '<div class="psc-supplier">' + (p.supplier || '—') + '</div>' +
                '<div class="psc-meta">' +
                  '<span class="supplier-type-tag ' + st.cls + '">' + st.label + '</span>' +
                  '<span class="psc-code">' + (p.itemCode || p.batch || '—') + '</span>' +
                  (p.label ? '<span>' + p.label + '</span>' : '') +
                '</div>' +
              '</div>' +
              '<div class="psc-amount">' + StockCatalog.formatPrice(p.amount) + '</div>' +
            '</div>';
          }).join('')
        : '<div class="prices-accordion-empty">لا توجد أسعار مسجلة — أضف أسعاراً من «تعديل الصنف»</div>';

      return '<div class="prices-accordion" id="catalogPricesAccordion">' +
        '<button type="button" class="prices-accordion-toggle" id="catalogPricesToggle"' + (count ? '' : ' disabled style="opacity:0.7;cursor:default"') + '>' +
          '<span class="pat-left">' +
            '<span class="pat-icon">💰</span>' +
            '<span><span class="pat-text">أسعار الموردين</span><br><span class="pat-sub">' + subText + '</span></span>' +
          '</span>' +
          (count ? '<span class="pat-count">' + count + ' ' + (count === 1 ? 'مورد' : 'موردين') + '</span>' : '') +
          '<span class="pat-arrow">▼</span>' +
        '</button>' +
        '<div class="prices-accordion-panel" id="catalogPricesPanel">' + panelContent + '</div>' +
      '</div>';
    }

    function bindPricesAccordion() {
      var accordion = document.getElementById('catalogPricesAccordion');
      var toggle = document.getElementById('catalogPricesToggle');
      if (!accordion || !toggle || toggle.disabled) return;
      toggle.addEventListener('click', function() {
        accordion.classList.toggle('open');
      });
    }

    function showCatalogDetail(code) {
      var item = StockCatalog.getAll().find(function(i) { return i.code === code; });
      if (!item) return;
      catalogModalItemCode = code;

      var reserved = item.reserved || 0;
      var available = Math.max(0, (item.qty || 0) - reserved);
      var statusLabel = item.status === 'low' ? 'كمية منخفضة' : 'متوفر';
      var statusClass = item.status === 'low' ? 'low' : 'ok';
      var prices = item.prices || [];

      document.getElementById('catalogModalTitle').textContent = item.name;
      document.getElementById('catalogModalCode').textContent = item.code;

      var priceSummary = StockCatalog.getPriceSummary(prices);

      document.getElementById('catalogModalBody').innerHTML =
        '<div class="catalog-detail-grid">' +
          '<div class="catalog-detail-box"><div class="dl">الفئة</div><div class="dv">' + item.category + '</div></div>' +
          '<div class="catalog-detail-box"><div class="dl">المواصفات</div><div class="dv" style="font-size:13px;font-weight:600">' + (item.spec || '—') + '</div></div>' +
          '<div class="catalog-detail-box"><div class="dl">الكمية الكلية</div><div class="dv">' + (item.qty || 0) + '</div></div>' +
          '<div class="catalog-detail-box"><div class="dl">محجوز</div><div class="dv" style="color:#0e7490">' + reserved + '</div></div>' +
          '<div class="catalog-detail-box"><div class="dl">المتاح</div><div class="dv ok">' + available + '</div></div>' +
          '<div class="catalog-detail-box"><div class="dl">الحالة</div><div class="dv ' + statusClass + '">' + statusLabel + '</div></div>' +
          '<div class="catalog-detail-box"><div class="dl">عدد الأسعار</div><div class="dv">' + priceSummary.count + '</div></div>' +
          (priceSummary.count
            ? '<div class="catalog-detail-box"><div class="dl">نطاق الأسعار</div><div class="dv" style="font-size:13px">' +
              (priceSummary.min === priceSummary.max
                ? StockCatalog.formatPrice(priceSummary.min)
                : StockCatalog.formatPrice(priceSummary.min) + ' – ' + StockCatalog.formatPrice(priceSummary.max)) +
              '</div></div>'
            : '') +
        '</div>' +
        buildPricesAccordionHtml(prices);

      bindPricesAccordion();
      document.getElementById('catalogDetailModal').classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeCatalogDetailModal() {
      document.getElementById('catalogDetailModal').classList.remove('open');
      document.body.style.overflow = '';
      catalogModalItemCode = null;
    }

    function editCatalogItem(code) {
      var item = catalogItems.find(function(i) { return i.code === code; });
      if (item) openCatalogForm(item);
    }

    function deleteCatalogItem(code) {
      var item = catalogItems.find(function(i) { return i.code === code; });
      if (!item) return;
      if (!confirm('حذف «' + item.name + '» من الكatalog؟')) return;
      closeCatalogDetailModal();
      StockCatalog.removeItem(code);
      catalogItems = StockCatalog.getAll();
      renderCatalog();
      renderAdminAnalytics();
    }

    function saveCatalogItem() {
      var name = document.getElementById('catalogName').value.trim();
      var spec = document.getElementById('catalogSpec').value.trim() || '—';
      var category = document.getElementById('catalogCategory').value;
      var qty = parseInt(document.getElementById('catalogQty').value, 10) || 0;
      var prices = collectPricesFromForm();
      if (!name) {
        alert('يرجى إدخال اسم الصنف');
        return;
      }
      if (!prices.length) {
        alert('يرجى إضافة سعر واحد على الأقل مع كود الصنف');
        return;
      }
      if (editingCatalogCode) {
        var existing = catalogItems.find(function(i) { return i.code === editingCatalogCode; });
        StockCatalog.updateItem(editingCatalogCode, {
          name: name,
          spec: spec,
          category: category,
          qty: qty,
          reserved: existing ? existing.reserved : 0,
          prices: prices
        });
      } else {
        StockCatalog.addItem({
          code: StockCatalog.nextCode(),
          name: name,
          spec: spec,
          category: category,
          qty: qty,
          reserved: 0,
          prices: prices
        });
      }
      catalogItems = StockCatalog.getAll();
      closeCatalogForm();
      renderCatalog();
      renderAdminAnalytics();
      alert('تم حفظ الصنف — يظهر في المخزون وتوصيات الطبيب');
    }

    function exportCatalog(type) {
      var data = getFilteredCatalog();
      var headers = ['الكود', 'الصنف', 'الفئة', 'المواصفات', 'عدد الأسعار', 'أقل سعر', 'أعلى سعر', 'الكمية'];
      var rows = data.map(function(i) {
        var s = StockCatalog.getPriceSummary(i.prices);
        return [i.code, i.name, i.category, i.spec, s.count, s.min, s.max, i.qty || 0];
      });
      if (type === 'excel') ExportKit.toExcel('catalog-items', headers, rows);
      else ExportKit.toPDF('الأصناف والأسعار', headers, rows);
    }

    document.getElementById('btnToggleCatalogForm').addEventListener('click', function() {
      if (document.getElementById('catalogForm').classList.contains('open')) closeCatalogForm();
      else openCatalogForm(null);
    });
    document.getElementById('btnCancelCatalog').addEventListener('click', closeCatalogForm);
    document.getElementById('btnSaveCatalog').addEventListener('click', saveCatalogItem);
    document.getElementById('btnAddPriceRow').addEventListener('click', function() {
      document.getElementById('itemPricesList').insertAdjacentHTML('beforeend', priceRowHtml({}, editingCatalogCode || 'NEW'));
    });
    document.getElementById('catalogSearch').addEventListener('input', function(e) {
      catalogSearchTerm = e.target.value.trim();
      renderCatalog();
    });
    document.getElementById('catalogCategoryFilter').addEventListener('change', function(e) {
      catalogCategoryFilter = e.target.value;
      renderCatalog();
    });

    document.getElementById('catalogTable').addEventListener('click', function(e) {
      var row = e.target.closest('.catalog-row-clickable');
      if (!row) return;
      showCatalogDetail(row.getAttribute('data-code'));
    });

    document.getElementById('catalogModalClose').addEventListener('click', closeCatalogDetailModal);
    document.getElementById('catalogModalCloseBtn').addEventListener('click', closeCatalogDetailModal);
    document.getElementById('catalogDetailModal').addEventListener('click', closeCatalogDetailModal);
    document.getElementById('catalogModalEdit').addEventListener('click', function() {
      if (!catalogModalItemCode) return;
      var code = catalogModalItemCode;
      closeCatalogDetailModal();
      editCatalogItem(code);
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeCatalogDetailModal();
    });

    function renderAuditItems(containerId, limit, filtered) {
      var logs = filtered || (limit ? auditLogs.slice(0, limit) : auditLogs);
      var container = document.getElementById(containerId);
      if (!container) return;
      container.innerHTML = logs.map(function(log) {
        var meta = (log.ip || log.before)
          ? '<div class="audit-meta">' +
              (log.ip ? '<span>🖥️ IP: ' + log.ip + (log.mac ? ' · MAC: ' + log.mac : '') + '</span>' : '') +
              ((log.before || log.after) ? '<span>📸 قبل: <em>' + (log.before || '—') + '</em> ← بعد: <em>' + (log.after || '—') + '</em></span>' : '') +
            '</div>'
          : '';
        return '<div class="audit-item">' +
          '<span class="audit-time">' + log.time + '</span>' +
          '<div class="audit-desc"><strong>' + log.user + '</strong> — ' + log.desc + meta + '</div>' +
          '<span class="audit-tag">' + log.action + '</span>' +
          '</div>';
      }).join('');
    }

    function getFilteredEmployees() {
      var search = document.getElementById('empSearch') ? document.getElementById('empSearch').value.trim() : '';
      var role = document.getElementById('empRoleFilter') ? document.getElementById('empRoleFilter').value : 'all';
      var status = document.getElementById('empStatusFilter') ? document.getElementById('empStatusFilter').value : 'all';
      return ExportKit.filterItems(employees, { search: search, searchKeys: ['name', 'roleLabel'], filterField: 'role', filterValue: role })
        .filter(function(e) { return status === 'all' || e.status === status; });
    }

    function getFilteredDebts() {
      var search = document.getElementById('debtSearch') ? document.getElementById('debtSearch').value.trim() : '';
      var status = document.getElementById('debtStatusFilter') ? document.getElementById('debtStatusFilter').value : 'all';
      return ExportKit.filterItems(debts, { search: search, searchKeys: ['company'], filterField: 'status', filterValue: status });
    }

    function getFilteredAudit() {
      var search = document.getElementById('auditSearch') ? document.getElementById('auditSearch').value.trim() : '';
      var action = document.getElementById('auditActionFilter') ? document.getElementById('auditActionFilter').value : 'all';
      return ExportKit.filterItems(auditLogs, { search: search, searchKeys: ['user', 'desc', 'action'], filterField: 'action', filterValue: action });
    }

    function getFilteredSuppliers() {
      var search = document.getElementById('supplierSearch') ? document.getElementById('supplierSearch').value.trim() : '';
      var status = document.getElementById('supplierStatusFilter') ? document.getElementById('supplierStatusFilter').value : 'all';
      return ExportKit.filterItems(suppliers, { search: search, searchKeys: ['name', 'specialty'], filterField: 'status', filterValue: status });
    }

    function exportEmployees(type) {
      var data = getFilteredEmployees();
      var headers = ['الاسم', 'الدور', 'الحالة', 'آخر دخول'];
      var rows = data.map(function(e) {
        return [e.name, e.roleLabel, e.status === 'active' ? 'نشط' : 'غير نشط', e.lastLogin];
      });
      if (type === 'excel') ExportKit.toExcel('الموظفون', headers, rows);
      else ExportKit.toPDF('ادارة الموظفين', headers, rows);
    }

    function exportDebts(type) {
      var data = getFilteredDebts();
      var headers = ['جهة التعاقد', 'المستحق', 'الحالة'];
      var rows = data.map(function(d) {
        var lbl = d.status === 'paid' ? 'مسدد' : d.status === 'partial' ? 'جزئي' : 'معلق';
        return [d.company, formatNumber(d.due), lbl];
      });
      if (type === 'excel') ExportKit.toExcel('المديونيات', headers, rows);
      else ExportKit.toPDF('مديونيات شركات التعاقد', headers, rows);
    }

    function exportAudit(type) {
      var data = getFilteredAudit();
      var headers = ['الوقت', 'المستخدم', 'الوصف', 'العملية'];
      var rows = data.map(function(l) { return [l.time, l.user, l.desc, l.action]; });
      if (type === 'excel') ExportKit.toExcel('سجل_الرقابة', headers, rows);
      else ExportKit.toPDF('سجل الرقابة — Audit Trail', headers, rows);
    }

    function exportSuppliers(type) {
      var data = getFilteredSuppliers();
      var headers = ['المورد', 'التخصص', 'آخر فاتورة', 'القيمة', 'الحالة'];
      var rows = data.map(function(s) {
        var lbl = s.status === 'paid' ? 'مسددة' : s.status === 'partial' ? 'جزئية' : 'معلقة';
        return [s.name, s.specialty, s.lastInvoice, formatNumber(s.amount), lbl];
      });
      if (type === 'excel') ExportKit.toExcel('الموردون', headers, rows);
      else ExportKit.toPDF('الموردون وفواتير المشتريات', headers, rows);
    }

    function formatNumber(n) {
      return n.toLocaleString('ar-EG');
    }

    function renderEmployees() {
      var filtered = getFilteredEmployees();
      var htmlAll = employees.map(function(emp) {
        return empRow(emp);
      }).join('');
      var htmlFiltered = filtered.map(function(emp) {
        return empRow(emp);
      }).join('');
      document.getElementById('employeesTable').innerHTML = htmlAll;
      document.getElementById('employeesTableFull').innerHTML = htmlFiltered;
      var ec = document.getElementById('empCount');
      if (ec) ec.textContent = filtered.length + ' موظف';
    }

    function empRow(emp) {
      return '<tr>' +
        '<td><strong>' + emp.name + '</strong></td>' +
        '<td><span class="role-badge ' + emp.role + '">' + emp.roleLabel + '</span></td>' +
        '<td><span class="status-dot ' + emp.status + '">' + (emp.status === 'active' ? 'نشط' : 'غير نشط') + '</span></td>' +
        '<td>' + emp.lastLogin + '</td>' +
        '<td><button class="btn-action" onclick="alert(\'نموذج تجريبي — تعديل صلاحيات: ' + emp.name + '\')">تعديل</button></td>' +
        '</tr>';
    }

    function renderDebts() {
      debts = reloadDebts();
      var filtered = getFilteredDebts();
      var htmlAll = debts.map(debtRow).join('');
      var htmlFiltered = filtered.map(debtRow).join('');
      document.getElementById('debtsTable').innerHTML = htmlAll;
      document.getElementById('debtsTableFull').innerHTML = htmlFiltered;
      var dc = document.getElementById('debtCount');
      if (dc) dc.textContent = filtered.length + ' جهة';
    }

    function appendAudit(entry) {
      auditLogs.unshift(Object.assign({
        time: '08/06/2026 10:00:00',
        user: 'أحمد محمود',
        ip: '192.168.1.10',
        mac: 'A4:5E:60:DC:55:2E',
        before: '—',
        after: '—',
        tag: 'finance'
      }, entry));
    }

    function renderCreditNotes() {
      if (typeof CreditNotes === 'undefined') return;
      var notes = CreditNotes.getAll();
      var badge = document.getElementById('creditNotesBadge');
      if (badge) badge.textContent = notes.length + ' إشعار';
      var tbody = document.getElementById('creditNotesTable');
      if (!tbody) return;
      if (!notes.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">لا توجد إشعارات دائن</td></tr>';
        return;
      }
      tbody.innerHTML = notes.map(function (n) {
        var statusCls = n.status === 'approved' ? 'reception' : (n.status === 'pending' ? 'technical' : 'doctor');
        var actions = '';
        if (n.status === 'pending') {
          actions = '<button type="button" class="btn-action success" onclick="approveCreditNote(\'' + n.id + '\')">✓ اعتماد</button> ' +
            '<button type="button" class="btn-action" onclick="approveCreditNoteReject(\'' + n.id + '\')">✗ رفض</button>';
        } else {
          actions = '<span class="text-muted">' + (n.approvedBy || '—') + '</span>';
        }
        return '<tr>' +
          '<td><strong>' + n.id + '</strong><br><span style="font-size:11px;color:var(--text-muted);">' + n.caseId + '</span></td>' +
          '<td>' + (n.patient || '—') + '</td>' +
          '<td>' + (n.company || '—') + '</td>' +
          '<td>' + CreditNotes.typeLabel(n.type) + '</td>' +
          '<td>' + formatNumber(n.amount) + ' ج.م<br><span style="font-size:11px;color:var(--text-muted);">من ' + formatNumber(n.originalTotal) + '</span></td>' +
          '<td><span class="role-badge ' + statusCls + '">' + CreditNotes.statusLabel(n.status) + '</span></td>' +
          '<td>' + actions + '</td></tr>';
      }).join('');
    }

    function openCreditNoteModal() {
      if (typeof CreditNotes === 'undefined') return;
      var cases = CreditNotes.getEligibleCases();
      var sel = document.getElementById('cnCaseSelect');
      if (!cases.length) {
        alert('لا توجد حالات مسلّمة (مدني) متاحة');
        return;
      }
      sel.innerHTML = cases.map(function (c) {
        var total = c.totalCost || c.quoteTotal || 0;
        return '<option value="' + c.id + '" data-total="' + total + '" data-company="' + (c.company || '') + '">' +
          c.id + ' — ' + c.patient + ' (' + formatNumber(total) + ' ج.م)</option>';
      }).join('');
      updateCnPreview();
      document.getElementById('creditNoteModal').classList.add('open');
    }

    function closeCreditNoteModal() {
      document.getElementById('creditNoteModal').classList.remove('open');
    }

    function updateCnPreview() {
      var sel = document.getElementById('cnCaseSelect');
      var opt = sel.options[sel.selectedIndex];
      var total = opt ? parseInt(opt.getAttribute('data-total'), 10) : 0;
      var type = document.getElementById('cnType').value;
      var amountGroup = document.getElementById('cnAmountGroup');
      if (amountGroup) amountGroup.style.display = type === 'full' ? 'none' : 'block';
      var amount = type === 'full' ? total : (parseInt(document.getElementById('cnAmount').value, 10) || 0);
      document.getElementById('cnPreview').innerHTML = opt
        ? 'جهة: <strong>' + opt.getAttribute('data-company') + '</strong> · إجمالي الفاتورة: <strong>' + formatNumber(total) + ' ج.م</strong> · خصم مقترح: <strong>' + formatNumber(amount) + ' ج.م</strong>'
        : '—';
    }

    function confirmCreditNote() {
      var caseId = document.getElementById('cnCaseSelect').value;
      var res = CreditNotes.createNote({
        caseId: caseId,
        type: document.getElementById('cnType').value,
        amount: document.getElementById('cnAmount').value,
        reason: document.getElementById('cnReason').value
      });
      if (!res.ok) {
        alert(res.reason || res.error || 'تعذّر إنشاء الإشعار');
        return;
      }
      closeCreditNoteModal();
      renderCreditNotes();
      appendAudit({
        action: 'إنشاء',
        desc: 'إشعار دائن ' + res.note.id + ' — ' + formatNumber(res.note.amount) + ' ج.م',
        after: res.note.id,
        tag: 'finance'
      });
    }

    function approveCreditNote(id) {
      var res = CreditNotes.approveNote(id, 'أحمد محمود');
      if (!res.ok) { alert(res.error || 'تعذّر الاعتماد'); return; }
      debts = reloadDebts();
      renderDebts();
      renderCreditNotes();
      appendAudit({
        action: 'اعتماد',
        desc: 'اعتماد إشعار دائن ' + id + ' — خصم ' + formatNumber(res.note.amount) + ' ج.م من ' + res.note.company,
        before: formatNumber(res.note.originalTotal),
        after: formatNumber(Math.max(0, res.note.originalTotal - res.note.amount)),
        tag: 'finance'
      });
      if (typeof renderAdminAnalytics === 'function') renderAdminAnalytics();
    }
    window.approveCreditNote = approveCreditNote;

    function approveCreditNoteReject(id) {
      var res = CreditNotes.rejectNote(id, 'أحمد محمود');
      if (!res.ok) { alert(res.error || 'تعذّر الرفض'); return; }
      renderCreditNotes();
    }
    window.approveCreditNoteReject = approveCreditNoteReject;

    function debtRow(d) {
      var statusLabel = d.status === 'paid' ? 'مسدد' : d.status === 'partial' ? 'جزئي' : 'معلق';
      return '<tr>' +
        '<td><strong>' + d.company + '</strong></td>' +
        '<td>' + formatNumber(d.due) + ' ج.م</td>' +
        '<td><span class="role-badge ' + (d.status === 'paid' ? 'reception' : d.status === 'pending' ? 'technical' : 'doctor') + '">' + statusLabel + '</span></td>' +
        '</tr>';
    }

    function renderSuppliers() {
      var filtered = getFilteredSuppliers();
      document.getElementById('suppliersTable').innerHTML = filtered.map(function(s) {
        var statusLabel = s.status === 'paid' ? 'مسددة' : s.status === 'partial' ? 'جزئية' : 'معلقة';
        return '<tr>' +
          '<td><strong>' + s.name + '</strong></td>' +
          '<td>' + s.specialty + '</td>' +
          '<td>' + s.lastInvoice + '</td>' +
          '<td>' + formatNumber(s.amount) + ' ج.م</td>' +
          '<td><span class="role-badge ' + (s.status === 'paid' ? 'reception' : s.status === 'pending' ? 'technical' : 'doctor') + '">' + statusLabel + '</span></td>' +
          '</tr>';
      }).join('');
      var sc = document.getElementById('supplierCount');
      if (sc) sc.textContent = filtered.length + ' مورد';
    }

    function renderAuditFull() {
      var filtered = getFilteredAudit();
      renderAuditItems('auditListFull', null, filtered);
      var ac = document.getElementById('auditCount');
      if (ac) ac.textContent = filtered.length + ' حركة';
    }

    ['empSearch','empRoleFilter','empStatusFilter'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', renderEmployees);
      if (el && el.tagName === 'SELECT') el.addEventListener('change', renderEmployees);
    });
    ['debtSearch','debtStatusFilter'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderDebts); el.addEventListener('change', renderDebts); }
    });
    ['auditSearch','auditActionFilter'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderAuditFull); el.addEventListener('change', renderAuditFull); }
    });
    ['supplierSearch','supplierStatusFilter'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderSuppliers); el.addEventListener('change', renderSuppliers); }
    });
    function renderAdminAnalytics() {
      var totalDue = debts.reduce(function(s,d){return s+d.due;},0);
      var totalCol = debts.reduce(function(s,d){return s+d.collected;},0);
      var paidCount = debts.length;
      ChartKit.mount('analytics-overview', {
        stats: [
          { icon: '💵', label: 'إيرادات يونيو', value: '2,500 ألف ج.م', color: '#059669', bg: 'rgba(5,150,105,0.1)', sub: '↑ 12%' },
          { icon: '👤', label: 'مرضى', value: '1,247', color: '#0e7490', bg: 'rgba(14,116,144,0.1)' },
          { icon: '📦', label: 'صحة المخزون', value: '78%', color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '💰', label: 'مديونيات', value: formatNumber(Math.round((totalDue-totalCol)/1000)) + ' ألف ج.م', color: '#7c3aed', bg: 'rgba(124,58,237,0.1)' }
        ],
        charts: [
          { type: 'column', title: 'الإيرادات — 6 أشهر (ألف ج.م)', color: '#7c3aed', wide: true, unit: 'EGP_K', items: [
            { label: 'يناير', value: 1800, sub: '↑ 5%' },
            { label: 'فبراير', value: 2100, sub: '↑ 17%' },
            { label: 'مارس', value: 1700, sub: '↓ 19%' },
            { label: 'أبريل', value: 2200, sub: '↑ 29%' },
            { label: 'مايو', value: 1900, sub: '↓ 14%' },
            { label: 'يونيو', value: 2500, sub: '↑ 32%' }
          ], footer: 'إجمالي نصف السنة: <strong>12,200 ألف ج.م</strong> · متوسط شهري: <strong>2,033 ألف ج.م</strong> · الأعلى: <strong>يونيو 2,500 ألف ج.م</strong>' },
          { type: 'donut', title: 'حالة المديونيات', large: true, items: [
            { label: 'مسدد', value: paidCount, display: paidCount + ' جهة', color: '#059669' }
          ], summary: [
            { label: 'المستحق', value: formatNumber(Math.round(totalDue / 1000)) + ' ألف ج.م' },
            { label: 'المحصّل', value: formatNumber(Math.round(totalCol / 1000)) + ' ألف ج.م', color: '#059669' },
            { label: 'المتبقي', value: formatNumber(Math.round((totalDue - totalCol) / 1000)) + ' ألف ج.م', color: '#dc2626' }
          ]},
          { type: 'bar', title: 'المحصّل حسب جهة التعاقد (ألف ج.م)', color: '#059669', items: debts.map(function(d){
            return { label: d.company.slice(0, 16), value: d.collected/1000, display: formatNumber(Math.round(d.collected/1000)) + ' ألف ج.م' };
          }).sort(function(a,b){ return b.value - a.value; }) }
        ]
      });
      ChartKit.mount('analytics-employees', {
        stats: [
          { icon: '👥', label: 'الموظفون', value: employees.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '✅', label: 'نشط', value: employees.filter(function(e){return e.status==='active';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '⏸️', label: 'غير نشط', value: employees.filter(function(e){return e.status==='inactive';}).length, bg: 'rgba(100,116,139,0.1)' },
          { icon: '🩺', label: 'أطباء', value: employees.filter(function(e){return e.role==='doctor';}).length, color: '#0e7490', bg: 'rgba(14,116,144,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'توزيع الأدوار', items: [
            { label: 'إدارة', value: 1, color: '#7c3aed' }, { label: 'أطباء', value: 2, color: '#0e7490' },
            { label: 'فني', value: 1, color: '#d97706' }, { label: 'استقبال', value: 1, color: '#059669' }, { label: 'مخزن', value: 1, color: '#64748b' }
          ]},
          { type: 'bar', title: 'نشاط الموظفين', color: '#7c3aed', items: [
            { label: 'أحمد', value: 5 }, { label: 'سارة', value: 4 }, { label: 'محمد', value: 3 }, { label: 'نورهان', value: 4 }
          ]}
        ]
      });
      var withDebts = contractCompanies.filter(function(c) {
        return debts.some(function(d) { return d.company === c.name; });
      }).length;
      ChartKit.mount('analytics-companies', {
        stats: [
          { icon: '🏢', label: 'شركات', value: contractCompanies.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '💰', label: 'لها مديونيات', value: withDebts, color: '#0e7490', bg: 'rgba(14,116,144,0.1)' },
          { icon: '➕', label: 'بدون مديونيات', value: contractCompanies.length - withDebts, bg: 'rgba(100,116,139,0.1)' }
        ],
        charts: [
          { type: 'bar', title: 'جهات التعاقد — المتبقي (ألف ج.م)', color: '#7c3aed', wide: true, items: contractCompanies.map(function(c) {
            var debt = debts.find(function(d) { return d.company === c.name; });
            return {
              label: c.name,
              value: debt ? (debt.due - debt.collected) / 1000 : 0,
              display: debt ? formatNumber(Math.round((debt.due - debt.collected) / 1000)) + ' ألف ج.م' : '—'
            };
          })}
        ]
      });
      var totalDue = debts.reduce(function(s,d){return s+d.due;},0);
      var totalCol = debts.reduce(function(s,d){return s+d.collected;},0);
      ChartKit.mount('analytics-debts', {
        stats: [
          { icon: '📋', label: 'جهات', value: debts.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '💳', label: 'المستحق', value: formatNumber(Math.round(totalDue / 1000)) + ' ألف ج.م', color: '#7c3aed', bg: 'rgba(124,58,237,0.1)' },
          { icon: '✅', label: 'المحصّل', value: formatNumber(Math.round(totalCol / 1000)) + ' ألف ج.م', color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '⏳', label: 'المتبقي', value: formatNumber(Math.round((totalDue - totalCol) / 1000)) + ' ألف ج.م', color: '#dc2626', bg: 'rgba(220,38,38,0.1)' }
        ],
        charts: [
          { type: 'bar', title: 'المحصّل حسب جهة التعاقد (ألف ج.م)', color: '#059669', items: debts.map(function(d){
            return { label: d.company.slice(0,14), value: d.collected/1000, display: formatNumber(Math.round(d.collected/1000)) + ' ألف ج.م' };
          })},
          { type: 'donut', title: 'حالة السداد', items: [
            { label: 'مسدد', value: debts.length, color: '#059669' }
          ]}
        ]
      });
      ChartKit.mount('analytics-audit', {
        stats: [
          { icon: '📝', label: 'عمليات', value: auditLogs.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '➕', label: 'إنشاء', value: auditLogs.filter(function(a){return a.action==='إنشاء';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '✏️', label: 'تحديث', value: auditLogs.filter(function(a){return a.action==='تحديث'||a.action==='تعديل';}).length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '👁️', label: 'عرض', value: auditLogs.filter(function(a){return a.action==='عرض';}).length, color: '#0e7490', bg: 'rgba(14,116,144,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'حسب القسم', items: [
            { label: 'مرضى', value: 1, color: '#059669' }, { label: 'طبي', value: 1, color: '#0e7490' },
            { label: 'فني', value: 1, color: '#d97706' }, { label: 'مالي', value: 2, color: '#7c3aed' }, { label: 'مخزن', value: 1, color: '#64748b' }
          ]},
          { type: 'bar', title: 'نوع العملية', color: '#7c3aed', items: [
            { label: 'إنشاء', value: 4 }, { label: 'تحديث', value: 2 }, { label: 'عرض', value: 1 }, { label: 'تعديل', value: 1 }
          ]}
        ]
      });
      var catItems = StockCatalog.getAll();
      var totalPrices = catItems.reduce(function(s, i) { return s + (i.prices ? i.prices.length : 0); }, 0);
      var multiPrice = catItems.filter(function(i) { return i.prices && i.prices.length > 1; }).length;
      ChartKit.mount('analytics-catalog', {
        stats: [
          { icon: '📦', label: 'أصناف', value: catItems.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '💰', label: 'أسعار مسجلة', value: totalPrices, color: '#7c3aed', bg: 'rgba(124,58,237,0.1)' },
          { icon: '🏷️', label: 'متعدد الأسعار', value: multiPrice, color: '#0e7490', bg: 'rgba(14,116,144,0.1)' },
          { icon: '📊', label: 'فئات', value: 5, bg: 'rgba(217,119,6,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'الفئات', items: [
            { label: 'مفاصل', value: catItems.filter(function(i){return i.category==='مفاصل';}).length, color: '#d97706' },
            { label: 'أقدام', value: catItems.filter(function(i){return i.category==='أقدام';}).length, color: '#059669' },
            { label: 'بطانات', value: catItems.filter(function(i){return i.category==='بطانات';}).length, color: '#7c3aed' },
            { label: 'محولات', value: catItems.filter(function(i){return i.category==='محولات';}).length, color: '#0e7490' },
            { label: 'إكسسوارات', value: catItems.filter(function(i){return i.category==='إكسسوارات';}).length, color: '#dc2626' }
          ]},
          { type: 'bar', title: 'أسعار لكل صنف', color: '#7c3aed', items: catItems.slice(0, 6).map(function(i) {
            return { label: i.name.slice(0, 12), value: (i.prices || []).length, display: (i.prices || []).length + ' سعر' };
          })}
        ]
      });
      var pricingAll = PricingQueue.getAll();
      var pricingPending = pricingAll.filter(function(p){return p.statusKey==='pending';});
      var pricingSent = pricingAll.filter(function(p){return p.statusKey==='sent';});
      ChartKit.mount('analytics-pricing', {
        stats: [
          { icon: '⏳', label: 'انتظار موافقة الأدمن', value: pricingPending.length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '✅', label: 'جاهز لعرض السعر', value: pricingSent.length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '📋', label: 'إجمالي الطلبات', value: pricingAll.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '💰', label: 'قيمة معلقة', value: formatNumber(Math.round(pricingPending.reduce(function(s,p){return s+PricingQueue.estimateTotal(p.recommendations);},0)/1000)) + ' ألف ج.م', color: '#7c3aed', bg: 'rgba(124,58,237,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'حالة الطلبات', items: [
            { label: 'انتظار الأدمن', value: pricingPending.length, color: '#d97706' },
            { label: 'جاهز للعرض', value: pricingSent.length, color: '#059669' }
          ]},
          { type: 'bar', title: 'التقدير (ألف ج.م)', color: '#7c3aed', items: pricingPending.slice(0, 5).map(function(p){
            return { label: p.patient.slice(0, 10), value: Math.round(PricingQueue.estimateTotal(p.recommendations)/1000), display: formatNumber(Math.round(PricingQueue.estimateTotal(p.recommendations)/1000)) + 'K' };
          })}
        ]
      });
      var supTotal = suppliers.reduce(function(s,x){return s+x.amount;},0);
      ChartKit.mount('analytics-suppliers', {
        stats: [
          { icon: '🏭', label: 'موردون', value: suppliers.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '💰', label: 'فواتير', value: formatNumber(Math.round(supTotal / 1000)) + ' ألف ج.م', color: '#7c3aed', bg: 'rgba(124,58,237,0.1)' },
          { icon: '✅', label: 'مسددة', value: suppliers.filter(function(s){return s.status==='paid';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '⏳', label: 'معلقة', value: suppliers.filter(function(s){return s.status==='pending';}).length, color: '#dc2626', bg: 'rgba(220,38,38,0.1)' }
        ],
        charts: [
          { type: 'bar', title: 'قيمة الفواتير (ألف ج.م)', color: '#7c3aed', items: suppliers.slice(0,5).map(function(s){
            return { label: s.name.slice(0,14), value: s.amount/1000, display: formatNumber(Math.round(s.amount/1000)) + ' ألف ج.م' };
          })},
          { type: 'donut', title: 'حالة الفواتير', items: [
            { label: 'مسددة', value: suppliers.filter(function(s){return s.status==='paid';}).length, color: '#059669' },
            { label: 'جزئية', value: suppliers.filter(function(s){return s.status==='partial';}).length, color: '#d97706' },
            { label: 'معلقة', value: suppliers.filter(function(s){return s.status==='pending';}).length, color: '#dc2626' }
          ]}
        ]
      });
    }

    function renderBomAdminReport() {
      var summaryEl = document.getElementById('bomAdminSummary');
      var tableEl = document.getElementById('bomAdminTable');
      var footerEl = document.getElementById('bomAdminFooter');
      if (!summaryEl || !tableEl) return;

      var summary = BomInventory.getSummary();
      summaryEl.innerHTML = ['raw', 'wip', 'finished'].map(function(key) {
        var s = summary[key];
        return '<div class="bom-admin-stat ' + key + '">' +
          '<div class="bas-label">' + s.label + '</div>' +
          '<div class="bas-value">' + s.count + ' قائمة</div>' +
          '<div class="bas-money">' + BomInventory.formatMoney(s.totalValue) + '</div>' +
          '<div class="bas-sub">' + s.itemCount + ' بند</div></div>';
      }).join('');

      var list = BomInventory.getAll();
      if (!list.length) {
        tableEl.innerHTML = '<tr><td colspan="5" class="empty-cell">لا توجد قوائم BOM</td></tr>';
      } else {
        tableEl.innerHTML = list.map(function(b) {
          return '<tr>' +
            '<td>' + b.patient + '</td>' +
            '<td>' + b.orderRef + '</td>' +
            '<td><span class="stage-badge ' + BomInventory.getStageBadgeClass(b.stage) + '">' + BomInventory.getStageLabel(b.stage) + '</span></td>' +
            '<td class="bom-items-cell">' + BomInventory.renderItemsList(b.items, true) + '</td>' +
            '<td><strong>' + BomInventory.formatMoney(BomInventory.bomTotalValue(b)) + '</strong></td></tr>';
        }).join('');
      }

      var totalVal = list.reduce(function(s, b) { return s + BomInventory.bomTotalValue(b); }, 0);
      if (footerEl) {
        footerEl.textContent = list.length + ' قائمة BOM · إجمالي القيمة (Highest Batch Cost): ' + BomInventory.formatMoney(totalVal);
      }
    }

    /* ===== 5 لوحات قيادة BI ===== */
    function biCard(title, icon, body) {
      return '<div class="bi-card"><div class="bi-card-head"><span>' + icon + '</span><h4>' + title + '</h4></div>' +
        '<div class="bi-card-body">' + body + '</div></div>';
    }
    function biRow(label, value, color) {
      return '<div class="bi-row"><span>' + label + '</span><strong' + (color ? ' style="color:' + color + '"' : '') + '>' + value + '</strong></div>';
    }

    function renderBI() {
      var el = document.getElementById('biContent');
      if (!el) return;
      var dist = CasesWorkflow.getTypeDistribution();
      var sla = CasesWorkflow.getSlaSummary(21);
      var cases = CasesWorkflow.getAll();

      // 1) إدارة المرضى
      var slaList = sla.breached.length
        ? sla.breached.map(function(c){ return '<li>' + c.patient + ' — ' + CasesWorkflow.turnaroundDays(c) + ' يوم (' + (c.stageLabel||'') + ')</li>'; }).join('')
        : '<li style="color:var(--accent,#059669)">لا توجد حالات متأخرة عن الـ SLA ✅</li>';
      var board1 = biCard('1. إدارة المرضى', '👥',
        biRow('إجمالي الحالات', dist.total) +
        biRow('🌐 مدني', dist.civilian, '#0e7490') +
        biRow('🪖 عسكري', dist.military, '#b45309') +
        biRow('متوسط زمن التنفيذ (Turnaround)', sla.avgTat + ' يوم') +
        biRow('حالات مفتوحة', sla.openCount) +
        '<div class="bi-sub">⏱️ حالات متأخرة عن الـ SLA (' + sla.slaLimit + ' يوم):<ul class="bi-list">' + slaList + '</ul></div>');

      // 2) المخازن وسلاسل الإمداد
      var invVal = StockCatalog.inventoryValue();
      var stagnant = StockCatalog.getStagnant(180);
      var low = StockCatalog.getAll().filter(function(i){ return i.status === 'low'; });
      var board2 = biCard('2. المخازن وسلاسل الإمداد', '📦',
        biRow('القيمة المالية الإجمالية (WAC)', formatNumber(invVal) + ' ج.م', '#0e7490') +
        biRow('عدد الأصناف', StockCatalog.getAll().length) +
        biRow('🚨 أصناف ناقصة (قرب حد الأمان)', low.length, '#b91c1c') +
        '<div class="bi-sub">🐌 أصناف راكدة (>180 يوم):<ul class="bi-list">' +
          (stagnant.length ? stagnant.map(function(i){ return '<li>' + i.name + ' (رصيد ' + i.qty + ' · آخر حركة ' + i.lastMoved + ')</li>'; }).join('') : '<li>لا يوجد ✅</li>') +
        '</ul></div>');

      // 3) العمليات والتشغيل
      var ops = (typeof OperationsDesk !== 'undefined') ? OperationsDesk.getSummary() : { queue:0, production:0, ready:0 };
      var openWO = cases.filter(function(c){ return c.stageKey === 'manufacturing'; });
      var board3 = biCard('3. العمليات والتشغيل', '🏭',
        biRow('أوامر التشغيل المفتوحة', openWO.length, '#7c3aed') +
        biRow('بانتظار الصرف', ops.queue) +
        biRow('داخل الورش حالياً', ops.production) +
        biRow('جاهز للتسليم', ops.ready, '#059669') +
        '<div class="bi-sub">⏲️ زمن الإنتاج التقديري لكل ورشة:' +
          '<ul class="bi-list"><li>توليد: 6 س</li><li>تجميع: 8 س</li><li>صب: 5 س</li><li>تشطيب: 4 س</li></ul></div>');

      // 4) الجهات والتكاليف
      var civCost = cases.filter(function(c){ return c.patientType === 'civilian'; }).reduce(function(s,c){ return s + (c.totalCost||0); }, 0);
      var milCost = cases.filter(function(c){ return c.patientType === 'military'; }).reduce(function(s,c){ return s + (c.totalCost||0); }, 0);
      var totalDebts = (typeof debts !== 'undefined') ? debts.reduce(function(s,d){ return s + (d.due||0) - (d.collected||0); }, 0) : 0;
      var board4 = biCard('4. الجهات والتكاليف', '🏢',
        biRow('التكلفة التراكمية — الجهات المدنية', formatNumber(civCost) + ' ج.م', '#0e7490') +
        biRow('التكلفة المجمعة الافتراضية — العسكرية', formatNumber(milCost) + ' ج.م', '#b45309') +
        biRow('مديونيات الجهات (صافي)', formatNumber(totalDebts) + ' ج.م', '#b91c1c') +
        '<div class="bi-sub">🪖 التكلفة العسكرية تُرحَّل لحساب الديون السيادية (دون مطالبة دفع).</div>');

      // 5) المشتريات والموردين — مقارنة WAC ↔ أعلى سعر
      var compRows = StockCatalog.getAll().slice(0, 8).map(function(it){
        var w = StockCatalog.wac(it); var h = StockCatalog.highestPrice(it);
        var diff = h - w;
        return '<tr><td>' + it.name + '</td><td>' + formatNumber(w) + '</td><td>' + formatNumber(h) + '</td>' +
          '<td style="color:' + (diff>0?'#b45309':'#059669') + '">' + (diff>0?'+':'') + formatNumber(diff) + '</td></tr>';
      }).join('');
      var board5 = biCard('5. المشتريات والموردين', '🏭',
        biRow('عدد الموردين المعتمدين', (typeof suppliers !== 'undefined' ? suppliers.length : 0)) +
        '<div class="bi-sub">⚖️ مقارنة المتوسط المرجح (WAC) ↔ أعلى سعر شراء:' +
          '<table class="bi-table"><thead><tr><th>الصنف</th><th>WAC</th><th>أعلى سعر</th><th>الفرق</th></tr></thead><tbody>' +
          compRows + '</tbody></table></div>');

      el.innerHTML = '<div class="bi-grid">' + board1 + board2 + board3 + board4 + board5 + '</div>';
    }
    window.renderBI = renderBI;



    renderAdminAnalytics();

    renderCatalog();
    renderPricingApproval();
    renderCasesSection();
    renderOverviewCasesCounts();
    renderCompanies();
    renderEmployees();
    renderDebts();
    renderCreditNotes();
    renderSuppliers();
    renderAuditItems('auditPreview', 5);
    renderAuditItems('auditPreview', 5);
    renderAuditFull();
    renderBomAdminReport();

    (function bindCreditNotes() {
      var btn = document.getElementById('btnNewCreditNote');
      if (btn) btn.addEventListener('click', openCreditNoteModal);
      ['closeCreditNoteModal', 'btnCancelCreditNote'].forEach(function (id) {
        var b = document.getElementById(id);
        if (b) b.addEventListener('click', closeCreditNoteModal);
      });
      var conf = document.getElementById('btnConfirmCreditNote');
      if (conf) conf.addEventListener('click', confirmCreditNote);
      ['cnCaseSelect', 'cnType', 'cnAmount'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
          el.addEventListener('change', updateCnPreview);
          el.addEventListener('input', updateCnPreview);
        }
      });
    })();
