    var ADMIN_USER = '';
    var pricingApprovalSearch = '';
    var pricingApprovalFilter = 'awaiting_admin_approval';
    var selectedPricingId = null;
    var casesFilter = 'waiting_return';
    var casesSearchTerm = '';
    var catalogItems = [];
    var catalogSearchTerm = '';
    var catalogCategoryFilter = 'all';
    var editingCatalogCode = null;
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
    bindModal('stockCategoryModal', 'btnAddStockCategory', ['closeStockCategoryModal', 'cancelStockCategoryModal']);
    bindModal('supplierModal', 'btnAddSupplier', ['closeSupplierModal', 'cancelSupplierModal']);
    bindTableFilter('rankSearch', 'ranksTable', 'rankCount', 'رتبة');
    bindTableFilter('visitTypeSearch', 'visitTypesTable', 'visitTypeCount', 'نوع');
    bindTableFilter('stockCategorySearch', 'stockCategoriesTable', 'stockCategoryCount', 'فئة');
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
      bi: 'لوحات القيادة (BI) — 5 لوحات',
      catalog: 'الأصناف والأسعار',
      pricing: 'اعتماد طلبات التسعير',
      cases: 'متابعة الحالات',
      employees: 'إدارة الموظفين',
      companies: 'شركات التعاقد',
      debts: 'مديونيات شركات التعاقد',
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

    onId('casesSearch', 'input', function(e) {
      casesSearchTerm = e.target.value.trim();
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
      if (document.getElementById('overviewWaitingCount') &&
          document.getElementById('overviewWaitingCount').dataset.serverRendered === '1') return;
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

      refreshPaginated('casesTableBody');
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

    function filterServerPricingApprovalRows() {
      var tbody = document.getElementById('pricingApprovalTable');
      if (!tbody || tbody.dataset.serverRendered !== '1') return;

      var searchEl = document.getElementById('pricingApprovalSearch');
      var filterEl = document.getElementById('pricingApprovalFilter');
      var search = searchEl ? searchEl.value.trim().toLowerCase() : '';
      var status = filterEl ? filterEl.value : pricingApprovalFilter;
      var visible = 0;
      var pendingCount = 0;

      tbody.querySelectorAll('tr[data-status]').forEach(function(row) {
        var rowStatus = row.dataset.status || '';
        if (rowStatus === 'awaiting_admin_approval') pendingCount++;

        var hay = (row.dataset.search || row.textContent || '').toLowerCase();
        var show = (status === 'all' || rowStatus === status)
          && (!search || hay.indexOf(search) !== -1);

        if (show) {
          delete row.dataset.paginationSkip;
          row.style.display = '';
          visible++;
        } else {
          row.dataset.paginationSkip = '1';
          row.style.display = 'none';
        }
      });

      var badge = document.getElementById('pricingApprovalBadge');
      if (badge) badge.textContent = pendingCount + ' بانتظار';

      var countEl = document.getElementById('pricingApprovalCount');
      if (countEl) countEl.textContent = visible + ' طلب';

      refreshPaginated('pricingApprovalTable');
    }

    function renderPricingApproval() {
      var tbody = document.getElementById('pricingApprovalTable');
      if (tbody && tbody.dataset.serverRendered === '1') {
        filterServerPricingApprovalRows();
        return;
      }

      var all = PricingQueue.getAll();
      var filtered = getFilteredPricingApproval();
      var pendingCount = all.filter(function(p) { return p.statusKey === 'awaiting_admin_approval'; }).length;

      document.getElementById('pricingApprovalBadge').textContent = pendingCount + ' بانتظار';
      document.getElementById('pricingApprovalCount').textContent = filtered.length + ' طلب';

      if (!filtered.length) {
        document.getElementById('pricingApprovalTable').innerHTML =
          '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">لا توجد طلبات مطابقة</td></tr>';
        refreshPaginated('pricingApprovalTable');
        return;
      }

      document.getElementById('pricingApprovalTable').innerHTML = filtered.map(function(p, idx) {
        var actions = '<div class="approval-actions">' +
          '<button type="button" class="btn-action" onclick="openPricingApprovalModal(\'' + p.id + '\')">عرض</button>';
        if (p.statusKey === 'awaiting_admin_approval') {
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
          '<td><span class="pricing-approval-status ' + p.statusKey + '">' + p.statusLabel + '</span></td>' +
          '<td>' + actions + '</td>' +
          '</tr>';
      }).join('');
      refreshPaginated('pricingApprovalTable');
    }

    function pricingDetailBox(label, value) {
      return '<div class="catalog-detail-box"><div class="dl">' + label + '</div><div class="dv">' + value + '</div></div>';
    }

    function formatPricingDate(value) {
      if (!value) return '—';
      return String(value).slice(0, 10);
    }

    function renderPricingDetailModal(p) {
      selectedPricingId = p.id;
      var statusKey = p.status_key || '';
      var patient = p.patient || {};
      var caseInfo = p.case || {};

      document.getElementById('pricingApprovalModalTitle').textContent = '🧾 ' + (p.request_no || '—');
      document.getElementById('pricingApprovalModalRef').textContent =
        (p.order_ref || caseInfo.order_ref || '—') + ' · ' + (p.patient_name || patient.name || '—');

      var meta = [
        pricingDetailBox('رقم الطلب', p.request_no || '—'),
        pricingDetailBox('أمر التشغيل', p.order_ref || caseInfo.order_ref || '—'),
        pricingDetailBox('رقم الحالة', caseInfo.case_no || '—'),
        pricingDetailBox('المريض', p.patient_name || patient.name || '—'),
        pricingDetailBox('رقم المريض', patient.patient_code || '—'),
        pricingDetailBox('الهوية الوطنية', patient.national_id || '—'),
        pricingDetailBox('الهاتف', patient.phone || '—'),
        pricingDetailBox('تصنيف المريض', pricingTypeBadge(p.patient_type || patient.patient_type || caseInfo.patient_type)),
        pricingDetailBox('جهة التعاقد', p.company_name || patient.company_name || caseInfo.company_name || '—'),
      ];

      if (patient.rank) {
        meta.push(pricingDetailBox('الرتبة', patient.rank));
      }
      if (patient.sovereign_entity) {
        meta.push(pricingDetailBox('الجهة السيادية', patient.sovereign_entity));
      }

      meta.push(
        pricingDetailBox('الطبيب', p.doctor_name || '—'),
        pricingDetailBox('تاريخ الطلب', formatPricingDate(p.request_date)),
        pricingDetailBox('الحالة', p.status_label || '—')
      );

      if (p.approved_by) {
        meta.push(pricingDetailBox('اعتمد بواسطة', p.approved_by));
      }
      if (p.approved_at) {
        meta.push(pricingDetailBox('تاريخ الاعتماد', formatPricingDate(p.approved_at)));
      }

      document.getElementById('pricingApprovalModalMeta').innerHTML = meta.join('');

      var rows = (p.items || []).map(function(item) {
        return '<tr>' +
          '<td><strong>' + (item.name || '—') + '</strong></td>' +
          '<td>' + (item.stock_item_code || '—') + '</td>' +
          '<td>' + (item.qty || 0) + '</td>' +
          '<td>' + PricingQueue.formatMoney(item.unit_price) + '</td>' +
          '<td>' + PricingQueue.formatMoney(item.line_total) + '</td>' +
          '</tr>';
      }).join('');

      document.getElementById('pricingApprovalModalItems').innerHTML = rows ||
        '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">لا توجد بنود</td></tr>';
      document.getElementById('pricingApprovalModalTotal').textContent =
        PricingQueue.formatMoney(p.computed_total || 0);

      var approveBtn = document.getElementById('btnApprovePricingModal');
      approveBtn.style.display = statusKey === 'awaiting_admin_approval' ? 'inline-flex' : 'none';
    }

    function openPricingApprovalModalFromServer(id) {
      var modal = document.getElementById('pricingApprovalModal');
      if (!modal) return;

      selectedPricingId = id;
      document.getElementById('pricingApprovalModalTitle').textContent = '🧾 جاري التحميل...';
      document.getElementById('pricingApprovalModalRef').textContent = '';
      document.getElementById('pricingApprovalModalMeta').innerHTML =
        '<p style="text-align:center;color:var(--text-muted);padding:16px;">جاري تحميل التفاصيل...</p>';
      document.getElementById('pricingApprovalModalItems').innerHTML = '';
      document.getElementById('pricingApprovalModalTotal').textContent = '—';
      document.getElementById('btnApprovePricingModal').style.display = 'none';
      modal.classList.add('open');

      fetch('/admin/pricing/' + id, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function(res) {
          if (!res.ok) throw new Error('load failed');
          return res.json();
        })
        .then(function(data) {
          renderPricingDetailModal(data);
        })
        .catch(function() {
          document.getElementById('pricingApprovalModalMeta').innerHTML =
            '<p style="color:#dc2626;text-align:center;padding:16px;">تعذّر تحميل تفاصيل الطلب</p>';
        });
    }

    function openPricingApprovalModal(id) {
      var tbody = document.getElementById('pricingApprovalTable');
      if (tbody && tbody.dataset.serverRendered === '1') {
        openPricingApprovalModalFromServer(id);
        return;
      }

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
      approveBtn.style.display = p.statusKey === 'awaiting_admin_approval' ? 'inline-flex' : 'none';

      document.getElementById('pricingApprovalModal').classList.add('open');
    }

    function closePricingApprovalModal() {
      document.getElementById('pricingApprovalModal').classList.remove('open');
      selectedPricingId = null;
    }

    function approvePricingRequest(id) {
      var tbody = document.getElementById('pricingApprovalTable');
      if (tbody && tbody.dataset.serverRendered === '1') {
        if (!confirm('موافقة الأدمن على الطلب وإرساله للاستقبال لإصدار عرض السعر؟')) return;
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch('/admin/pricing/' + id + '/approve', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.content : '',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
          .then(function(res) {
            if (!res.ok) throw new Error('approve failed');
            return res.json();
          })
          .then(function() {
            closePricingApprovalModal();
            window.location.reload();
          })
          .catch(function() {
            alert('⚠️ تعذّر اعتماد الطلب');
          });
        return;
      }

      var p = PricingQueue.getById(id);
      if (!p || p.statusKey !== 'awaiting_admin_approval') return;
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
      var headers = ['رقم الطلب', 'أمر التشغيل', 'المريض', 'التصنيف', 'جهة التعاقد', 'التاريخ', 'البنود', 'الحالة'];
      var rows = data.map(function(p) {
        var typeLabel = p.patientType === 'military' ? 'عسكري' : 'مدني';
        return [p.id, p.orderRef, p.patient, typeLabel, p.company, p.date, p.items, p.statusLabel];
      });
      if (type === 'excel') ExportKit.toExcel('pricing-approval', headers, rows);
      else ExportKit.toPDF('اعتماد التسعير', headers, rows);
    }

    window.openPricingApprovalModal = openPricingApprovalModal;
    window.approvePricingRequest = approvePricingRequest;

    onId('pricingApprovalSearch', 'input', function(e) {
      pricingApprovalSearch = e.target.value.trim();
      renderPricingApproval();
    });

    onId('pricingApprovalFilter', 'change', function(e) {
      pricingApprovalFilter = e.target.value;
      renderPricingApproval();
    });

    onId('closePricingApprovalModal', 'click', closePricingApprovalModal);
    onId('btnClosePricingApprovalModal', 'click', closePricingApprovalModal);
    onId('btnApprovePricingModal', 'click', function() {
      if (selectedPricingId) approvePricingRequest(selectedPricingId);
    });
    onId('pricingApprovalModal', 'click', function(e) {
      if (e.target === this) closePricingApprovalModal();
    });

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
      if (countEl) countEl.textContent = visible + ' شركة';
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
        if (badge) badge.textContent = contractCompanies.length + ' شركة';
        if (countEl) countEl.textContent = filtered.length + ' شركة';

        if (!filtered.length) {
          tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد شركات — أضف جهة تعاقد من الحقل أعلاه.</td></tr>';
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
      var headers = ['#', 'اسم الشركة'];
      var rows = data.map(function(c, i) { return [i + 1, c.name]; });
      if (type === 'excel') ExportKit.toExcel('contract-companies', headers, rows);
      else ExportKit.toPDF('شركات التعاقد', headers, rows);
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

    var SUPPLIER_TYPES = [
      { value: 'محلي', label: 'محلي', cls: 'local' },
      { value: 'مستورد', label: 'مستورد', cls: 'import' },
      { value: 'OEM', label: 'OEM', cls: 'oem' },
      { value: 'موزّع', label: 'موزّع', cls: 'distributor' }
    ];

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
        '<div><label>المورد</label><select class="price-supplier">' + supplierSelectHtml(p.supplier_id || p.supplierId, p.supplier) + '</select></div>' +
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
      var catSel = document.getElementById('catalogCategory');
      if (catSel) catSel.selectedIndex = catSel.options.length > 1 ? 1 : 0;
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
        var code = editingCatalogCode || StockCatalog.nextCode();
        var supplierSel = row.querySelector('.price-supplier');
        var supplierId = supplierSel ? supplierSel.value : '';
        var supplierName = supplierId ? getSupplierNameById(supplierId) : '';
        return {
          id: id || StockCatalog.nextPriceId(code),
          label: row.querySelector('.price-label').value.trim(),
          supplier_id: supplierId ? parseInt(supplierId, 10) : null,
          supplier: supplierName,
          supplierType: row.querySelector('.price-supplier-type').value,
          itemCode: row.querySelector('.price-item-code').value.trim(),
          amount: parseInt(row.querySelector('.price-amount').value, 10) || 0
        };
      }).filter(function(p) { return p.label && p.itemCode && p.amount > 0 && p.supplier_id; });
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
      refreshPaginated('catalogTable');
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
      var categoryId = document.getElementById('catalogCategory').value;
      var category = getCatalogCategoryNameById(categoryId);
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
        alert('يرجى إضافة سعر واحد على الأقل مع اختيار المورد وكود الصنف');
        return;
      }
      if (editingCatalogCode) {
        var existing = catalogItems.find(function(i) { return i.code === editingCatalogCode; });
        StockCatalog.updateItem(editingCatalogCode, {
          name: name,
          spec: spec,
          category_id: parseInt(categoryId, 10),
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
          category_id: parseInt(categoryId, 10),
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
      showCatalogDetail(row.getAttribute('data-code'));
    });

    onId('catalogModalClose', 'click', closeCatalogDetailModal);
    onId('catalogModalCloseBtn', 'click', closeCatalogDetailModal);
    onId('catalogDetailModal', 'click', closeCatalogDetailModal);
    onId('catalogModalEdit', 'click', function() {
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
      if (!container || container.dataset.serverRendered === '1') return;
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
      var action = document.getElementById('auditActionFilter') ? document.getElementById('auditActionFilter').value : 'all';
      return ExportKit.filterItems(auditLogs, { search: search, searchKeys: ['user', 'desc', 'action'], filterField: 'action', filterValue: action });
    }

    function getFilteredSuppliers() {
      var search = document.getElementById('supplierSearch') ? document.getElementById('supplierSearch').value.trim() : '';
      return ExportKit.filterItems(suppliers, { search: search, searchKeys: ['name', 'phone', 'email'] });
    }

    function exportEmployees(type) {
      var tbody = document.getElementById('employeesTableFull') || document.getElementById('employeesTable');
      var headers = ['الاسم', 'الدور', 'الحالة', 'آخر دخول'];
      var rows = [];
      if (tbody && tbody.dataset.serverRendered === '1') {
        tbody.querySelectorAll('tr').forEach(function(row) {
          if (row.style.display === 'none') return;
          var cells = row.querySelectorAll('td');
          if (cells.length < 4) return;
          rows.push([
            (cells[0].querySelector('strong') || cells[0]).textContent.trim(),
            cells[1].textContent.trim(),
            cells[2].textContent.trim(),
            cells[3].textContent.trim()
          ]);
        });
      } else {
        rows = getFilteredEmployees().map(function(e) {
          return [e.name, e.roleLabel, e.status === 'active' ? 'نشط' : 'غير نشط', e.lastLogin];
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
      if (container && container.dataset.serverRendered === '1') return;
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
    ['supplierSearch'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderSuppliers); el.addEventListener('change', renderSuppliers); }
    });
    function renderAdminAnalytics() {
      return;
    }

    function renderBomAdminReport() {
      return;
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



    var activePage = document.body.dataset.activePage || '';

    function safePageInit(fn) {
      try { fn(); } catch (e) { /* عنصر الصفحة غير موجود */ }
    }

    safePageInit(renderAdminAnalytics);
    safePageInit(renderCatalog);
    safePageInit(renderPricingApproval);
    safePageInit(renderCasesSection);
    safePageInit(renderOverviewCasesCounts);
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
