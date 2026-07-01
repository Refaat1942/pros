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
    warehouse: 'المخزن', issue: 'قيد التصنيع', workshop: 'الورشة', fitting: 'تجربة تركيب',
    quality: 'مراقبة جودة', generation: 'توليد', assembly: 'تم التصنيع', casting: 'صب',
    finishing: 'تشطيب', closed: 'مغلق'
  };

  function $(id) { return document.getElementById(id); }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', isError: !!isError });
      return;
    }
    alert(msg);
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function renderActionCell(c) {
    var printBtn = c.work_order_print_url
      ? '<a href="' + esc(c.work_order_print_url) + '" target="_blank" rel="noopener" ' +
        'class="text-xs font-bold rounded-lg border border-violet-700 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block mb-1">🖨️ طباعة إذن شغل</a> '
      : '';
    return printBtn + '<button type="button" class="btn-complete-manufacturing text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700" data-case-id="' + c.id + '">✓ تم التصنيع</button>';
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
      '<td class="px-4 py-3 text-center">' + renderItemsCell(c) + '</td>' +
      '<td class="px-4 py-3">' + renderActionCell(c) + '</td></tr>';
  }

  function updateSummary(summary) {
    summary = summary || {};
    if ($('sumWip')) $('sumWip').textContent = summary.wip != null ? summary.wip : 0;
    if ($('sumTotal')) $('sumTotal').textContent = summary.total_active != null ? summary.total_active : 0;

    var analytics = document.getElementById('analytics-workshop');
    if (!analytics) return;
    var values = analytics.querySelectorAll('.ck-stat-value');
    if (values.length < 2) return;
    values[0].textContent = summary.wip != null ? summary.wip : 0;
    values[1].textContent = summary.total_active != null ? summary.total_active : 0;
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
    if (!window.confirm('تأكيد تم التصنيع؟ ستُحوَّل الحالة إلى المخزن لإتمام التسليم وإغلاق الطلب.')) return;

    btn.disabled = true;
    axios.post('/workshop/workshop/' + caseId + '/finish-quality')
      .then(function () {
        toast('✅ تم التصنيع — الحالة جاهزة للتسليم في المخزن');
        refreshList();
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
          return '<tr><td class="px-3 py-2 font-mono text-xs text-slate-500">' + esc(item.stock_item_code) + '</td>' +
            '<td class="px-3 py-2 font-semibold text-slate-800">' + esc(item.name || item.stock_item_code) + '</td>' +
            '<td class="px-3 py-2 text-center font-bold">' + esc(item.qty) + '</td></tr>';
        }).join('')
      : '<tr><td colspan="3" class="px-3 py-8 text-center text-slate-400">لا توجد بنود.</td></tr>';

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

    axios.get('/workshop/workshop/list')
      .then(function (res) {
        casesCache = res.data.data || [];
        var tbody = $('workshopTableBody');
        if (!tbody) return;
        tbody.innerHTML = casesCache.length
          ? casesCache.map(renderRow).join('')
          : '<tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر في الورشة حالياً.</td></tr>';
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

  document.addEventListener('DOMContentLoaded', function () {
    bindTableEvents();
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
    if (refresh) refresh.addEventListener('click', refreshList);
    var closeBtn = $('closeWorkshopBomItemsModal');
    var modal = $('workshopBomItemsModal');
    if (closeBtn) closeBtn.addEventListener('click', closeBomItemsModal);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeBomItemsModal(); });
    applyFilters();
  });
})();
