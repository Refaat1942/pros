/**
 * Operations Desk — delivery queue after workshop finishes manufacturing.
 */
(function () {
  if (document.body.dataset.dashboard !== 'operations') return;
  if (document.body.dataset.activePage !== 'operations') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

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
        'class="text-xs font-bold rounded-lg border border-cyan-700 text-cyan-800 px-3 py-1.5 hover:bg-cyan-50 inline-block mb-1">🖨️ طباعة إذن شغل</a> '
      : '';
    return printBtn + '<button type="button" class="btn-deliver-case text-xs font-bold rounded-lg bg-indigo-600 text-white px-3 py-1.5 hover:bg-indigo-700" data-case-id="' + c.id + '">✅ تم التسليم</button>';
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var search = [c.work_order_no, c.case_no, c.patient && c.patient.name].join(' ');

    return '<tr class="ops-row hover:bg-slate-50" data-case-id="' + c.id + '" data-search="' + esc(search) + '"' +
      ' data-path="' + (isMil ? 'military' : 'civilian') + '" data-filter-hidden="0">' +
      '<td class="px-4 py-3 font-mono font-bold text-cyan-700">' + esc(c.work_order_no || '—') + '</td>' +
      '<td class="px-4 py-3"><div class="font-semibold text-slate-800">' + esc(c.patient && c.patient.name) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.case_no) + '</div></td>' +
      '<td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-lg ' +
        (isMil ? 'bg-indigo-100 text-indigo-700">🪖 عسكري' : 'bg-emerald-100 text-emerald-700">🌐 مدني') + '</span></td>' +
      return '<td class="px-4 py-3 text-slate-600">' + (window.EntityBadges ? EntityBadges.renderHtml(c) : esc(c.company_name || '—')) + '</td>' +
      '<td class="px-4 py-3 text-center">' + renderItemsCell(c) + '</td>' +
      '<td class="px-4 py-3">' + renderActionCell(c) + '</td></tr>';
  }

  function updateSummary(summary) {
    summary = summary || {};
    if ($('sumReady')) $('sumReady').textContent = summary.ready != null ? summary.ready : 0;
    if ($('sumDone')) $('sumDone').textContent = summary.done != null ? summary.done : 0;
    if ($('sumTotal')) $('sumTotal').textContent = summary.total_active != null ? summary.total_active : 0;

    var analytics = document.getElementById('analytics-operations');
    if (!analytics) return;
    var values = analytics.querySelectorAll('.ck-stat-value');
    if (values.length < 4) return;
    values[0].textContent = summary.ready != null ? summary.ready : 0;
    values[1].textContent = summary.military != null ? summary.military : 0;
    values[2].textContent = summary.civilian != null ? summary.civilian : 0;
    values[3].textContent = summary.done != null ? summary.done : 0;
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-deliver-case').forEach(function (btn) {
      btn.addEventListener('click', deliverCase);
    });
    document.querySelectorAll('.btn-view-bom-items').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var caseData = findCaseData(btn.getAttribute('data-case-id'));
        if (caseData) openBomItemsModal(caseData);
      });
    });
  }

  function deliverCase(ev) {
    var btn = ev.currentTarget;
    var caseId = btn.getAttribute('data-case-id');
    if (!caseId || !window.axios) return;
    if (!window.confirm('تأكيد تسليم الطرف وإغلاق الطلب؟')) return;

    btn.disabled = true;
    axios.post('/operations/operations/' + caseId + '/deliver')
      .then(function () {
        toast('✅ تم التسليم — أُغلق الطلب بنجاح');
        refreshList();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر إتمام التسليم', true);
        btn.disabled = false;
      });
  }

  var refreshInFlight = false;
  var casesCache = [];
  var activeOpsFilter = 'all';

  function rowMatchesFilter(row) {
    if (activeOpsFilter === 'all') return true;
    return row.getAttribute('data-path') === activeOpsFilter;
  }

  function renderItemsCell(c) {
    var items = (c.bom && c.bom.items) || [];
    if (!items.length) return '<span class="text-xs text-slate-400">—</span>';
    return '<button type="button" class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50" data-case-id="' + c.id + '">عرض</button>';
  }

  function openBomItemsModal(caseData) {
    var modal = $('opsBomItemsModal');
    var tbody = $('opsBomItemsBody');
    var subtitle = $('opsBomItemsSubtitle');
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
    var modal = $('opsBomItemsModal');
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
    var btn = $('btnRefreshOps');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get('/operations/operations/list')
      .then(function (res) {
        casesCache = res.data.data || [];
        var tbody = $('opsTableBody');
        if (!tbody) return;
        tbody.innerHTML = casesCache.length
          ? casesCache.map(renderRow).join('')
          : '<tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد حالات جاهزة للتسليم.</td></tr>';
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
    var q = ($('opsSearch') && $('opsSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.ops-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      var ok = (!q || hay.indexOf(q) !== -1) && rowMatchesFilter(row);
      row.dataset.filterHidden = ok ? '0' : '1';
    });
    var tbody = $('opsTableBody');
    if (tbody && window.TablePagination && TablePagination.repaginate) {
      TablePagination.repaginate(tbody);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindTableEvents();
    var search = $('opsSearch');
    if (search) search.addEventListener('input', applyFilters);
    var filtersRoot = $('opsFilters');
    if (filtersRoot) {
      filtersRoot.addEventListener('click', function (e) {
        var btn = e.target.closest('.ops-filter');
        if (!btn) return;
        activeOpsFilter = btn.getAttribute('data-filter') || 'all';
        filtersRoot.querySelectorAll('.ops-filter').forEach(function (b) {
          b.classList.remove('active', 'bg-slate-800', 'text-white');
        });
        btn.classList.add('active', 'bg-slate-800', 'text-white');
        applyFilters();
      });
    }
    var refresh = $('btnRefreshOps');
    if (refresh) refresh.addEventListener('click', refreshList);
    var closeBomModal = $('closeOpsBomItemsModal');
    var bomModal = $('opsBomItemsModal');
    if (closeBomModal) closeBomModal.addEventListener('click', closeBomItemsModal);
    if (bomModal) bomModal.addEventListener('click', function (e) { if (e.target === bomModal) closeBomItemsModal(); });
    applyFilters();
  });
})();
