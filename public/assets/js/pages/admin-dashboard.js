    var ADMIN_USER = '';
    var casesFilter = 'waiting_return';
    var casesSearchTerm = '';
    var casesPatientTypeFilter = '';
    var adminCaseBuckets = window.__ADMIN_CASE_BUCKETS || { waiting_return: [], in_progress: [], delivered: [] };
    var catalogItems = [];
    var catalogSearchTerm = '';
    var catalogCategoryFilter = 'all';
    var editingCatalogCode = null;
    var editingCatalogId = null;
    var employees = [];
    var companySearchTerm = '';
    var contractCompanies = [];
    var debts = [];
    var auditLogs = [];

    function refreshPaginated() {
      if (!window.TablePagination) return;
      Array.prototype.slice.call(arguments).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) TablePagination.refresh(el);
      });
    }

    var suppliers = [];

    var COMPANIES_STORAGE_KEY = 'clinic_contract_companies';

    function onId(id, event, handler) {
      var el = document.getElementById(id);
      if (el) el.addEventListener(event, handler);
    }

    function bindModal(modalId, openBtnId, closeIds) {
      var modal = document.getElementById(modalId);
      if (!modal) return;
      function close() { modal.classList.remove('open'); }
      function open() { modal.classList.add('open'); }
      var openBtn = openBtnId ? document.getElementById(openBtnId) : null;
      if (openBtn) openBtn.addEventListener('click', open);
      (closeIds || []).forEach(function(id) {
        var btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', close);
      });
      modal.addEventListener('click', function(e) {
        if (e.target === modal) close();
      });
    }

    function bindEmployeeModal() {
      var modal = document.getElementById('employeeModal');
      var form = document.getElementById('employeeForm');
      if (!modal || !form) return;

      function closeEmployeeModal() {
        modal.classList.remove('open');
      }

      function setEmployeeAddMode() {
        var url = new URL(window.location.href);
        if (url.searchParams.has('edit')) {
          url.searchParams.delete('edit');
          window.history.replaceState({}, '', url.pathname + url.search);
        }

        var methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) methodInput.remove();

        form.action = form.dataset.storeUrl || form.getAttribute('action');
        form.reset();

        var title = document.getElementById('employeeModalTitle');
        if (title) title.textContent = form.dataset.addTitle || '➕ إضافة موظف';

        var pwRequired = document.getElementById('employeePasswordRequired');
        var pwHint = document.getElementById('employeePasswordHint');
        if (pwRequired) pwRequired.style.display = '';
        if (pwHint) pwHint.style.display = 'none';

        var pw = form.querySelector('[name="password"]');
        if (pw) pw.setAttribute('data-v-rules', 'required,password');

        var roleSelectWrap = document.getElementById('employeeRoleSelectWrap');
        var roleLockedWrap = document.getElementById('employeeRoleLockedWrap');
        if (roleSelectWrap) roleSelectWrap.style.display = '';
        if (roleLockedWrap) roleLockedWrap.style.display = 'none';

        var lockedRoleInput = roleLockedWrap
          ? roleLockedWrap.querySelector('[name="role_id"]')
          : null;
        if (lockedRoleInput) lockedRoleInput.removeAttribute('name');

        var roleSelect = document.getElementById('employeeRoleSelect');
        if (roleSelect) {
          roleSelect.disabled = false;
          roleSelect.setAttribute('name', 'role_id');
          roleSelect.value = '';
        }

        var statusSelect = form.querySelector('[name="status"]');
        if (statusSelect) statusSelect.value = 'active';
      }

      var addBtn = document.getElementById('btnAddEmployee');
      if (addBtn) {
        addBtn.addEventListener('click', function() {
          setEmployeeAddMode();
          modal.classList.add('open');
        });
      }

      ['closeEmployeeModal', 'cancelEmployeeModal'].forEach(function(id) {
        var btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', closeEmployeeModal);
      });

      modal.addEventListener('click', function(e) {
        if (e.target === modal) closeEmployeeModal();
      });
    }

    window.deleteEmployee = function (id, name) {
      if (!confirm('حذف الموظف «' + name + '»؟ لا يمكن التراجع عن هذا الإجراء.')) return;
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
      fetch('/admin/employees/' + encodeURIComponent(id), {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      })
        .then(function (r) {
          return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function () {
          window.location.reload();
        })
        .catch(function (err) {
          alert((err && err.message) ? err.message : 'تعذّر حذف الموظف.');
        });
    };

    function bindTableFilter(inputId, tableId, countId, suffix) {
      var input = document.getElementById(inputId);
      var el = document.getElementById(tableId);
      if (!input || !el || el.dataset.serverRendered !== '1') return;
      var rowsHost = el.tagName === 'TBODY' ? el : (el.tBodies[0] || el);
      input.addEventListener('input', function() {
        var q = input.value.trim().toLowerCase();
        var visible = 0;
        rowsHost.querySelectorAll('tr[data-search], tr[data-name], tr[data-code], tr[data-role]').forEach(function(row) {
          var hay = (
            (row.dataset.search || '') + ' ' +
            (row.dataset.name || '') + ' ' +
            (row.dataset.code || '') + ' ' +
            (row.querySelector('td') ? row.textContent : '')
          ).toLowerCase();
          var show = !q || hay.indexOf(q) !== -1;
          if (show) {
            delete row.dataset.paginationSkip;
            visible++;
          } else {
            row.dataset.paginationSkip = '1';
          }
        });
        var countEl = document.getElementById(countId);
        if (countEl) countEl.textContent = visible + ' ' + suffix;
        refreshPaginated(tableId);
      });
    }

    bindModal('rankModal', 'btnAddRank', ['closeRankModal', 'cancelRankModal']);
    bindEmployeeModal();
    bindModal('visitTypeModal', 'btnAddVisitType', ['closeVisitTypeModal', 'cancelVisitTypeModal']);
    // bindModal('stockCategoryModal', 'btnAddStockCategory', ['closeStockCategoryModal', 'cancelStockCategoryModal']);
    bindModal('supplierModal', 'btnAddSupplier', ['closeSupplierModal', 'cancelSupplierModal']);
    bindTableFilter('rankSearch', 'ranksTable', 'rankCount', 'رتبة');
    bindTableFilter('visitTypeSearch', 'visitTypesTable', 'visitTypeCount', 'نوع');
    // bindTableFilter('stockCategorySearch', 'stockCategoriesTable', 'stockCategoryCount', 'فئة');
    bindTableFilter('supplierSearch', 'suppliersTable', 'supplierCount', 'مورد');

    function loadCompanies() {
      return contractCompanies.slice();
    }

    function saveCompanies(list) {
      contractCompanies = list.slice();
    }

    function fetchContractCompaniesFromServer(callback) {
      fetch('/admin/companies/list?all=1', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
        .then(function (r) { return r.ok ? r.json() : { data: [] }; })
        .then(function (res) {
          contractCompanies = (res.data || []).map(function (c) {
            return {
              id: c.id,
              name: c.name,
              company_code: c.company_code,
              is_military: !!c.is_military,
            };
          });
          if (callback) callback();
        })
        .catch(function () {
          if (callback) callback();
        });
    }

    function reloadDebts() {
      return debts;
    }

    var sectionTitles = {
      overview: 'لوحة المعلومات — الإدارة العليا',
      bi: 'لوحات القيادة — 5 لوحات',
      catalog: 'الأصناف والأسعار',
      cases: 'متابعة الحالات',
      employees: 'إدارة الموظفين',
      companies: 'جهات التعاقد',
      debts: 'مديونيات جهات التعاقد',
      audit: 'سجل الرقابة الحصين — Immutable Audit Log',
      reports: 'التقارير والتحليلات',
      suppliers: 'الموردون وفواتير المشتريات'
    };

    function dashboardPageUrl(page) {
      var seg = window.location.pathname.split('/').filter(Boolean);
      return '/' + (seg[0] || 'admin') + '/' + page;
    }

    function switchSection(sectionId) {
      if (!document.getElementById('section-' + sectionId)) {
        window.location.href = dashboardPageUrl(sectionId);
        return;
      }
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

    onId('casesSearch', 'input', function(e) {
      casesSearchTerm = e.target.value.trim();
      renderCasesSection();
    });

    onId('casesPatientTypeFilter', 'change', function(e) {
      casesPatientTypeFilter = e.target.value || '';
      renderCasesSection();
    });

    document.querySelectorAll('.nav-menu a[data-section]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        var sectionId = link.getAttribute('data-section');
        if (sectionId && !document.getElementById('section-' + sectionId)) {
          e.preventDefault();
          switchSection(sectionId);
        }
      });
    });

    window.addEventListener('storage', function(e) {
      if (e.key === CasesWorkflow.STORAGE_KEY) {
        renderCasesSection();
        renderOverviewCasesCounts();
        renderAdminAnalytics();
      }
      if (e.key === BomInventory.STORAGE_KEY || e.key === StockCatalog.STORAGE_KEY) {
        renderBomAdminReport();
      }
    });

    function getAdminCaseBucket(key) {
      if (adminCaseBuckets && Array.isArray(adminCaseBuckets[key])) {
        return adminCaseBuckets[key];
      }
      return [];
    }

    function renderOverviewCasesCounts() {
      if (document.getElementById('overviewWaitingCount') &&
          document.getElementById('overviewWaitingCount').dataset.serverRendered === '1') return;
      var waiting = getAdminCaseBucket('waiting_return').length;
      var progress = getAdminCaseBucket('in_progress').length;
      var delivered = getAdminCaseBucket('delivered').length;
      var ow = document.getElementById('overviewWaitingCount');
      var op = document.getElementById('overviewProgressCount');
      var od = document.getElementById('overviewDeliveredCount');
      if (ow) ow.textContent = waiting;
      if (op) op.textContent = progress;
      if (od) od.textContent = delivered;
    }

    function getFilteredCases() {
      var list = getAdminCaseBucket(casesFilter);
      if (casesPatientTypeFilter) {
        list = list.filter(function(c) {
          return (c.patientType || '') === casesPatientTypeFilter;
        });
      }
      if (!casesSearchTerm) return list;
      var term = casesSearchTerm.toLowerCase();
      return list.filter(function(c) {
        return (c.patient || '').toLowerCase().indexOf(term) !== -1 ||
          (c.patientPhone || '').toLowerCase().indexOf(term) !== -1 ||
          (c.quoteId || '').toLowerCase().indexOf(term) !== -1 ||
          (c.pricingRef || '').toLowerCase().indexOf(term) !== -1 ||
          (c.company || '').toLowerCase().indexOf(term) !== -1 ||
          (c.orderRef || '').toLowerCase().indexOf(term) !== -1 ||
          (c.caseNo || '').toLowerCase().indexOf(term) !== -1;
      });
    }

    function renderCasesSection() {
      var waiting = getAdminCaseBucket('waiting_return');
      var progress = getAdminCaseBucket('in_progress');
      var delivered = getAdminCaseBucket('delivered');
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
          hintEl.innerHTML = '';
          hintEl.style.display = 'none';
        } else if (casesFilter === 'in_progress') {
          hintEl.innerHTML = 'العميل رجع بخطاب الموافقة — الشغل جاري في المخزن/الورشة. التسليم للمريض يتم بعد BOM «تام» فقط.';
          hintEl.style.display = 'block';
        } else {
          hintEl.innerHTML = 'تقرير مالي: إجمالي التكلفة للحالات المسلّمة.';
          hintEl.style.display = 'block';
        }
      }

      var head = document.getElementById('casesTableHead');
      var body = document.getElementById('casesTableBody');
      var pipelineCol = '<th style="min-width:320px">مسار الحالة</th>';
      var viewCol = '<th style="width:90px">عرض</th>';

      if (casesFilter === 'waiting_return') {
        head.innerHTML = '<tr><th>المريض</th><th>جهة التعاقد</th><th>رقم عرض السعر</th><th>تاريخ العرض</th><th>أيام الانتظار</th>' + pipelineCol + viewCol + '</tr>';
        body.innerHTML = filtered.length ? filtered.map(function(c) {
          var days = c.quoteDaysWaiting || 0;
          var daysCls = days >= 14 ? ' days-wait-badge urgent' : ' days-wait-badge';
          var tm = CasesWorkflow.getPatientTypeMeta(c.patientType);
          return '<tr>' +
            '<td><strong>' + c.patient + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
            '<td>' + c.company + '</td>' +
            '<td>' + (c.quoteRefHtml || c.quoteId || '—') + '</td>' +
            '<td>' + (c.quoteDate || '—') + '</td>' +
            '<td><span class="' + daysCls.trim() + '">⏱ ' + days + ' يوم</span></td>' +
            '<td><div class="wf-pipeline">' + (c.pipelineHtml || c.stageLabel || '—') + '</div></td>' +
            caseViewCell(c.id) +
            '</tr>';
        }).join('') : '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد حالات مطابقة</td></tr>';
      } else if (casesFilter === 'in_progress') {
        head.innerHTML = '<tr><th>المريض</th><th>جهة التعاقد</th><th>مرحلة الشغل</th><th>BOM</th><th>تاريخ الموافقة</th>' + pipelineCol + viewCol + '</tr>';
        body.innerHTML = filtered.length ? filtered.map(function(c) {
          var bom = c.bom || null;
          var bomLabel = bom ? bom.stageLabel : '—';
          var bomCls = bom ? bom.badgeClass : 'default';
          var tm = CasesWorkflow.getPatientTypeMeta(c.patientType);
          return '<tr>' +
            '<td><strong>' + c.patient + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
            '<td>' + c.company + '</td>' +
            '<td><span class="stage-badge progress">' + (c.manufacturingLabel || '—') + '</span></td>' +
            '<td><span class="stage-badge ' + bomCls + '">' + bomLabel + '</span></td>' +
            '<td>' + (c.approvalDate || '—') + '</td>' +
            '<td><div class="wf-pipeline">' + (c.pipelineHtml || c.stageLabel || '—') + '</div></td>' +
            caseViewCell(c.id) +
            '</tr>';
        }).join('') : '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد حالات مطابقة</td></tr>';
      } else {
        head.innerHTML = '<tr><th>المريض</th><th>جهة التعاقد</th><th>إجمالي التكلفة</th><th>تاريخ ووقت التسليم</th>' + pipelineCol + viewCol + '</tr>';
        body.innerHTML = filtered.length ? filtered.map(function(c) {
          var tm = CasesWorkflow.getPatientTypeMeta(c.patientType);
          return '<tr>' +
            '<td><strong>' + c.patient + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
            '<td>' + c.company + '</td>' +
            '<td class="pricing-total-cell">' + CasesWorkflow.formatMoney(c.totalCost) + '</td>' +
            '<td>' + (c.deliveredAt || '—') + '</td>' +
            '<td><div class="wf-pipeline">' + (c.pipelineHtml || c.stageLabel || '—') + '</div></td>' +
            caseViewCell(c.id) +
            '</tr>';
        }).join('') : '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد حالات مطابقة</td></tr>';
      }

      refreshPaginated('casesTableBody');
    }

    function exportCases(type) {
      var filtered = getFilteredCases();
      var headers, rows, title;
      if (casesFilter === 'waiting_return') {
        title = 'حالات بانتظار رجوع العميل';
        headers = ['المريض', 'جهة التعاقد', 'رقم عرض السعر', 'مرجع التسعير', 'تاريخ العرض', 'أيام الانتظار', 'الحالة'];
        rows = filtered.map(function(c) {
          return [c.patient, c.company, c.quoteId, c.pricingRef || '—', ExportKit.formatDateForExport(c.quoteDate), c.quoteDaysWaiting || 0, c.stageLabel];
        });
      } else if (casesFilter === 'in_progress') {
        title = 'حالات تحت التنفيذ';
        headers = ['المريض', 'جهة التعاقد', 'مرحلة الشغل', 'BOM', 'تاريخ الموافقة'];
        rows = filtered.map(function(c) {
          return [c.patient, c.company, c.manufacturingLabel || '—', c.bom ? c.bom.stageLabel : '—', ExportKit.formatDateForExport(c.approvalDate)];
        });
      } else {
        title = 'تقرير الحالات المسلّمة';
        headers = ['المريض', 'جهة التعاقد', 'إجمالي التكلفة', 'تاريخ ووقت التسليم'];
        rows = filtered.map(function(c) {
          return [c.patient, c.company, c.totalCost, ExportKit.formatDateForExport(c.deliveredAt)];
        });
      }
      if (type === 'excel') ExportKit.toExcel('cases-' + casesFilter, headers, rows);
      else ExportKit.toPDF(title, headers, rows);
    }

    function escapeHtml(s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function caseViewCell(caseId) {
      return '<td class="case-view-cell"><button type="button" class="btn-case-view" onclick="openCaseDetail(\'' + caseId + '\')">' +
        '<span class="btn-case-view__icon" aria-hidden="true">👁️</span><span>عرض</span></button></td>';
    }

    function caseDetailBox(label, value) {
      return '<div class="catalog-detail-box"><div class="dl">' + escapeHtml(label) + '</div><div class="dv">' + (value || '—') + '</div></div>';
    }

    function caseDocPanel(title, innerHtml) {
      return '<div class="case-doc-panel">' +
        '<div class="case-doc-panel__head"><span>' + title + '</span></div>' +
        '<div class="case-doc-panel__body">' + innerHtml + '</div></div>';
    }

    /** خطاب الموافقة: PDF في iframe — أي صورة (jfif/heic/…) في img */
    function approvalLetterPreviewHtml(url, ext) {
      ext = (ext || '').toLowerCase();
      if (ext === 'pdf') {
        return '<iframe src="' + escapeHtml(url) + '" title="خطاب الموافقة"></iframe>';
      }
      return '<img src="' + escapeHtml(url) + '" alt="خطاب الموافقة">';
    }

    function closeCaseDetailModal() {
      var modal = document.getElementById('caseDetailModal');
      var body = document.getElementById('caseDetailModalBody');
      if (modal) modal.classList.remove('open');
      if (body) body.innerHTML = '<p class="case-detail-loading">جاري التحميل...</p>';
      document.body.style.overflow = '';
    }

    function renderCaseDetailModal(data) {
      var body = document.getElementById('caseDetailModalBody');
      var title = document.getElementById('caseDetailModalTitle');
      var ref = document.getElementById('caseDetailModalRef');
      if (!body) return;

      var c = data.case || {};
      var p = data.patient || {};
      var q = data.quote;
      var a = data.approval;

      if (title) title.textContent = 'تفاصيل الحالة';
      if (ref) ref.textContent = [p.name || c.case_no, c.case_no, c.work_order_no].filter(Boolean).join(' · ');

      var metaGrid = '<div class="case-detail-section">' +
        '<h4 class="catalog-modal-section-title">👤 بيانات المريض والحالة</h4>' +
        '<div class="catalog-detail-grid">' +
        caseDetailBox('المريض', escapeHtml(p.name)) +
        caseDetailBox('كود المريض', escapeHtml(p.patient_code)) +
        caseDetailBox('الهاتف', escapeHtml(p.phone)) +
        caseDetailBox('الرقم القومي', escapeHtml(p.national_id)) +
        caseDetailBox('المسار', escapeHtml(p.type_label)) +
        (data.is_military
          ? caseDetailBox('الرتبة العسكرية', escapeHtml(p.rank)) +
            caseDetailBox('الجهة السيادية', escapeHtml(p.sovereign || 'القوات المسلحة'))
          : caseDetailBox('جهة التعاقد', escapeHtml(p.company))) +
        caseDetailBox('رقم الحالة', escapeHtml(c.case_no)) +
        caseDetailBox('أمر التشغيل', escapeHtml(c.work_order_no)) +
        caseDetailBox('مرحلة الحالة', escapeHtml(c.stage_label)) +
        caseDetailBox('تاريخ ووقت التسليم', escapeHtml(c.delivered_at)) +
        caseDetailBox('إجمالي التكلفة', c.total_cost != null ? CasesWorkflow.formatMoney(c.total_cost) : '—') +
        caseDetailBox('المدفوع', c.paid != null ? '<span class="case-detail-paid">' + CasesWorkflow.formatMoney(c.paid) + '</span>' : '—') +
        '</div></div>';

      var quoteSection = '';
      if (q) {
        var itemsRows = (q.items || []).map(function(it) {
          var adjBadge = it.from_adjustments
            ? ' <span class="case-item-source case-item-source--adj" title="أُضيف بواسطة المعدلات">📏 المعدلات</span>'
            : '';
          return '<tr><td>' + escapeHtml(it.name) + adjBadge + '</td><td>' + escapeHtml(it.stock_item_code || '—') + '</td>' +
            '<td class="num">' + it.qty + '</td><td class="num case-detail-paid">' +
            CasesWorkflow.formatMoney(it.amount) + '</td></tr>';
        }).join('');
        quoteSection =
          '<div class="case-detail-section">' +
          '<h4 class="catalog-modal-section-title">📄 عرض السعر — ' + escapeHtml(q.quote_no) + '</h4>' +
          '<div class="catalog-detail-grid">' +
          caseDetailBox('التاريخ', escapeHtml(q.quote_date)) +
          caseDetailBox('الحالة', escapeHtml(q.status_label || q.status)) +
          caseDetailBox('الإجمالي', CasesWorkflow.formatMoney(q.total)) +
          caseDetailBox('الجهة', escapeHtml(q.company_name)) +
          '</div>' +
          '<div class="case-detail-actions">' +
          '<a href="' + escapeHtml(q.print_url) + '" target="_blank" rel="noopener" class="btn-case-doc">🖨️ فتح للطباعة / PDF</a>' +
          '</div>' +
          caseDocPanel('معاينة عرض السعر', '<iframe src="' + escapeHtml(q.print_url) + '" title="عرض السعر"></iframe>') +
          (itemsRows
            ? '<div class="case-detail-table-wrap"><table class="case-detail-table" data-paginate="6"><thead><tr>' +
              '<th>البند</th><th>الكود</th><th class="num">الكمية</th><th class="num">المبلغ</th></tr></thead><tbody>' +
              itemsRows + '</tbody></table></div>'
            : '') +
          '</div>';
      } else if (!data.is_military) {
        quoteSection = '<div class="case-detail-empty">لا يوجد عرض سعر مرتبط بهذه الحالة.</div>';
      }

      var approvalSection = '';
      if (a) {
        var letterPreview = '';
        if (a.has_letter && a.letter_url) {
          letterPreview = approvalLetterPreviewHtml(a.letter_url, a.letter_ext);
        }
        approvalSection =
          '<div class="case-detail-section">' +
          '<h4 class="catalog-modal-section-title">📑 خطاب موافقة الجهة — ' + escapeHtml(a.contract_no) + '</h4>' +
          '<div class="catalog-detail-grid">' +
          caseDetailBox('تاريخ الموافقة', escapeHtml(a.approval_date)) +
          caseDetailBox('رقم الخطاب', escapeHtml(a.letter_ref)) +
          caseDetailBox('المبلغ المعتمد', CasesWorkflow.formatMoney(a.approved_amount)) +
          '</div>';
        if (a.has_letter && a.letter_url) {
          approvalSection +=
            '<div class="case-detail-actions">' +
            '<button type="button" class="btn-case-doc" onclick="openContractLetterView(\'' + escapeHtml(a.letter_url) + '\', \'' +
              escapeHtml(a.contract_no) + '\', \'' + escapeHtml(a.letter_ext || '') + '\')">👁️ عرض بملء الشاشة</button>' +
            (a.download_url
              ? '<a href="' + escapeHtml(a.download_url) + '" target="_blank" class="btn-case-doc btn-case-doc--muted" download>📎 تحميل</a>'
              : '') +
            '</div>' +
            caseDocPanel('معاينة خطاب الموافقة', letterPreview);
        } else {
          approvalSection += '<div class="case-detail-empty">لم يُرفع ملف خطاب الموافقة.</div>';
        }
        approvalSection += '</div>';
      } else if (!data.is_military) {
        approvalSection = '<div class="case-detail-empty">لا يوجد عقد موافقة مسجّل بعد (يُنشأ عند مسح OCR).</div>';
      }

      body.innerHTML = metaGrid + quoteSection + approvalSection;
      if (window.TablePagination) TablePagination.refresh(body);
    }

    function openCaseDetail(caseId) {
      var modal = document.getElementById('caseDetailModal');
      var body = document.getElementById('caseDetailModalBody');
      if (!modal || !body) return;

      body.innerHTML = '<p class="case-detail-loading">جاري التحميل...</p>';
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';

      fetch('/admin/cases/' + encodeURIComponent(caseId) + '/detail', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function(res) {
          if (!res.ok) throw new Error('تعذّر تحميل التفاصيل');
          return res.json();
        })
        .then(renderCaseDetailModal)
        .catch(function() {
          body.innerHTML = '<p class="case-detail-error">تعذّر تحميل تفاصيل الحالة.</p>';
        });
    }
    window.openCaseDetail = openCaseDetail;

    function bindCaseDetailModal() {
      var modal = document.getElementById('caseDetailModal');
      var closeBtn = document.getElementById('closeCaseDetailModal');
      var footerClose = document.getElementById('btnCloseCaseDetailModal');
      if (closeBtn) closeBtn.addEventListener('click', closeCaseDetailModal);
      if (footerClose) footerClose.addEventListener('click', closeCaseDetailModal);
      if (modal) {
        modal.addEventListener('click', function(e) {
          if (e.target === modal) closeCaseDetailModal();
        });
      }
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('open')) closeCaseDetailModal();
      });
    }


    function getFilteredCompanies() {
      return contractCompanies.filter(function(c) {
        return !companySearchTerm || c.name.indexOf(companySearchTerm) !== -1;
      });
    }

    function filterServerCompanyRows() {
      var tbody = document.getElementById('companiesTable');
      if (!tbody || tbody.dataset.serverRendered !== '1') return;
      var search = ((document.getElementById('companySearch') || {}).value || '').trim().toLowerCase();
      var visible = 0;
      tbody.querySelectorAll('tr[data-name]').forEach(function (row) {
        var name = (row.dataset.name || '').toLowerCase();
        var show = !search || name.indexOf(search) !== -1;
        if (show) {
          delete row.dataset.paginationSkip;
          visible++;
        } else {
          row.dataset.paginationSkip = '1';
        }
      });
      var countEl = document.getElementById('companiesCount');
      if (countEl) countEl.textContent = visible + ' جهة';
      refreshPaginated('companiesTable');
    }

    function companyRowHtml(c, idx) {
      var safeName = String(c.name).replace(/"/g, '&quot;');
      return '<tr data-id="' + c.id + '" data-name="' + safeName + '">' +
        '<td class="bulk-select-col"><input type="checkbox" class="bulk-row-select" value="' + c.id + '" aria-label="تحديد"></td>' +
        '<td>' + (idx + 1) + '</td>' +
        '<td><strong>' + c.name + '</strong></td>' +
        '<td><div class="table-actions">' +
        '<button type="button" class="btn-action" onclick="openCompanyEditModal(' + c.id + ', ' + JSON.stringify(c.name) + ')">✏️ تعديل</button>' +
        '<button type="button" class="btn-action danger" onclick="deleteCompany(' + c.id + ', ' + JSON.stringify(c.name) + ')">🗑️ حذف</button>' +
        '</div></td></tr>';
    }

    function renderCompanies() {
      var tbody = document.getElementById('companiesTable');
      if (!tbody) return;

      if (tbody.dataset.serverRendered === '1') {
        filterServerCompanyRows();
        return;
      }

      fetchContractCompaniesFromServer(function () {
        var filtered = getFilteredCompanies();
        var badge = document.getElementById('companiesBadge');
        var countEl = document.getElementById('companiesCount');
        if (badge) badge.textContent = contractCompanies.length + ' جهة';
        if (countEl) countEl.textContent = filtered.length + ' جهة';

        if (!filtered.length) {
          tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد جهات — أضف جهة تعاقد من الحقل أعلاه.</td></tr>';
        } else {
          tbody.innerHTML = filtered.map(function (c, idx) {
            return companyRowHtml(c, idx);
          }).join('');
        }
        refreshPaginated('companiesTable');
      });
    }

    window.renderCompanies = renderCompanies;

    function exportCompanies(type) {
      var data = getFilteredCompanies();
      var headers = ['#', 'اسم الجهة'];
      var rows = data.map(function(c, i) { return [i + 1, c.name]; });
      if (type === 'excel') ExportKit.toExcel('contract-companies', headers, rows);
      else ExportKit.toPDF('جهات التعاقد', headers, rows);
    }

    function getCsrfToken() {
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      return csrfMeta ? csrfMeta.getAttribute('content') : '';
    }

    function normalizeCatalogItem(item) {
      if (!item) return item;
      return {
        id: item.id,
        code: item.code || '',
        name: item.name || '',
        spec: item.spec || '',
        category_id: item.category_id || null,
        category: item.category || '',
        qty: item.qty || 0,
        reserved: item.reserved || 0,
        status: item.status || 'ok',
        prices: (item.prices || []).map(function (p) {
          return {
            id: String(p.id || ''),
            label: p.label || '',
            supplier_id: p.supplier_id || null,
            supplier: p.supplier || '',
            itemCode: p.itemCode || p.supplier_item_code || '',
            amount: Number(p.amount) || 0
          };
        })
      };
    }

    function setCatalogItems(list) {
      catalogItems = (list || []).map(normalizeCatalogItem);
    }

    function fetchCatalogFromServer(callback) {
      fetch('/admin/catalog/items', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
        .then(function (payload) {
          setCatalogItems(payload.data || []);
          if (callback) callback();
        })
        .catch(function () {
          if (callback) callback();
        });
    }

    function loadCatalogItems() {
      if (Array.isArray(window.__CATALOG_ITEMS)) {
        setCatalogItems(window.__CATALOG_ITEMS);
        return;
      }
      fetchCatalogFromServer(function () {
        renderCatalog();
      });
    }

    function findCatalogItemByCode(code) {
      return catalogItems.find(function (i) { return i.code === code; });
    }

    function findCatalogItemById(id) {
      return catalogItems.find(function (i) { return String(i.id) === String(id); });
    }

    function catalogActionsHtml(item) {
      var id = item.id;
      return '<div class="table-actions">' +
        '<button type="button" class="btn-action" title="عرض التفاصيل" onclick="showCatalogDetail(' + id + ')">👁️ عرض</button>' +
        '<button type="button" class="btn-action" title="تعديل الصنف" onclick="editCatalogItem(' + id + ')">✏️ تعديل</button>' +
        '<button type="button" class="btn-action danger" title="حذف الصنف" onclick="deleteCatalogItem(' + id + ')">🗑️ حذف</button>' +
        '</div>';
    }
    function formatPriceRange(prices) {
      var s = StockCatalog.getPriceSummary(prices);
      if (!s.count) return '—';
      if (s.min === s.max) return s.count + ' سعر · ' + StockCatalog.formatPrice(s.min);
      return s.count + ' أسعار · ' + StockCatalog.formatPrice(s.min) + ' – ' + StockCatalog.formatPrice(s.max);
    }

    function getCatalogCategoryNameById(id) {
      var sel = document.getElementById('catalogCategory');
      if (!sel || !id) return '';
      var opt = sel.querySelector('option[value="' + id + '"]');
      return opt ? opt.textContent.trim() : '';
    }

    function getFilteredCatalog() {
      return catalogItems.filter(function(item) {
        var matchCat = catalogCategoryFilter === 'all' ||
          String(item.category_id || '') === String(catalogCategoryFilter) ||
          item.category === getCatalogCategoryNameById(catalogCategoryFilter);
        var matchSearch = !catalogSearchTerm ||
          item.name.indexOf(catalogSearchTerm) !== -1 ||
          item.code.indexOf(catalogSearchTerm) !== -1 ||
          item.spec.indexOf(catalogSearchTerm) !== -1;
        return matchCat && matchSearch;
      });
    }

    function getCatalogSuppliers() {
      return window.__CATALOG_SUPPLIERS || [];
    }

    function supplierSelectHtml(selectedId, selectedName) {
      var html = '<option value="">— اختر المورد —</option>';
      getCatalogSuppliers().forEach(function (s) {
        var selected = (selectedId && String(selectedId) === String(s.id)) ||
          (!selectedId && selectedName && selectedName === s.name);
        html += '<option value="' + s.id + '"' + (selected ? ' selected' : '') + '>' + s.name + '</option>';
      });
      return html;
    }

    function getSupplierNameById(id) {
      var found = getCatalogSuppliers().find(function (s) { return String(s.id) === String(id); });
      return found ? found.name : '';
    }

    function priceRowHtml(price, code) {
      var p = price || {};
      return '<div class="price-row" data-id="' + (p.id || '') + '">' +
        '<div><label>وصف الصنف</label><input type="text" class="price-label" value="' + (p.label || '') + '" placeholder="مثال: ركبة محلية"></div>' +
        '<div><label>المورد</label><select class="price-supplier">' + supplierSelectHtml(p.supplier_id || p.supplierId, p.supplier) + '</select></div>' +
        '<div><label>كود الصنف <span style="font-weight:400;color:var(--text-muted)">(اختياري)</span></label><input type="text" class="price-item-code" value="' + (p.itemCode || p.batch || '') + '" placeholder="ITM-001-01"></div>' +
        '<div><label>السعر (ج.م)</label><input type="number" class="price-amount" min="0" value="' + (p.amount || '') + '" placeholder="45000"></div>' +
        '<button type="button" class="btn-remove-price" onclick="removePriceRow(this)" aria-label="حذف">&times;</button>' +
        '</div>';
    }

    function resetCatalogForm() {
      editingCatalogCode = null;
      editingCatalogId = null;
      document.getElementById('catalogEditCode').value = '';
      document.getElementById('catalogName').value = '';
      document.getElementById('catalogSpec').value = '';
      var catSel = document.getElementById('catalogCategory');
      if (catSel) catSel.selectedIndex = catSel.options.length > 1 ? 1 : 0;
      document.getElementById('catalogQty').value = '0';
      document.getElementById('itemPricesList').innerHTML = priceRowHtml({}, 'NEW');
    }

    function openCatalogForm(item) {
      document.getElementById('catalogForm').classList.add('open');
      if (item) {
        editingCatalogCode = item.code;
        editingCatalogId = item.id;
        document.getElementById('catalogEditCode').value = item.code;
        document.getElementById('catalogName').value = item.name;
        document.getElementById('catalogSpec').value = item.spec || '';
        var catSel = document.getElementById('catalogCategory');
        if (catSel) {
          if (item.category_id) {
            catSel.value = String(item.category_id);
          } else if (item.category) {
            Array.prototype.slice.call(catSel.options).some(function(opt) {
              if (opt.textContent.trim() === item.category) {
                catSel.value = opt.value;
                return true;
              }
              return false;
            });
          }
        }
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
        var supplierSel = row.querySelector('.price-supplier');
        var supplierId = supplierSel ? supplierSel.value : '';
        var itemCode = row.querySelector('.price-item-code').value.trim();
        var rowData = {
          label: row.querySelector('.price-label').value.trim(),
          supplier_id: supplierId ? parseInt(supplierId, 10) : null,
          supplier_item_code: itemCode,
          itemCode: itemCode,
          amount: parseFloat(row.querySelector('.price-amount').value) || 0
        };
        if (id && /^\d+$/.test(String(id))) {
          rowData.id = parseInt(id, 10);
        }
        return rowData;
      }).filter(function(p) { return p.label && p.amount > 0 && p.supplier_id; });
    }

    function renderCatalog() {
      var table = document.getElementById('catalogTable');
      if (!table) return;
      var filtered = getFilteredCatalog();
      var countEl = document.getElementById('catalogCount');
      var filteredEl = document.getElementById('catalogFilteredCount');
      if (countEl) countEl.textContent = catalogItems.length + ' صنف';
      if (filteredEl) filteredEl.textContent = filtered.length + ' صنف';
      table.innerHTML = filtered.map(function(item) {
        return '<tr class="catalog-row-clickable" data-id="' + item.id + '" data-code="' + item.code + '" title="اضغط لعرض التفاصيل">' +
          '<td><strong>' + item.code + '</strong></td>' +
          '<td><strong>' + item.name + '</strong></td>' +
          '<td>' + item.category + '</td>' +
          '<td>' + (item.spec || '—') + '</td>' +
          '<td>' + (item.qty || 0) + '</td>' +
          '<td onclick="event.stopPropagation()">' + catalogActionsHtml(item) + '</td></tr>';
      }).join('');
      refreshPaginated('catalogTable');
    }

    var catalogModalItemId = null;

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
            return '<div class="price-supplier-card">' +
              '<div class="psc-main">' +
                '<div class="psc-supplier">' + (p.supplier || '—') + '</div>' +
                '<div class="psc-meta">' +
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

    function showCatalogDetail(id) {
      var item = findCatalogItemById(id);
      if (!item) return;
      catalogModalItemId = item.id;

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
      catalogModalItemId = null;
    }

    function editCatalogItem(id) {
      var item = findCatalogItemById(id);
      if (item) openCatalogForm(item);
    }

    function deleteCatalogItem(id) {
      var item = findCatalogItemById(id);
      if (!item) return;
      if (!confirm('حذف «' + item.name + '» من الأصناف؟')) return;
      closeCatalogDetailModal();
      fetch('/admin/catalog/' + encodeURIComponent(item.id), {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken()
        }
      })
        .then(function (res) {
          return res.json().then(function (body) {
            if (!res.ok) throw new Error(body.message || 'تعذّر حذف الصنف');
            return body;
          });
        })
        .then(function () {
          catalogItems = catalogItems.filter(function (i) { return String(i.id) !== String(item.id); });
          window.__CATALOG_ITEMS = catalogItems;
          renderCatalog();
        })
        .catch(function (err) {
          alert(err.message || 'تعذّر حذف الصنف');
        });
    }

    function saveCatalogItem() {
      var name = document.getElementById('catalogName').value.trim();
      var spec = document.getElementById('catalogSpec').value.trim() || null;
      var categoryId = document.getElementById('catalogCategory').value;
      var qty = parseInt(document.getElementById('catalogQty').value, 10) || 0;
      var prices = collectPricesFromForm();
      if (!name) {
        alert('يرجى إدخال اسم الصنف');
        return;
      }
      if (!categoryId) {
        alert('يرجى اختيار الفئة');
        return;
      }
      if (!prices.length) {
        alert('يرجى إضافة سعر واحد على الأقل مع اختيار المورد والسعر');
        return;
      }

      var payload = {
        name: name,
        spec: spec,
        category_id: parseInt(categoryId, 10),
        qty: qty,
        prices: prices
      };

      var url = editingCatalogId
        ? '/admin/catalog/' + encodeURIComponent(editingCatalogId)
        : '/admin/catalog';
      var method = editingCatalogId ? 'PUT' : 'POST';

      var saveBtn = document.getElementById('btnSaveCatalog');
      if (saveBtn) saveBtn.disabled = true;

      fetch(url, {
        method: method,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().then(function (body) {
            if (!res.ok) {
              var msg = body.message || (body.errors ? Object.values(body.errors).flat().join('\n') : 'تعذّر حفظ الصنف');
              throw new Error(msg);
            }
            return body;
          });
        })
        .then(function (body) {
          var saved = normalizeCatalogItem(body.item);
          if (editingCatalogId) {
            catalogItems = catalogItems.map(function (i) {
              return String(i.id) === String(saved.id) ? saved : i;
            });
          } else {
            catalogItems = [saved].concat(catalogItems);
          }
          window.__CATALOG_ITEMS = catalogItems;
          closeCatalogForm();
          renderCatalog();
          alert(body.message || 'تم حفظ الصنف — يظهر في لوحة الإدارة والمخزون وتوصيات الطبيب');
        })
        .catch(function (err) {
          alert(err.message || 'تعذّر حفظ الصنف');
        })
        .finally(function () {
          if (saveBtn) saveBtn.disabled = false;
        });
    }

    window.showCatalogDetail = showCatalogDetail;
    window.editCatalogItem = editCatalogItem;
    window.deleteCatalogItem = deleteCatalogItem;
    window.removePriceRow = removePriceRow;

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
    window.exportCatalog = exportCatalog;

    onId('btnToggleCatalogForm', 'click', function() {
      var form = document.getElementById('catalogForm');
      if (!form) return;
      if (form.classList.contains('open')) closeCatalogForm();
      else openCatalogForm(null);
    });
    onId('btnCancelCatalog', 'click', closeCatalogForm);
    onId('btnSaveCatalog', 'click', saveCatalogItem);
    onId('btnAddPriceRow', 'click', function() {
      var list = document.getElementById('itemPricesList');
      if (list) list.insertAdjacentHTML('beforeend', priceRowHtml({}, editingCatalogCode || 'NEW'));
    });
    onId('catalogSearch', 'input', function(e) {
      catalogSearchTerm = e.target.value.trim();
      renderCatalog();
    });
    onId('catalogCategoryFilter', 'change', function(e) {
      catalogCategoryFilter = e.target.value;
      renderCatalog();
    });

    onId('catalogTable', 'click', function(e) {
      var row = e.target.closest('.catalog-row-clickable');
      if (!row) return;
      showCatalogDetail(row.getAttribute('data-id'));
    });

    onId('catalogModalClose', 'click', closeCatalogDetailModal);
    onId('catalogModalCloseBtn', 'click', closeCatalogDetailModal);
    onId('catalogDetailModal', 'click', closeCatalogDetailModal);
    onId('catalogModalEdit', 'click', function() {
      if (!catalogModalItemId) return;
      var id = catalogModalItemId;
      closeCatalogDetailModal();
      editCatalogItem(id);
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeCatalogDetailModal();
    });

    function renderAuditItems(containerId, limit, filtered) {
      var logs = filtered || (limit ? auditLogs.slice(0, limit) : auditLogs);
      var container = document.getElementById(containerId);
      if (!container || container.dataset.serverRendered === '1') return;
      if (!logs.length) {
        container.innerHTML = '<p style="color:var(--text-muted);padding:8px 0">لا توجد حركات مسجَّلة بعد.</p>';
        return;
      }
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
      var tbody = document.getElementById('employeesTableFull') || document.getElementById('employeesTable');
      if (tbody && tbody.dataset.serverRendered === '1') {
        return [];
      }
      var search = document.getElementById('empSearch') ? document.getElementById('empSearch').value.trim() : '';
      var role = document.getElementById('empRoleFilter') ? document.getElementById('empRoleFilter').value : 'all';
      var status = document.getElementById('empStatusFilter') ? document.getElementById('empStatusFilter').value : 'all';
      return ExportKit.filterItems(employees, { search: search, searchKeys: ['name', 'roleLabel'], filterField: 'role', filterValue: role })
        .filter(function(e) { return status === 'all' || e.status === status; });
    }

    function filterServerEmployeeRows() {
      var tbody = document.getElementById('employeesTableFull') || document.getElementById('employeesTable');
      if (!tbody || tbody.dataset.serverRendered !== '1') return;
      var search = (document.getElementById('empSearch') || {}).value || '';
      search = search.trim().toLowerCase();
      var role = (document.getElementById('empRoleFilter') || {}).value || 'all';
      var status = (document.getElementById('empStatusFilter') || {}).value || 'all';
      var visible = 0;
      tbody.querySelectorAll('tr').forEach(function(row) {
        var name = ((row.querySelector('td strong') || {}).textContent || '').toLowerCase();
        var rowRole = row.dataset.role || '';
        var rowStatus = row.dataset.status || '';
        var show = (!search || name.indexOf(search) !== -1)
          && (role === 'all' || rowRole === role)
          && (status === 'all' || rowStatus === status);
        if (show) {
          delete row.dataset.paginationSkip;
          visible++;
        } else {
          row.dataset.paginationSkip = '1';
        }
      });
      var ec = document.getElementById('empCount');
      if (ec) ec.textContent = visible + ' موظف';
      refreshPaginated('employeesTable', 'employeesTableFull');
    }

    function getFilteredDebts() {
      var search = document.getElementById('debtSearch') ? document.getElementById('debtSearch').value.trim() : '';
      var status = document.getElementById('debtStatusFilter') ? document.getElementById('debtStatusFilter').value : 'all';
      return ExportKit.filterItems(debts, { search: search, searchKeys: ['company'], filterField: 'status', filterValue: status });
    }

    function getFilteredAudit() {
      var search = document.getElementById('auditSearch') ? document.getElementById('auditSearch').value.trim() : '';
      return ExportKit.filterItems(auditLogs, { search: search, searchKeys: ['user', 'desc', 'action'] });
    }

    function filterServerAuditRows() {
      var host = document.getElementById('auditListFull');
      var list = document.getElementById('auditItemsList');
      if (!host || host.dataset.serverRendered !== '1' || !list) return;

      var form = document.getElementById('auditFilterForm');
      var searchEl = form ? form.querySelector('[name="search"]') : document.getElementById('auditSearch');
      var tagEl = form ? form.querySelector('[name="tag"]') : document.getElementById('auditTagFilter');
      var fromEl = form ? form.querySelector('[name="date_from"]') : document.getElementById('auditFilterDateFrom');
      var toEl = form ? form.querySelector('[name="date_to"]') : document.getElementById('auditFilterDateTo');

      var search = searchEl ? searchEl.value.trim().toLowerCase() : '';
      var tag = tagEl ? tagEl.value : '';
      var dateFrom = fromEl ? fromEl.value.trim() : '';
      var dateTo = toEl ? toEl.value.trim() : '';

      var visible = 0;
      list.querySelectorAll('.audit-item').forEach(function (item) {
        var itemText = (item.dataset.search || '').toLowerCase();
        var itemTag = item.dataset.tag || '';
        var itemDate = item.dataset.date || '';

        var show = (!search || itemText.indexOf(search) !== -1)
          && (!tag || itemTag === tag)
          && (!dateFrom || (itemDate && itemDate >= dateFrom))
          && (!dateTo || (itemDate && itemDate <= dateTo));

        item.style.display = show ? '' : 'none';
        if (show) visible++;
      });

      var countEl = document.getElementById('auditCount');
      if (countEl) {
        var pageTotal = list.querySelectorAll('.audit-item').length;
        var dbTotal = host.dataset.auditTotal || String(pageTotal);
        var filtering = !!(search || tag || dateFrom || dateTo);
        if (filtering && visible !== pageTotal) {
          countEl.textContent = visible + ' حركة (من ' + pageTotal + ' في الصفحة)';
        } else {
          countEl.textContent = dbTotal + ' حركة';
        }
      }
    }

    function getFilteredSuppliers() {
      var search = document.getElementById('supplierSearch') ? document.getElementById('supplierSearch').value.trim() : '';
      return ExportKit.filterItems(suppliers, { search: search, searchKeys: ['name', 'phone', 'email'] });
    }

    function exportEmployees(type) {
      var tbody = document.getElementById('employeesTableFull') || document.getElementById('employeesTable');
      var headers = ['الاسم', 'البريد', 'الدور', 'الحالة', 'آخر دخول'];
      var rows = [];
      if (tbody && tbody.dataset.serverRendered === '1') {
        tbody.querySelectorAll('tr').forEach(function(row) {
          if (row.style.display === 'none') return;
          var cells = row.querySelectorAll('td');
          if (cells.length < 5) return;
          var offset = row.querySelector('.bulk-checkbox, input[type="checkbox"]') ? 1 : 0;
          if (cells.length < offset + 5) return;
          rows.push([
            (cells[offset].querySelector('strong') || cells[offset]).textContent.trim(),
            cells[offset + 1].textContent.trim(),
            cells[offset + 2].textContent.trim(),
            cells[offset + 3].textContent.trim(),
            ExportKit.formatDateForExport(cells[offset + 4].textContent.trim())
          ]);
        });
      } else {
        rows = getFilteredEmployees().map(function(e) {
          return [e.name, e.email || '—', e.roleLabel, e.status === 'active' ? 'نشط' : 'غير نشط', ExportKit.formatDateForExport(e.lastLogin)];
        });
      }
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
      else ExportKit.toPDF('مديونيات جهات التعاقد', headers, rows);
    }

    function exportAudit(type) {
      var data = getFilteredAudit();
      var headers = ['الوقت', 'المستخدم', 'الوصف', 'العملية'];
      var rows = data.map(function(l) { return [l.time, l.user, l.desc, l.action]; });
      if (type === 'excel') ExportKit.toExcel('سجل_الرقابة', headers, rows);
      else ExportKit.toPDF('سجل الرقابة — Audit Trail', headers, rows);
    }

    function exportSuppliers(type) {
      var tbody = document.getElementById('suppliersTable');
      var headers = ['اسم المورد', 'الهاتف', 'البريد'];
      var rows = [];
      if (tbody && tbody.dataset.serverRendered === '1') {
        tbody.querySelectorAll('tr').forEach(function(row) {
          if (row.style.display === 'none') return;
          var cells = row.querySelectorAll('td');
          if (cells.length < 5) return;
          rows.push([
            (cells[2].querySelector('strong') || cells[2]).textContent.trim(),
            cells[3].textContent.trim(),
            cells[4].textContent.trim(),
          ]);
        });
      } else {
        rows = getFilteredSuppliers().map(function(s) {
          return [s.name, s.phone || '—', s.email || '—'];
        });
      }
      if (type === 'excel') ExportKit.toExcel('الموردون', headers, rows);
      else ExportKit.toPDF('الموردون', headers, rows);
    }

    function formatNumber(n) {
      return n.toLocaleString('ar-EG');
    }

    function renderEmployees() {
      var full = document.getElementById('employeesTableFull');
      var overview = document.getElementById('employeesTable');
      if ((full && full.dataset.serverRendered === '1') || (overview && overview.dataset.serverRendered === '1')) {
        filterServerEmployeeRows();
        return;
      }
      var filtered = getFilteredEmployees();
      var htmlAll = employees.map(function(emp) {
        return empRow(emp);
      }).join('');
      var htmlFiltered = filtered.map(function(emp) {
        return empRow(emp);
      }).join('');
      if (overview) overview.innerHTML = htmlAll;
      if (full) full.innerHTML = htmlFiltered;
      var ec = document.getElementById('empCount');
      if (ec) ec.textContent = filtered.length + ' موظف';
      refreshPaginated('employeesTable', 'employeesTableFull');
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
      refreshPaginated('debtsTable', 'debtsTableFull');
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
        refreshPaginated('creditNotesTable');
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
      refreshPaginated('creditNotesTable');
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
      var tbody = document.getElementById('suppliersTable');
      if (tbody && tbody.dataset.serverRendered === '1') return;
      var filtered = getFilteredSuppliers();
      tbody.innerHTML = filtered.map(function(s) {
        return '<tr>' +
          '<td><strong>' + s.name + '</strong></td>' +
          '<td>' + (s.phone || '—') + '</td>' +
          '<td>' + (s.email || '—') + '</td>' +
          '</tr>';
      }).join('');
      var sc = document.getElementById('supplierCount');
      if (sc) sc.textContent = filtered.length + ' مورد';
      refreshPaginated('suppliersTable');
    }

    function renderAuditFull() {
      var container = document.getElementById('auditListFull');
      if (container && container.dataset.serverRendered === '1') {
        filterServerAuditRows();
        return;
      }
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
    ['auditSearch', 'auditTagFilter', 'auditFilterDateFrom', 'auditFilterDateTo'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', renderAuditFull);
        el.addEventListener('change', renderAuditFull);
      }
    });
    var auditForm = document.getElementById('auditFilterForm');
    if (auditForm) {
      auditForm.addEventListener('submit', function () {
        var host = document.getElementById('auditListFull');
        if (host) host.dataset.auditTotal = '';
      });
    }
    ['supplierSearch'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderSuppliers); el.addEventListener('change', renderSuppliers); }
    });
    function renderAdminAnalytics() {
      return;
    }

    function renderBomAdminReport() {
      if (document.getElementById('section-reports')?.dataset.serverRendered === '1') return;
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
      if (el && el.dataset.serverRendered === '1') return;
    }
    window.renderBI = renderBI;



    function bindOpsOverviewBomModal() {
      var table = document.getElementById('opsOverviewTable');
      var modal = document.getElementById('opsOverviewBomModal');
      if (!table || !modal) return;

      function closeModal() {
        modal.classList.remove('open');
      }

      function openModal(btn) {
        var patient = btn.getAttribute('data-patient') || '—';
        var caseNo = btn.getAttribute('data-case-no') || '—';
        var wo = btn.getAttribute('data-work-order') || '—';
        var items = [];

        try {
          items = JSON.parse(btn.getAttribute('data-items') || '[]');
        } catch (e) {
          items = [];
        }

        var subtitle = document.getElementById('opsOverviewBomSubtitle');
        var tbody = document.getElementById('opsOverviewBomBody');

        if (subtitle) subtitle.textContent = patient + ' · ' + caseNo + ' · ' + wo;

        if (tbody) {
          if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد بنود.</td></tr>';
          } else {
            tbody.innerHTML = items.map(function (item) {
              return '<tr>' +
                '<td style="font-family:monospace;font-size:12px;color:#64748b">' + escapeHtml(item.stock_item_code) + '</td>' +
                '<td style="font-weight:600">' + escapeHtml(item.name || item.stock_item_code) + '</td>' +
                '<td style="text-align:center;font-weight:700">' + escapeHtml(item.qty) + '</td>' +
                '</tr>';
            }).join('');
          }
        }

        modal.classList.add('open');
      }

      table.addEventListener('click', function (e) {
        var btn = e.target.closest('.ops-overview-bom-btn, .workshop-overview-bom-btn');
        if (btn) openModal(btn);
      });

      ['opsOverviewBomClose', 'opsOverviewBomCloseBtn'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', closeModal);
      });

      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
      });
    }

    var activePage = document.body.dataset.activePage || '';

    function safePageInit(fn) {
      try { fn(); } catch (e) { /* عنصر الصفحة غير موجود */ }
    }

    safePageInit(renderAdminAnalytics);
    safePageInit(function () { loadCatalogItems(); renderCatalog(); });
    safePageInit(renderCasesSection);
    safePageInit(bindCaseDetailModal);
    safePageInit(renderOverviewCasesCounts);
    safePageInit(bindOpsOverviewBomModal);
    safePageInit(renderCompanies);
    safePageInit(renderEmployees);
    safePageInit(renderDebts);
    safePageInit(renderCreditNotes);
    safePageInit(renderSuppliers);
    safePageInit(function() { renderAuditItems('auditPreview', 5); });
    safePageInit(renderAuditFull);
    safePageInit(renderBomAdminReport);
    safePageInit(renderBI);

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

    bindTableFilter('companySearch', 'companiesTable', 'companiesCount', 'شركة');

    // ── Patient Track Panel — table + path modal ───────────────────────────
    (function bindPatientTrack() {
      var btn = document.getElementById('patientTrackRefresh');
      var searchInput = document.getElementById('patientTrackSearch');
      var stageFilter = document.getElementById('patientTrackStageFilter');
      var typeFilter = document.getElementById('patientTrackTypeFilter');
      var visitFilter = document.getElementById('patientTrackVisitFilter');
      var tbody = document.getElementById('patientTrackTableBody');
      var modal = document.getElementById('patientTrackModal');
      var detailsModal = document.getElementById('patientDetailsModal');
      if (!tbody && !searchInput) return;

      if (!window.__patientTracksById) window.__patientTracksById = {};

      function escHtml(value) {
        return String(value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function syncTrackCache(tracks) {
        window.__patientTracksById = {};
        (tracks || []).forEach(function (track) {
          if (track && track.id != null) {
            window.__patientTracksById[String(track.id)] = track;
          }
        });
      }

      function stepHtml(step) {
        var status = step.status || 'pending';
        var icon = status === 'done' ? '✓' : (status === 'current' ? '●' : '○');
        return '<span class="patient-track-step patient-track-step--' + status + '" title="' + escHtml(step.label || '') + '">'
          + icon + '<small>' + escHtml(step.label || '') + '</small></span>';
      }

      function contactHtml(track) {
        var parts = [];
        if (track.phone) parts.push('<span dir="ltr">' + escHtml(track.phone) + '</span>');
        if (track.national_id) parts.push('<span dir="ltr">' + escHtml(track.national_id) + '</span>');
        return parts.length ? parts.join('') : '—';
      }

      function detailBox(label, value, opts) {
        opts = opts || {};
        if (value == null || value === '') return '';
        var valueClass = 'dv' + (opts.mono ? ' mono' : '') + (opts.ok ? ' ok' : '');
        return '<div class="catalog-detail-box">'
          + '<div class="dl">' + escHtml(label) + '</div>'
          + '<div class="' + valueClass + '">' + escHtml(String(value)) + '</div>'
          + '</div>';
      }

      function detailSection(title, boxesHtml, extraClass) {
        if (!boxesHtml) return '';
        return '<section class="pd-section' + (extraClass ? ' ' + extraClass : '') + '">'
          + '<h4 class="pd-section-title">' + title + '</h4>'
          + '<div class="catalog-detail-grid">' + boxesHtml + '</div>'
          + '</section>';
      }

      function detailSectionRaw(title, contentHtml, extraClass) {
        if (!contentHtml) return '';
        return '<section class="pd-section' + (extraClass ? ' ' + extraClass : '') + '">'
          + '<h4 class="pd-section-title">' + title + '</h4>'
          + contentHtml
          + '</section>';
      }

      function formatDetailDate(value) {
        if (!value) return '';
        var m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? (m[3] + '/' + m[2] + '/' + m[1]) : value;
      }

      function actionButtonsHtml(trackId) {
        return '<div class="patient-track-action-btns">'
          + '<button type="button" class="btn-action primary btn-view-patient-track" data-track-id="' + escHtml(trackId) + '">📍 عرض المسار</button>'
          + '<button type="button" class="btn-action btn-view-patient-details" data-track-id="' + escHtml(trackId) + '">👤 تفاصيل المريض</button>'
          + '</div>';
      }

      function rowHtml(track) {
        var pathway = track.pathway === 'military' ? 'military' : 'civilian';
        var pathwayLabel = track.pathway === 'military' ? '🪖 عسكري' : '🌐 مدني';
        var subLines = '';
        if (track.case_no) subLines += '<div class="patient-track-cell-sub">' + escHtml(track.case_no) + '</div>';
        if (track.company_name) subLines += '<div class="patient-track-cell-sub">' + escHtml(track.company_name) + '</div>';

        return '<tr class="patient-track-row" data-search="' + escHtml(track.search_hay || '') + '"'
          + ' data-stage-key="' + escHtml(track.stage_key || '') + '"'
          + ' data-pathway="' + escHtml(pathway) + '"'
          + ' data-visit-type-id="' + escHtml(track.visit_type_id != null ? String(track.visit_type_id) : '') + '">'
          + '<td><strong>' + escHtml(track.name || '—') + '</strong>' + subLines + '</td>'
          + '<td><span class="patient-type-badge ' + pathway + '">' + pathwayLabel + '</span></td>'
          + '<td class="patient-track-contact">' + contactHtml(track) + '</td>'
          + '<td><span class="patient-track-stage-inline">' + escHtml(track.stage_label || '—') + '</span>'
          + '<span class="patient-track-percent-inline">' + (track.progress_percent || 0) + '%</span></td>'
          + '<td class="col-actions">' + actionButtonsHtml(track.id) + '</td>'
          + '</tr>';
      }

      function openPatientDetailsModal(track) {
        if (!detailsModal || !track) return;

        var details = track.patient_details || {};
        var titleEl = document.getElementById('patientDetailsModalTitle');
        var metaEl = document.getElementById('patientDetailsModalMeta');
        var bodyEl = document.getElementById('patientDetailsModalBody');
        var pathway = track.pathway === 'military' ? 'military' : 'civilian';
        var pathwayLabel = track.pathway === 'military' ? '🪖 عسكري' : '🌐 مدني';
        var patientName = details.name || track.name || '—';
        var stageLabel = details.current_stage_label || track.stage_label || '—';

        if (titleEl) titleEl.textContent = 'تفاصيل المريض';
        if (metaEl) metaEl.textContent = details.patient_code ? ('رقم المريض: ' + details.patient_code) : '—';

        var contactBoxes = ''
          + detailBox('الهاتف', details.phone || track.phone, { mono: true })
          + detailBox('الرقم القومي', details.national_id || track.national_id, { mono: true });

        var entityBoxes = pathway === 'military'
          ? detailBox('الجهة السيادية', details.sovereign_entity || details.display_entity)
            + detailBox('الرتبة', details.rank)
          : detailBox('جهة التعاقد', details.company_name || details.display_entity || track.company_name);

        var fileBoxes = ''
          + detailBox('تاريخ التسجيل', formatDetailDate(details.registered_at))
          + detailBox('آخر زيارة', formatDetailDate(details.last_visit_at))
          + detailBox('حالة الملف', details.status_label, { ok: details.status === 'active' })
          + detailBox('كود التتبع', details.tracking_uid || details.patient_qr, { mono: true })
          + detailBox('عدد الحالات', details.cases_count);

        var activeBoxes = '';
        if (details.active_case) {
          activeBoxes = detailBox('رقم الحالة', details.active_case.case_no, { mono: true })
            + detailBox('رقم الطلب', details.active_case.order_ref, { mono: true })
            + detailBox('مرحلة الحالة', details.active_case.stage_label);
        }

        var casesHtml = '';
        if (details.cases && details.cases.length) {
          casesHtml = detailSectionRaw('📋 سجل الحالات',
            '<div class="pd-table-wrap"><table class="patient-details-cases-table"><thead><tr>'
            + '<th>رقم الحالة</th><th>الطلب</th><th>المرحلة</th><th>التسليم</th></tr></thead><tbody>'
            + details.cases.map(function (c) {
              return '<tr><td class="mono">' + escHtml(c.case_no || '—') + '</td>'
                + '<td class="mono">' + escHtml(c.order_ref || '—') + '</td>'
                + '<td>' + escHtml(c.stage_label || '—') + '</td>'
                + '<td>' + escHtml(formatDetailDate(c.delivered_at) || '—') + '</td></tr>';
            }).join('')
            + '</tbody></table></div>',
            'pd-section--muted');
        }

        if (bodyEl) {
          bodyEl.innerHTML = '<div class="pd-hero">'
            + '<div class="pd-hero-main">'
            + '<span class="pd-avatar" aria-hidden="true">👤</span>'
            + '<div><p class="pd-name">' + escHtml(patientName) + '</p>'
            + '<p class="pd-code">' + escHtml(details.patient_code || '—') + '</p></div>'
            + '</div>'
            + '<div class="pd-hero-badges">'
            + '<span class="patient-type-badge ' + pathway + '">' + escHtml(details.patient_type_label || pathwayLabel) + '</span>'
            + '<span class="pd-stage-pill">📍 ' + escHtml(stageLabel) + '</span>'
            + '</div></div>'
            + detailSection('📞 بيانات التواصل', contactBoxes)
            + detailSection('🏢 ' + (pathway === 'military' ? 'الجهة والرتبة' : 'جهة التعاقد'), entityBoxes)
            + detailSection('📁 الملف والتتبع', fileBoxes, 'pd-section--muted')
            + (activeBoxes ? detailSection('⚡ الحالة النشطة', activeBoxes, 'pd-section--highlight') : '')
            + casesHtml
            || '<p class="patient-details-empty">لا توجد تفاصيل لهذا المريض.</p>';
        }

        detailsModal.classList.add('open');
      }

      function closePatientDetailsModal() {
        if (detailsModal) detailsModal.classList.remove('open');
      }

      function journeyPreviewBtnHtml(preview) {
        if (!preview || !preview.url) return '';
        return '<button type="button" class="patient-track-doc-btn"'
          + ' data-preview-type="' + escHtml(preview.type || '') + '"'
          + ' data-preview-url="' + escHtml(preview.url) + '"'
          + ' data-preview-ext="' + escHtml(preview.ext || '') + '"'
          + ' data-preview-title="' + escHtml(preview.title || preview.label || '') + '"'
          + ' data-preview-contract="' + escHtml(preview.contract_no || '') + '"'
          + ' title="' + escHtml(preview.label || 'عرض') + '">'
          + '📄 ' + escHtml(preview.label || 'عرض')
          + '</button>';
      }

      function openJourneyQuotePreview(url, title) {
        var modal = document.getElementById('journeyQuotePreviewModal');
        var titleEl = document.getElementById('journeyQuotePreviewTitle');
        var bodyEl = document.getElementById('journeyQuotePreviewBody');
        if (!modal || !bodyEl) {
          window.open(url, '_blank', 'noopener');
          return;
        }
        if (titleEl) titleEl.textContent = title || '🧾 عرض السعر';
        bodyEl.innerHTML = '<iframe src="' + escHtml(url) + '" title="عرض السعر" class="journey-quote-preview-frame"></iframe>';
        modal.style.display = 'flex';
      }

      function closeJourneyQuotePreview() {
        var modal = document.getElementById('journeyQuotePreviewModal');
        var bodyEl = document.getElementById('journeyQuotePreviewBody');
        if (modal) modal.style.display = 'none';
        if (bodyEl) bodyEl.innerHTML = '';
      }

      function handleJourneyPreviewClick(event) {
        var btn = event.target.closest('.patient-track-doc-btn');
        if (!btn) return;
        event.preventDefault();
        event.stopPropagation();

        var type = btn.getAttribute('data-preview-type') || '';
        var url = btn.getAttribute('data-preview-url') || '';
        if (!url) return;

        if (type === 'approval_letter' && typeof window.openContractLetterView === 'function') {
          window.openContractLetterView(
            url,
            btn.getAttribute('data-preview-contract') || btn.getAttribute('data-preview-title') || '',
            btn.getAttribute('data-preview-ext') || ''
          );
          return;
        }

        if (type === 'quote') {
          openJourneyQuotePreview(url, btn.getAttribute('data-preview-title') || 'عرض السعر');
        }
      }

      function journeyTimelineHtml(events) {
        if (!events || !events.length) {
          return '<p class="patient-track-journey-empty">لا توجد أحداث مسجّلة بعد في دورة هذا المريض.</p>';
        }

        return '<h4 class="patient-track-journey-title">📋 سجل الدورة — كل ما حدث</h4>'
          + '<div class="patient-track-timeline">'
          + events.map(function (ev) {
            var lines = (ev.lines || []).map(function (line) {
              return '<li>' + escHtml(line) + '</li>';
            }).join('');
            var link = ev.link && ev.link.url
              ? '<a class="patient-track-journey-link" href="' + escHtml(ev.link.url) + '" target="_blank" rel="noopener">' + escHtml(ev.link.label || 'عرض') + '</a>'
              : '';
            var previewBtn = journeyPreviewBtnHtml(ev.preview);
            return '<article class="patient-track-timeline-item">'
              + '<div class="patient-track-timeline-marker" aria-hidden="true"></div>'
              + '<div class="patient-track-timeline-body">'
              + '<div class="patient-track-timeline-layout">'
              + '<div class="patient-track-timeline-main">'
              + '<div class="patient-track-timeline-head">'
              + '<span class="patient-track-timeline-cat">' + escHtml(ev.category_label || '') + '</span>'
              + '<time class="patient-track-timeline-time">' + escHtml(ev.at_label || '—') + '</time>'
              + '</div>'
              + '<strong class="patient-track-timeline-title">' + escHtml(ev.title || '—') + '</strong>'
              + (lines ? '<ul class="patient-track-timeline-lines">' + lines + '</ul>' : '')
              + link
              + '</div>'
              + previewBtn
              + '</div>'
              + '</div></article>';
          }).join('')
          + '</div>';
      }

      function openTrackModal(track) {
        if (!modal || !track) return;

        var pathway = track.pathway === 'military' ? 'military' : 'civilian';
        var pathwayLabel = track.pathway === 'military' ? '🪖 عسكري' : '🌐 مدني';
        var metaParts = [];
        if (track.case_no) metaParts.push(track.case_no);
        if (track.company_name) metaParts.push(track.company_name);

        var titleEl = document.getElementById('patientTrackModalTitle');
        var metaEl = document.getElementById('patientTrackModalMeta');
        var nameEl = document.getElementById('patientTrackModalName');
        var badgeEl = document.getElementById('patientTrackModalBadge');
        var percentEl = document.getElementById('patientTrackModalPercent');
        var stageEl = document.getElementById('patientTrackModalStage');
        var barEl = document.getElementById('patientTrackModalBar');
        var stepsEl = document.getElementById('patientTrackModalSteps');
        var noteEl = document.getElementById('patientTrackModalPathNote');
        var journeyEl = document.getElementById('patientTrackModalJourney');

        if (titleEl) titleEl.textContent = '📍 مسار المريض — ' + (track.name || '—');
        if (metaEl) metaEl.textContent = metaParts.join(' · ') || '—';
        if (nameEl) nameEl.textContent = track.name || '—';
        if (badgeEl) {
          badgeEl.textContent = pathwayLabel;
          badgeEl.className = 'patient-type-badge ' + pathway;
        }
        if (percentEl) percentEl.textContent = (track.progress_percent || 0) + '%';
        if (stageEl) stageEl.textContent = track.stage_label || '—';
        if (barEl) {
          barEl.style.width = (track.progress_percent || 0) + '%';
          barEl.parentElement.setAttribute('aria-valuenow', String(track.progress_percent || 0));
        }
        if (stepsEl) stepsEl.innerHTML = (track.steps || []).map(stepHtml).join('');
        if (noteEl) {
          noteEl.textContent = pathway === 'military'
            ? 'المسار العسكري: 6 مراحل — بدون تسعير واعتماد جهة التأمين.'
            : 'المسار المدني: 7 مراحل — يشمل التسعير واعتماد التشغيل وموافقة الجهة.';
        }
        if (journeyEl) journeyEl.innerHTML = journeyTimelineHtml(track.journey || []);

        modal.classList.add('open');
      }

      function closeTrackModal() {
        if (modal) modal.classList.remove('open');
      }

      function resolveTrack(button) {
        var id = button.getAttribute('data-track-id');
        return id != null ? window.__patientTracksById[String(id)] : null;
      }

      if (tbody) {
        tbody.addEventListener('click', function (event) {
          var trackButton = event.target.closest('.btn-view-patient-track');
          if (trackButton) {
            var track = resolveTrack(trackButton);
            if (track) openTrackModal(track);
            return;
          }
          var detailsButton = event.target.closest('.btn-view-patient-details');
          if (detailsButton) {
            var detailsTrack = resolveTrack(detailsButton);
            if (detailsTrack) openPatientDetailsModal(detailsTrack);
          }
        });
      }

      var journeyElRoot = document.getElementById('patientTrackModalJourney');
      if (journeyElRoot) {
        journeyElRoot.addEventListener('click', handleJourneyPreviewClick);
      }

      var quotePreviewModal = document.getElementById('journeyQuotePreviewModal');
      var btnCloseQuotePreview = document.getElementById('btnCloseJourneyQuotePreview');
      if (btnCloseQuotePreview) {
        btnCloseQuotePreview.addEventListener('click', closeJourneyQuotePreview);
      }
      if (quotePreviewModal) {
        quotePreviewModal.addEventListener('click', function (event) {
          if (event.target === quotePreviewModal) closeJourneyQuotePreview();
        });
      }

      function applyPatientTrackFilter() {
        var term = (searchInput && searchInput.value || '').toLowerCase().trim();
        var stage = stageFilter ? stageFilter.value : '';
        var pathway = typeFilter ? typeFilter.value : '';
        var visitType = visitFilter ? visitFilter.value : '';
        var rows = tbody ? tbody.querySelectorAll('.patient-track-row') : [];
        var visible = 0;

        rows.forEach(function (row) {
          var hay = row.getAttribute('data-search') || '';
          var rowStage = row.getAttribute('data-stage-key') || '';
          var rowPathway = row.getAttribute('data-pathway') || '';
          var rowVisitType = row.getAttribute('data-visit-type-id') || '';
          var show = (!term || hay.indexOf(term) !== -1)
            && (!stage || rowStage === stage)
            && (!pathway || rowPathway === pathway)
            && (!visitType || rowVisitType === visitType);
          row.style.display = show ? '' : 'none';
          if (show) visible++;
        });

        var countEl = document.getElementById('patientTrackFilterCount');
        if (countEl) {
          countEl.textContent = (term || stage || pathway || visitType)
            ? (visible + ' من ' + rows.length + ' مريض')
            : (rows.length + ' مريض');
        }
      }

      function patientTrackQueryParams() {
        var params = new URLSearchParams();
        var search = searchInput ? searchInput.value.trim() : '';
        var stage = stageFilter ? stageFilter.value : '';
        var pathway = typeFilter ? typeFilter.value : '';
        var visitType = visitFilter ? visitFilter.value : '';
        if (search) params.set('search', search);
        if (stage) params.set('stage', stage);
        if (pathway) params.set('patient_type', pathway);
        if (visitType) params.set('visit_type', visitType);
        return params.toString();
      }

      if (searchInput) {
        searchInput.addEventListener('input', applyPatientTrackFilter);
      }
      if (stageFilter) {
        stageFilter.addEventListener('change', function () {
          applyPatientTrackFilter();
          if (btn) btn.click();
        });
      }
      if (typeFilter) {
        typeFilter.addEventListener('change', function () {
          applyPatientTrackFilter();
          if (btn) btn.click();
        });
      }
      if (visitFilter) {
        visitFilter.addEventListener('change', function () {
          applyPatientTrackFilter();
          if (btn) btn.click();
        });
      }
      applyPatientTrackFilter();

      var closeBtn = document.getElementById('closePatientTrackModal');
      var closeFooterBtn = document.getElementById('btnClosePatientTrackModal');
      if (closeBtn) closeBtn.addEventListener('click', closeTrackModal);
      if (closeFooterBtn) closeFooterBtn.addEventListener('click', closeTrackModal);
      if (modal) {
        modal.addEventListener('click', function (e) {
          if (e.target === modal) closeTrackModal();
        });
      }

      var closeDetailsBtn = document.getElementById('closePatientDetailsModal');
      var closeDetailsFooterBtn = document.getElementById('btnClosePatientDetailsModal');
      if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', closePatientDetailsModal);
      if (closeDetailsFooterBtn) closeDetailsFooterBtn.addEventListener('click', closePatientDetailsModal);
      if (detailsModal) {
        detailsModal.addEventListener('click', function (e) {
          if (e.target === detailsModal) closePatientDetailsModal();
        });
      }

      if (!btn) return;

      btn.addEventListener('click', function () {
        btn.disabled = true;
        var seg = window.location.pathname.split('/').filter(Boolean);
        var prefix = '/' + (seg[0] || 'admin');
        var query = patientTrackQueryParams();
        var url = prefix + '/patient-tracks/list' + (query ? ('?' + query) : '');

        fetch(url, {
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var badge = document.getElementById('patientTrackBadge');
            if (badge) badge.textContent = data.length + ' مريض';

            syncTrackCache(data);

            if (!tbody) return;

            if (!data.length) {
              tbody.innerHTML = '<tr><td colspan="5" class="patient-track-empty">✅ لا يوجد مرضى مطابقون للفلتر الحالي.</td></tr>';
            } else {
              tbody.innerHTML = data.map(rowHtml).join('');
              if (window.TablePagination) TablePagination.refreshById('patientTrackTableBody');
            }

            applyPatientTrackFilter();
          })
          .catch(function () { /* silent */ })
          .finally(function () {
            btn.disabled = false;
          });
      });
    })();

    (function initAdminReturnsPage() {
      var section = document.getElementById('section-returns');
      if (!section) return;

      function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }

      function visibleNoteRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.return-note-row')).filter(function (row) {
          return row.style.display !== 'none';
        });
      }

      function visibleLineRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.return-line-row')).filter(function (row) {
          return row.style.display !== 'none';
        });
      }

      function applyNoteFilters() {
        var term = (document.getElementById('returnNoteSearch') || {}).value || '';
        term = term.trim().toUpperCase();
        var status = (document.getElementById('returnNoteStatusFilter') || {}).value || '';
        var rows = document.querySelectorAll('.return-note-row');
        var visible = 0;
        rows.forEach(function (row) {
          var matchTerm = !term || (row.dataset.search || '').toUpperCase().indexOf(term) !== -1;
          var matchStatus = !status || row.dataset.status === status;
          row.style.display = (matchTerm && matchStatus) ? '' : 'none';
          if (matchTerm && matchStatus) visible++;
        });
        var countEl = document.getElementById('returnNoteFilterCount');
        if (countEl) countEl.textContent = visible + ' طلب';
        refreshPaginated('returnNotesTableBody');
      }

      function applyLineFilters() {
        var term = (document.getElementById('returnLineSearch') || {}).value || '';
        term = term.trim().toUpperCase();
        var rows = document.querySelectorAll('.return-line-row');
        var visible = 0;
        rows.forEach(function (row) {
          var match = !term || (row.dataset.search || '').toUpperCase().indexOf(term) !== -1;
          row.style.display = match ? '' : 'none';
          if (match) visible++;
        });
        var countEl = document.getElementById('returnLineFilterCount');
        if (countEl) countEl.textContent = visible + ' بند';
        refreshPaginated('returnLinesTableBody');
      }

      onId('returnNoteSearch', 'input', applyNoteFilters);
      onId('returnNoteStatusFilter', 'change', applyNoteFilters);
      onId('returnLineSearch', 'input', applyLineFilters);

      function detailRow(label, value) {
        return '<div class="admin-return-detail-row">' +
          '<span class="admin-return-detail-label">' + label + '</span>' +
          '<span class="admin-return-detail-value">' + value + '</span></div>';
      }

      function getReturnNoteLines(row) {
        var id = row.dataset.id || row.getAttribute('data-id');
        var map = window.__ADMIN_RETURN_NOTE_LINES || {};
        if (id != null) {
          if (Array.isArray(map[id]) && map[id].length) return map[id];
          if (Array.isArray(map[String(id)]) && map[String(id)].length) return map[String(id)];
        }
        return [];
      }

      window.closeReturnNoteDetail = function () {
        var modal = document.getElementById('returnNoteDetailModal');
        if (modal) modal.style.display = 'none';
      };

      window.openReturnNoteDetail = function (btn) {
        var row = btn.closest('.return-note-row');
        if (!row) return;
        var d = row.dataset;
        var lines = getReturnNoteLines(row);

        var title = document.getElementById('returnNoteModalTitle');
        var subtitle = document.getElementById('returnNoteModalSubtitle');
        var body = document.getElementById('returnNoteModalBody');
        var modal = document.getElementById('returnNoteDetailModal');

        if (title) title.textContent = '↩️ ' + esc(d.returnNo);
        if (subtitle) subtitle.textContent = esc(d.patient) + ' · ' + esc(d.bomNo) + ' · ' + esc(d.workOrder);

        var linesHtml = lines.length
          ? '<div class="admin-return-lines-table-wrap"><table class="admin-return-lines-table">' +
            '<thead><tr><th>الصنف</th><th>الباركود</th><th>الكمية المرجعة</th><th>السبب</th></tr></thead><tbody>' +
            lines.map(function (ln) {
              return '<tr>' +
                '<td><strong>' + esc(ln.name) + '</strong><br><code>' + esc(ln.code) + '</code></td>' +
                '<td><code>' + esc(ln.barcode || '—') + '</code></td>' +
                '<td><strong style="color:#059669;">' + (ln.returned || 0) + '</strong></td>' +
                '<td>' + esc(ln.reason || '—') + '</td></tr>';
            }).join('') + '</tbody></table></div>'
          : '<p style="color:var(--text-muted);">لا بنود.</p>';

        if (body) {
          body.innerHTML =
            '<div class="admin-return-detail-grid">' +
            detailRow('رقم الطلب', '<strong style="font-family:monospace;">' + esc(d.returnNo) + '</strong>') +
            detailRow('BOM', esc(d.bomNo)) +
            detailRow('أمر التشغيل', '<span style="font-family:monospace;color:#4f46e5;">' + esc(d.workOrder) + '</span>') +
            detailRow('رقم الحالة', esc(d.caseNo)) +
            detailRow('المريض', '<strong>' + esc(d.patient) + '</strong>') +
            detailRow('مرجع الطلب', esc(d.orderRef)) +
            detailRow('الحالة', esc(d.statusLabel)) +
            detailRow('سبب الارتجاع', esc(d.reason)) +
            detailRow('أرسله (التشغيل)', esc(d.createdBy)) +
            detailRow('تاريخ الإرسال', esc(d.authorizedAt)) +
            (d.completedAt !== '—' ? detailRow('تاريخ الاستلام', esc(d.completedAt)) : '') +
            '</div>' +
            '<h4 class="admin-return-lines-title">بنود الطلب</h4>' + linesHtml;
        }

        if (modal) modal.style.display = 'flex';
      };

      var modal = document.getElementById('returnNoteDetailModal');
      if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeReturnNoteDetail(); });
      onId('btnCloseReturnNoteDetail', 'click', closeReturnNoteDetail);
      onId('btnReturnNoteModalClose', 'click', closeReturnNoteDetail);

      window.exportAdminReturnNotes = function () {
        var rows = visibleNoteRows();
        if (!rows.length) { alert('لا توجد بيانات للتصدير'); return; }
        var headers = ['رقم الطلب', 'BOM', 'أمر التشغيل', 'المريض', 'سبب الارتجاع', 'البنود', 'الحالة', 'أرسله', 'تاريخ الإرسال', 'تاريخ الاستلام'];
        var data = rows.map(function (row) {
          var d = row.dataset;
          var lines = getReturnNoteLines(row);
          var linesTxt = lines.map(function (ln) {
            return (ln.name || ln.code) + ' ×' + (ln.returned || 0);
          }).join(' · ');
          return [
            d.returnNo, d.bomNo, d.workOrder, d.patient, d.reason, linesTxt,
            d.statusLabel, d.createdBy,
            ExportKit.formatDateForExport(d.authorizedAt),
            ExportKit.formatDateForExport(d.completedAt),
          ];
        });
        ExportKit.toExcel('return-requests', headers, data);
      };

      window.exportAdminReturnLinesDetail = function () {
        var all = window.__ADMIN_RETURN_LINES_EXPORT || [];
        var term = ((document.getElementById('returnLineSearch') || {}).value || '').trim().toUpperCase();
        var filtered = all.filter(function (row) {
          if (!term) return true;
          var hay = [
            row.return_no, row.patient_name, row.stock_item_code,
            row.item_name, row.barcode, row.work_order_no,
          ].join(' ').toUpperCase();
          return hay.indexOf(term) !== -1;
        });
        if (!filtered.length) { alert('لا توجد بيانات للتصدير'); return; }
        var headers = [
          'رقم الطلب', 'الحالة', 'BOM', 'أمر التشغيل', 'رقم الحالة', 'المريض', 'مرجع الطلب',
          'كود الصنف', 'اسم الصنف', 'الباركود', 'الكمية المرجعة',
          'سبب الارتجاع', 'أرسله', 'تاريخ الإرسال', 'تاريخ الاستلام',
        ];
        var data = filtered.map(function (row) {
          return [
            row.return_no, row.status, row.bom_no, row.work_order_no, row.case_no,
            row.patient_name, row.order_ref, row.stock_item_code, row.item_name, row.barcode,
            row.qty_returned, row.reason, row.sent_by,
            ExportKit.formatDateForExport(row.sent_at),
            ExportKit.formatDateForExport(row.received_at),
          ];
        });
        ExportKit.toExcel('return-lines-detail', headers, data);
      };
    })();

    (function bindAdminExcelExports() {
      document.addEventListener('click', function (event) {
        var tableBtn = event.target.closest('[data-export-table]');
        if (tableBtn && window.ExportKit && ExportKit.fromVisibleTable) {
          event.preventDefault();
          ExportKit.fromVisibleTable(tableBtn.getAttribute('data-export-table'), {
            filename: tableBtn.getAttribute('data-export-filename') || 'export',
          });
          return;
        }

        var auditBtn = event.target.closest('[data-export-audit]');
        if (auditBtn && window.ExportKit && ExportKit.fromAuditList) {
          event.preventDefault();
          ExportKit.fromAuditList(auditBtn.getAttribute('data-export-audit'), {
            filename: auditBtn.getAttribute('data-export-filename') || 'audit-log',
          });
          return;
        }

        var permBtn = event.target.closest('[data-export-permissions]');
        if (permBtn && window.ExportKit && ExportKit.fromPermissions) {
          event.preventDefault();
          ExportKit.fromPermissions(permBtn.getAttribute('data-export-filename') || 'permissions');
        }
      });

      function slugifyFilename(text) {
        return String(text || 'export')
          .replace(/[^\w\u0600-\u06FF]+/g, '_')
          .replace(/^_+|_+$/g, '')
          .slice(0, 48) || 'export';
      }

      function panelHasExport(panel) {
        return !!panel.querySelector('.btn-export.excel, [data-export-table], [data-export-audit], [data-export-permissions], a[href*="export"]');
      }

      function makeExportButton(table, filename) {
        if (!table.id) {
          table.id = 'admin-export-table-' + Math.random().toString(36).slice(2, 9);
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-export excel';
        btn.setAttribute('data-export-table', '#' + table.id);
        btn.setAttribute('data-export-filename', filename);
        btn.textContent = '📊 Excel';
        return btn;
      }

      function injectTableExport(host, table, filename) {
        if (!table || !host) return;
        if (host.querySelector('[data-export-table="' + '#' + table.id + '"]')) return;

        var toolbar = host.querySelector('.data-toolbar');
        var btn = makeExportButton(table, filename);
        var wrap = document.createElement('div');
        wrap.className = 'export-btns';
        wrap.appendChild(btn);

        if (toolbar) {
          var count = toolbar.querySelector('.toolbar-count');
          if (count) toolbar.insertBefore(wrap, count);
          else toolbar.appendChild(wrap);
          return;
        }

        var header = host.querySelector('.panel-header, .bi-card-head');
        if (header) {
          var actions = header.querySelector('.patient-track-actions, .perm-header-actions');
          if (actions) actions.insertBefore(btn, actions.firstChild);
          else header.appendChild(wrap);
          return;
        }

        if (host.classList.contains('report-card')) {
          var title = host.querySelector('h4');
          if (title) {
            title.insertAdjacentElement('afterend', wrap);
          }
        }
      }

      document.querySelectorAll('.panel, .bi-card, .ops-overview-panel, .report-card').forEach(function (panel) {
        if (panelHasExport(panel)) return;

        panel.querySelectorAll('table').forEach(function (table) {
          if (!table.querySelector('thead')) return;
          if (table.closest('.perm-hidden-matrix, .catalog-modal, #patientTrackModal')) return;

          var heading = panel.querySelector('h3, h4');
          var filename = slugifyFilename(heading ? heading.textContent : 'export');
          var biWrap = table.closest('.bi-table-wrap');
          if (biWrap) {
            var sectionTitle = biWrap.previousElementSibling;
            if (sectionTitle && sectionTitle.classList.contains('bi-section-title') && !sectionTitle.querySelector('[data-export-table]')) {
              sectionTitle.appendChild(makeExportButton(table, slugifyFilename(sectionTitle.textContent)));
            }
            return;
          }

          injectTableExport(panel, table, filename);
        });
      });
    })();
