/**
 * Workshop Desk — manufacturing queue after warehouse dispense.
 */
(function () {
  if (document.body.dataset.dashboard !== 'workshop') return;
  if (document.body.dataset.activePage !== 'workshop') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var MFG_LABELS = {
    warehouse: 'المخزن', issue: 'قيد التصنيع', workshop: 'قسم الإنتاج', fitting: 'تجربة تركيب',
    quality: 'مراقبة جودة', generation: 'توليد', assembly: 'تم التصنيع', casting: 'صب',
    finishing: 'تشطيب', closed: 'مغلق'
  };

  var assignmentSections = [];
  var selectedCaseId = null;

  function $(id) { return document.getElementById(id); }

  function toast(msg, isError, extra) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, Object.assign({ id: 'toast', isError: !!isError }, extra || {}));
      return;
    }
    alert(msg);
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function renderAssignmentStatusCell(c) {
    if (c.assignment_approved) {
      return '<span class="text-xs font-bold px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700">✓ معتمد — جاهز للصرف</span>';
    }
    if (c.workshop_section_id && c.assigned_technician_id) {
      return '<span class="text-xs font-bold px-2 py-1 rounded-lg bg-amber-100 text-amber-800">بانتظار الاعتماد</span>';
    }
    return '<span class="text-xs font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">غير مخصّص</span>';
  }

  function renderAssignmentQueueRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var search = [c.work_order_no, c.case_no, c.patient && c.patient.name].join(' ');
    var printBtn = c.work_order_print_url
      ? '<a href="' + esc(c.work_order_print_url) + '" target="_blank" rel="noopener" ' +
        'class="text-xs font-bold rounded-lg border border-violet-700 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block mb-1">🖨️ إذن شغل</a> '
      : '';
    var approveBtn = (!c.assignment_approved && c.workshop_section_id && c.assigned_technician_id)
      ? '<button type="button" class="btn-approve-assignment text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700 inline-block mb-1" data-case-id="' + c.id + '">✓ اعتماد</button> '
      : '';

    return '<tr class="assignment-row hover:bg-slate-50" data-case-id="' + c.id + '" data-search="' + esc(search) + '">' +
      '<td class="px-4 py-3 font-mono font-bold text-amber-800">' + esc(c.work_order_no || '—') + '</td>' +
      '<td class="px-4 py-3"><div class="font-semibold text-slate-800">' + esc(c.patient && c.patient.name) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.case_no) + '</div></td>' +
      '<td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-lg ' +
        (isMil ? 'bg-indigo-100 text-indigo-700">🪖 عسكري' : 'bg-emerald-100 text-emerald-700">🌐 مدني') + '</span></td>' +
      '<td class="px-4 py-3">' + renderAssignmentCell(c) + '</td>' +
      '<td class="px-4 py-3">' + renderAssignmentStatusCell(c) + '</td>' +
      '<td class="px-4 py-3">' + printBtn + approveBtn +
        '<button type="button" class="btn-select-workshop-case text-xs font-bold rounded-lg border border-violet-300 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block" data-case-id="' + c.id + '" data-work-order="' + esc(c.work_order_no || '') + '">👤 تخصيص</button></td></tr>';
  }

  function bindAssignmentQueueEvents() {
    document.querySelectorAll('#workshopAssignmentTableBody .btn-select-workshop-case').forEach(function (btn) {
      btn.addEventListener('click', function () {
        selectedCaseId = btn.getAttribute('data-case-id');
        var wo = btn.getAttribute('data-work-order') || selectedCaseId;
        var input = $('workshopSelectedOrder');
        if (input) input.value = wo;
        var cached = assignmentCache.find(function (c) { return String(c.id) === String(selectedCaseId); });
        if (cached) populateAssignmentForm(cached);
        document.getElementById('workshopAssignmentQueuePanel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    document.querySelectorAll('.btn-approve-assignment').forEach(function (btn) {
      btn.addEventListener('click', function () {
        approveWorkshopAssignment(btn.getAttribute('data-case-id'), btn);
      });
    });
  }

  function populateAssignmentForm(c) {
    var sectionSel = $('workshopAssignSection');
    var techSel = $('workshopAssignTechnician');
    if (sectionSel && c.workshop_section_id) {
      sectionSel.value = String(c.workshop_section_id);
      sectionSel.dispatchEvent(new Event('change'));
    }
    if (techSel && c.assigned_technician_id) techSel.value = String(c.assigned_technician_id);
  }

  var assignmentRefreshInFlight = false;
  var assignmentCache = [];

  function refreshAssignmentQueue() {
    if (!window.axios || assignmentRefreshInFlight) return;
    assignmentRefreshInFlight = true;
    var btn = $('btnRefreshAssignmentQueue');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get('/workshop/workshop/assignment-queue')
      .then(function (res) {
        assignmentCache = res.data.data || [];
        var tbody = $('workshopAssignmentTableBody');
        if (!tbody) return;
        tbody.innerHTML = assignmentCache.length
          ? assignmentCache.map(renderAssignmentQueueRow).join('')
          : '<tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر بانتظار التخصيص — تظهر بعد اعتماد مكتب التشغيل.</td></tr>';
        bindAssignmentQueueEvents();
        if (window.TablePagination && TablePagination.repaginate) TablePagination.repaginate(tbody);
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر تحميل طابور التخصيص', true);
      })
      .finally(function () {
        assignmentRefreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث الطابور'; }
      });
  }

  function resolveSelectedCaseId() {
    if (selectedCaseId) return selectedCaseId;
    var woInput = $('workshopSelectedOrder');
    var wo = (woInput && woInput.value || '').trim();
    if (!wo) return null;
    var fromQueue = assignmentCache.find(function (c) {
      return String(c.work_order_no || '').trim() === wo;
    });
    if (fromQueue) {
      selectedCaseId = String(fromQueue.id);
      return selectedCaseId;
    }
    var fromDesk = casesCache.find(function (c) {
      return String(c.work_order_no || '').trim() === wo;
    });
    if (fromDesk) {
      selectedCaseId = String(fromDesk.id);
      return selectedCaseId;
    }
    return null;
  }

  function readAssignmentPayload() {
    var sectionEl = $('workshopAssignSection');
    var techEl = $('workshopAssignTechnician');
    var sectionRaw = sectionEl ? String(sectionEl.value || '').trim() : '';
    var techRaw = techEl ? String(techEl.value || '').trim() : '';
    var sectionId = sectionRaw ? parseInt(sectionRaw, 10) : null;
    var techId = techRaw ? parseInt(techRaw, 10) : null;
    if (!sectionId || Number.isNaN(sectionId)) sectionId = null;
    if (!techId || Number.isNaN(techId)) techId = null;
    return {
      workshop_section_id: sectionId,
      assigned_technician_id: techId,
    };
  }

  function approveWorkshopAssignment(caseId, triggerBtn) {
    if (!window.axios) return;

    caseId = resolveSelectedCaseId() || caseId;
    if (!caseId) {
      toast('اختر أمر شغل من الطابور أولاً (زر «تخصيص»)', true);
      return;
    }

    var payload = readAssignmentPayload();
    if (!payload.workshop_section_id || !payload.assigned_technician_id) {
      toast('حدّد قسم الإنتاج والفني من القوائم ثم أعد المحاولة.', true);
      return;
    }

    if (!window.confirm('تأكيد حفظ واعتماد التخصيص؟\n\nبعد الاعتماد يمكن للمخزن صرف المواد لهذا الأمر.')) return;

    if (triggerBtn) triggerBtn.disabled = true;
    var formBtn = $('btnApproveWorkshopAssignment');
    var saveBtn = $('btnSaveWorkshopAssignment');
    if (formBtn) formBtn.disabled = true;
    if (saveBtn) saveBtn.disabled = true;

    // حفظ التخصيص أولاً ثم الاعتماد — يعمل حتى قبل تحديث السيرفر الذي يدمج الطلبين.
    axios.post('/workshop/workshop/' + caseId + '/assign', payload)
      .then(function () {
        return axios.post('/workshop/workshop/' + caseId + '/approve-assignment', payload);
      })
      .then(function (res) {
        toast(res.data.message || 'تم اعتماد التخصيص');
        refreshAssignmentQueue();
        refreshList();
        refreshTechBoard();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر اعتماد التخصيص', true);
      })
      .finally(function () {
        if (triggerBtn) triggerBtn.disabled = false;
        if (formBtn) formBtn.disabled = false;
        if (saveBtn) saveBtn.disabled = false;
      });
  }

  function renderActionCell(c) {
    var printBtn = c.work_order_print_url
      ? '<a href="' + esc(c.work_order_print_url) + '" target="_blank" rel="noopener" ' +
        'class="text-xs font-bold rounded-lg border border-violet-700 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block mb-1">🖨️ طباعة إذن شغل</a> '
      : '';
    return printBtn +
      '<button type="button" class="btn-select-workshop-case text-xs font-bold rounded-lg border border-violet-300 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block mb-1" data-case-id="' + c.id + '" data-work-order="' + esc(c.work_order_no || '') + '">👤 تخصيص</button> ' +
      '<button type="button" class="btn-complete-manufacturing text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700" data-case-id="' + c.id + '">✓ تم التصنيع</button>';
  }

  function renderAssignmentCell(c) {
    var section = (c.workshop_section && c.workshop_section.name) || '—';
    var tech = (c.assigned_technician && c.assigned_technician.name) || '—';
    return '<div class="text-xs"><span class="font-semibold text-slate-700">' + esc(section) + '</span>' +
      '<div class="text-slate-400 mt-0.5">' + esc(tech) + '</div></div>';
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var search = [c.work_order_no, c.case_no, c.patient && c.patient.name].join(' ');
    var mfgLabel = MFG_LABELS[c.manufacturing_stage] || c.manufacturing_stage || '—';

    return '<tr class="workshop-row hover:bg-slate-50" data-case-id="' + c.id + '" data-search="' + esc(search) + '"' +
      ' data-path="' + (isMil ? 'military' : 'civilian') + '" data-filter-hidden="0">' +
      '<td class="px-4 py-3 font-mono font-bold text-violet-700">' + esc(c.work_order_no || '—') + '</td>' +
      '<td class="px-4 py-3"><div class="font-semibold text-slate-800">' + esc(c.patient && c.patient.name) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.case_no) + '</div></td>' +
      '<td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-lg ' +
        (isMil ? 'bg-indigo-100 text-indigo-700">🪖 عسكري' : 'bg-emerald-100 text-emerald-700">🌐 مدني') + '</span></td>' +
      '<td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-lg bg-cyan-100 text-cyan-800">' + esc(mfgLabel) + '</span></td>' +
      '<td class="px-4 py-3">' + renderAssignmentCell(c) + '</td>' +
      '<td class="px-4 py-3 text-center">' + renderItemsCell(c) + '</td>' +
      '<td class="px-4 py-3">' + renderActionCell(c) + '</td></tr>';
  }

  function updateSummary(summary) {
    summary = summary || {};
    if (summary.total_wip != null && summary.wip == null) summary.wip = summary.total_wip;

    var analytics = document.getElementById('analytics-workshop');
    if (!analytics) return;

    var statMap = {
      wip: summary.wip,
      assigned: summary.assigned,
      unassigned: summary.unassigned,
      avg_progress: summary.avg_progress != null ? summary.avg_progress + '%' : null,
    };

    analytics.querySelectorAll('[data-stat-key]').forEach(function (el) {
      var key = el.getAttribute('data-stat-key');
      if (statMap[key] != null) el.textContent = statMap[key];
    });

    var values = analytics.querySelectorAll('.ck-stat-value');
    if (values.length >= 4) {
      if (summary.wip != null) values[0].textContent = summary.wip;
      if (summary.assigned != null) values[1].textContent = summary.assigned;
      if (summary.unassigned != null) values[2].textContent = summary.unassigned;
      if (summary.avg_progress != null) values[3].textContent = summary.avg_progress + '%';
    } else if (values.length >= 2) {
      values[0].textContent = summary.wip != null ? summary.wip : 0;
      values[1].textContent = summary.total_active != null ? summary.total_active : 0;
    }
  }

  function renderTechOrderCard(order) {
    var doneClass = order.is_done ? ' border-emerald-200 bg-emerald-50/40' : ' border-slate-200';
    var pct = order.progress_pct || 0;
    return '<div class="rounded-xl border p-3' + doneClass + '" data-case-id="' + order.id + '">' +
      '<div class="flex items-start justify-between gap-2 mb-2">' +
        '<div class="min-w-0">' +
          '<div class="font-mono text-sm font-bold text-violet-800">' + esc(order.work_order_no || '—') + '</div>' +
          '<div class="text-xs text-slate-600 truncate">' + esc(order.patient && order.patient.name) + '</div>' +
          '<div class="text-[11px] text-slate-400">' + esc(order.case_no) + ' · ' + esc(order.pathway_label) + '</div>' +
        '</div>' +
        '<span class="text-xs font-bold px-2 py-1 rounded-lg ' + (order.is_done ? 'bg-emerald-100 text-emerald-700' : 'bg-cyan-100 text-cyan-800') + '">' +
          esc(order.manufacturing_stage_label || '—') + '</span>' +
      '</div>' +
      '<label class="block text-[11px] font-bold text-slate-500 mb-1">نسبة الإنجاز: <span class="tech-progress-val">' + pct + '</span>%</label>' +
      '<input type="range" min="0" max="100" step="5" value="' + pct + '" class="tech-progress-slider w-full accent-violet-600" data-case-id="' + order.id + '">' +
    '</div>';
  }

  function renderTechnicianBoard(payload) {
    payload = payload || {};
    var cardsRoot = $('workshopTechBoardCards');
    var unassignedPanel = $('workshopUnassignedPanel');
    var unassignedList = $('workshopUnassignedList');
    if (!cardsRoot) return;

    var technicians = payload.technicians || [];
    if (!technicians.length) {
      cardsRoot.innerHTML = '<p class="text-sm text-slate-400 col-span-full text-center py-8">لا يوجد فنيون لديهم أوامر حالياً — خصّص الفنيين من الأعلى.</p>';
    } else {
      cardsRoot.innerHTML = technicians.map(function (group) {
        var tech = group.technician || {};
        var section = (group.section && group.section.name) || '—';
        var orders = group.orders || [];
        return '<div class="rounded-2xl border border-indigo-100 bg-white shadow-sm overflow-hidden">' +
          '<div class="px-4 py-3 bg-indigo-50 border-b border-indigo-100">' +
            '<div class="font-bold text-indigo-900">' + esc(tech.name || '—') + '</div>' +
            '<div class="text-xs text-indigo-700 mt-0.5">' + esc(section) + ' · ' + (group.active_count || 0) + ' نشط · ' + (group.done_count || 0) + ' مكتمل · متوسط ' + (group.avg_progress || 0) + '%</div>' +
          '</div>' +
          '<div class="p-3 space-y-2 max-h-80 overflow-y-auto">' +
            (orders.length
              ? orders.map(renderTechOrderCard).join('')
              : '<p class="text-xs text-slate-400 text-center py-4">لا توجد أوامر.</p>') +
          '</div></div>';
      }).join('');
    }

    var unassigned = payload.unassigned || [];
    if (unassignedPanel && unassignedList) {
      if (unassigned.length) {
        unassignedPanel.classList.remove('hidden');
        unassignedList.innerHTML = unassigned.map(function (order) {
          return '<div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm">' +
            '<div><span class="font-mono font-bold text-amber-900">' + esc(order.work_order_no || '—') + '</span> · ' +
            esc(order.patient && order.patient.name) + ' <span class="text-xs text-amber-700">(' + esc(order.case_no) + ')</span></div>' +
            '<button type="button" class="btn-select-workshop-case text-xs font-bold rounded-lg border border-violet-300 text-violet-800 px-3 py-1 hover:bg-violet-50" data-case-id="' + order.id + '" data-work-order="' + esc(order.work_order_no || '') + '">👤 تخصيص</button>' +
          '</div>';
        }).join('');
      } else {
        unassignedPanel.classList.add('hidden');
        unassignedList.innerHTML = '';
      }
    }

    bindTechBoardEvents();
    if (payload.summary) updateSummary(payload.summary);
  }

  var techBoardInFlight = false;

  function refreshTechBoard() {
    if (!window.axios || techBoardInFlight) return;
    techBoardInFlight = true;
    var btn = $('btnRefreshTechBoard');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get('/workshop/technicians/board')
      .then(function (res) { renderTechnicianBoard(res.data || {}); })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر تحميل تتبع الفنيين', true);
      })
      .finally(function () {
        techBoardInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث التتبع'; }
      });
  }

  function saveProgress(caseId, percent, slider) {
    if (!window.axios || !caseId) return;
    if (slider) slider.disabled = true;
    axios.post('/workshop/workshop/' + caseId + '/progress', { progress_pct: percent })
      .then(function () {
        refreshList();
        refreshTechBoard();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر تحديث الإنجاز', true);
      })
      .finally(function () { if (slider) slider.disabled = false; });
  }

  function bindTechBoardEvents() {
    document.querySelectorAll('.tech-progress-slider').forEach(function (slider) {
      slider.addEventListener('input', function () {
        var card = slider.closest('[data-case-id]');
        var valEl = card && card.querySelector('.tech-progress-val');
        if (valEl) valEl.textContent = slider.value;
      });
      slider.addEventListener('change', function () {
        saveProgress(slider.getAttribute('data-case-id'), parseInt(slider.value, 10), slider);
      });
    });
    document.querySelectorAll('#workshopUnassignedPanel .btn-select-workshop-case').forEach(function (btn) {
      btn.addEventListener('click', function () {
        selectedCaseId = btn.getAttribute('data-case-id');
        var wo = btn.getAttribute('data-work-order') || selectedCaseId;
        var input = $('workshopSelectedOrder');
        if (input) input.value = wo;
        document.getElementById('workshopDeskRoot')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  function renderItemsCell(c) {
    var items = (c.bom && c.bom.items) || [];
    if (!items.length) return '<span class="text-xs text-slate-400">—</span>';
    return '<button type="button" class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50" data-case-id="' + c.id + '">عرض</button>';
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-complete-manufacturing').forEach(function (btn) {
      btn.addEventListener('click', completeManufacturing);
    });
    document.querySelectorAll('.btn-select-workshop-case').forEach(function (btn) {
      btn.addEventListener('click', function () {
        selectedCaseId = btn.getAttribute('data-case-id');
        var wo = btn.getAttribute('data-work-order') || selectedCaseId;
        var input = $('workshopSelectedOrder');
        if (input) input.value = wo;
        var cached = casesCache.find(function (c) { return String(c.id) === String(selectedCaseId); });
        if (cached) populateAssignmentForm(cached);
        else {
          var fromAssignment = assignmentCache.find(function (c) { return String(c.id) === String(selectedCaseId); });
          if (fromAssignment) populateAssignmentForm(fromAssignment);
        }
      });
    });
    document.querySelectorAll('.btn-view-bom-items').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var caseData = findCaseData(btn.getAttribute('data-case-id'));
        if (caseData) openBomItemsModal(caseData);
      });
    });
  }

  function completeManufacturing(ev) {
    var btn = ev.currentTarget;
    var caseId = btn.getAttribute('data-case-id');
    if (!caseId || !window.axios) return;
    if (!window.confirm('تأكيد تم التصنيع؟\n\nبعد الإتمام يُرجى توجيه العميل إلى المخزن لاستلام الطرف وإتمام التسليم.')) return;

    btn.disabled = true;
    axios.post('/workshop/workshop/' + caseId + '/finish-quality')
      .then(function (res) {
        var message = (res.data && res.data.message)
          || 'تم التصنيع — يُرجى توجيه العميل إلى المخزن للتسليم.';
        toast(message, false, {
          title: 'تم التصنيع',
          type: 'success',
          duration: 8000,
        });
        refreshList();
        refreshTechBoard();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر إتمام التصنيع', true);
        btn.disabled = false;
      });
  }

  var refreshInFlight = false;
  var casesCache = [];
  var activeFilter = 'all';

  function rowMatchesFilter(row) {
    if (activeFilter === 'all') return true;
    return row.getAttribute('data-path') === activeFilter;
  }

  function openBomItemsModal(caseData) {
    var modal = $('workshopBomItemsModal');
    var tbody = $('workshopBomItemsBody');
    var subtitle = $('workshopBomItemsSubtitle');
    if (!modal || !tbody) return;

    var patient = (caseData.patient && caseData.patient.name) || '—';
    if (subtitle) subtitle.textContent = patient + ' · ' + (caseData.case_no || '—') + ' · ' + (caseData.work_order_no || '—');

    var items = (caseData.bom && caseData.bom.items) || [];
    tbody.innerHTML = items.length
      ? items.map(function (item) {
          return '<tr><td class="px-4 py-3 font-mono text-sm text-slate-600">' + esc(item.stock_item_code) + '</td>' +
            '<td class="px-4 py-3 font-semibold text-slate-800">' + esc(item.name || item.stock_item_code) + '</td>' +
            '<td class="px-4 py-3 text-center text-lg font-bold">' + esc(item.qty) + '</td></tr>';
        }).join('')
      : '<tr><td colspan="3" class="px-4 py-10 text-center text-slate-400 text-base">لا توجد بنود.</td></tr>';

    modal.classList.remove('hidden');
  }

  function closeBomItemsModal() {
    var modal = $('workshopBomItemsModal');
    if (modal) modal.classList.add('hidden');
  }

  function findCaseData(caseId) {
    var cached = casesCache.find(function (c) { return String(c.id) === String(caseId); });
    if (cached) return cached;
    var btn = document.querySelector('.btn-view-bom-items[data-case-id="' + caseId + '"]');
    if (!btn) return null;
    var items = [];
    try { items = JSON.parse(btn.getAttribute('data-items') || '[]'); } catch (e) { items = []; }
    return {
      id: caseId,
      case_no: btn.getAttribute('data-case-no'),
      work_order_no: btn.getAttribute('data-work-order'),
      patient: { name: btn.getAttribute('data-patient') },
      bom: { items: items }
    };
  }

  function refreshList(ev) {
    if (ev && ev.preventDefault) ev.preventDefault();
    if (!window.axios || refreshInFlight) return;

    refreshInFlight = true;
    var btn = $('btnRefreshWorkshop');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get('/workshop/workshop/list', {
      params: {
        filter: activeFilter === 'mine' ? 'mine' : (activeFilter === 'section' ? 'section' : undefined),
        section_id: activeFilter === 'section' ? (document.getElementById('workshopSectionFilter') || {}).value : undefined,
      },
    })
      .then(function (res) {
        casesCache = res.data.data || [];
        var tbody = $('workshopTableBody');
        if (!tbody) return;
        tbody.innerHTML = casesCache.length
          ? casesCache.map(renderRow).join('')
          : '<tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر في قسم الإنتاج حالياً.</td></tr>';
        bindTableEvents();
        updateSummary(res.data.summary || {});
        applyFilters();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر تحديث القائمة', true);
      })
      .finally(function () {
        refreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث'; }
      });
  }

  function applyFilters() {
    var q = ($('workshopSearch') && $('workshopSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.workshop-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      var ok = (!q || hay.indexOf(q) !== -1) && rowMatchesFilter(row);
      row.dataset.filterHidden = ok ? '0' : '1';
    });
    var tbody = $('workshopTableBody');
    if (tbody && window.TablePagination && TablePagination.repaginate) {
      TablePagination.repaginate(tbody);
    }
  }

  function saveWorkshopAssignment() {
    if (!selectedCaseId || !window.axios) {
      toast('اختر أمر شغل من الجدول أولاً', true);
      return;
    }
    var sectionEl = $('workshopAssignSection');
    var techEl = $('workshopAssignTechnician');
    var payload = {};
    if (sectionEl && sectionEl.value) payload.workshop_section_id = parseInt(sectionEl.value, 10);
    if (techEl && techEl.value) payload.assigned_technician_id = parseInt(techEl.value, 10);

    var btn = $('btnSaveWorkshopAssignment');
    if (btn) btn.disabled = true;
    axios.post('/workshop/workshop/' + selectedCaseId + '/assign', payload)
      .then(function (res) {
        toast(res.data.message || 'تم حفظ التخصيص');
        refreshAssignmentQueue();
        refreshList();
        refreshTechBoard();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر حفظ التخصيص', true);
      })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  function loadAssignmentOptions() {
    if (!window.axios) return;
    axios.get('/workshop/workshop-assignment/options').then(function (res) {
      assignmentSections = (res.data && res.data.sections) || [];
      var sectionSel = $('workshopAssignSection');
      var techSel = $('workshopAssignTechnician');
      if (!sectionSel || !techSel) return;
      sectionSel.innerHTML = '<option value="">— بدون —</option>' + assignmentSections.map(function (s) {
        return '<option value="' + s.id + '">' + esc(s.name) + (s.code ? ' (' + esc(s.code) + ')' : '') + '</option>';
      }).join('');
      sectionSel.addEventListener('change', function () {
        var sec = assignmentSections.find(function (s) { return String(s.id) === String(sectionSel.value); });
        var techs = sec ? (sec.technicians || []) : [];
        techSel.innerHTML = '<option value="">— بدون —</option>' + techs.map(function (t) {
          return '<option value="' + t.id + '">' + esc(t.name) + '</option>';
        }).join('');
      });
    }).catch(function () {});
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadAssignmentOptions();
    bindTableEvents();
    refreshAssignmentQueue();
    refreshTechBoard();
    var search = $('workshopSearch');
    if (search) search.addEventListener('input', applyFilters);
    var filtersRoot = $('workshopFilters');
    if (filtersRoot) {
      filtersRoot.addEventListener('click', function (e) {
        var btn = e.target.closest('.workshop-filter');
        if (!btn) return;
        activeFilter = btn.getAttribute('data-filter') || 'all';
        filtersRoot.querySelectorAll('.workshop-filter').forEach(function (b) {
          b.classList.remove('active', 'bg-slate-800', 'text-white');
        });
        btn.classList.add('active', 'bg-slate-800', 'text-white');
        applyFilters();
      });
    }
    var refresh = $('btnRefreshWorkshop');
    if (refresh) refresh.addEventListener('click', function () { refreshList(); refreshTechBoard(); });
    var techRefresh = $('btnRefreshTechBoard');
    if (techRefresh) techRefresh.addEventListener('click', refreshTechBoard);
    var assignBtn = $('btnSaveWorkshopAssignment');
    if (assignBtn) assignBtn.addEventListener('click', saveWorkshopAssignment);
    var approveAssignBtn = $('btnApproveWorkshopAssignment');
    if (approveAssignBtn) {
      approveAssignBtn.addEventListener('click', function () {
        approveWorkshopAssignment(null, approveAssignBtn);
      });
    }
    var refreshAssign = $('btnRefreshAssignmentQueue');
    if (refreshAssign) refreshAssign.addEventListener('click', refreshAssignmentQueue);
    var closeBtn = $('closeWorkshopBomItemsModal');
    var modal = $('workshopBomItemsModal');
    if (closeBtn) closeBtn.addEventListener('click', closeBomItemsModal);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeBomItemsModal(); });
    applyFilters();
  });
})();
