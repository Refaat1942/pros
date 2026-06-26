/**

 * Operations Desk — manufacturing queue, BOM close action from operations only.

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



  var BOM_LABELS = {

    raw: { label: 'خام', cls: 'bg-amber-100 text-amber-800' },

    wip: { label: 'تحت التشغيل', cls: 'bg-cyan-100 text-cyan-800' },

    finished: { label: 'تام', cls: 'bg-emerald-100 text-emerald-800' },

  };



  function $(id) { return document.getElementById(id); }



  function toast(msg, isError) {

    if (window.DashboardToast) {

      window.DashboardToast.show(msg, { id: 'toast', isError: !!isError });

      return;

    }

    var el = $('toast');

    if (!el) {

      alert(msg);

      return;

    }

    el.textContent = msg;

    el.classList.remove('hidden');

    el.classList.add('show');

    setTimeout(function () {

      el.classList.remove('show');

      el.classList.add('hidden');

    }, 5000);

  }



  function esc(s) {

    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');

  }



  function renderActionCell(c) {

    var bomStage = c.bom && c.bom.stage;

    var printBtn = c.work_order_print_url

      ? '<a href="' + esc(c.work_order_print_url) + '" target="_blank" rel="noopener" ' +

        'class="btn-print-work-order text-xs font-bold rounded-lg border border-cyan-700 text-cyan-800 px-3 py-1.5 hover:bg-cyan-50 inline-block mb-1">🖨️ طباعة إذن شغل الورشة</a> '

      : '';



    if (c.stage_key === 'ready_delivery') {

      return printBtn + '<button type="button" class="btn-deliver-case text-xs font-bold rounded-lg bg-indigo-600 text-white px-3 py-1.5 hover:bg-indigo-700" data-case-id="' + c.id + '">✅ تم التسليم</button>';

    }

    if (bomStage === 'wip') {

      return printBtn + '<button type="button" class="btn-complete-manufacturing text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700" data-case-id="' + c.id + '">✓ تم التصنيع</button>';

    }

    return printBtn + '<span class="text-xs text-slate-400">—</span>';

  }



  function renderRow(c) {

    var isMil = c.patient_type === 'military' || c.path === 'military';

    var bomMeta = c.bom && c.bom.stage ? (BOM_LABELS[c.bom.stage] || { label: c.bom.stage, cls: 'bg-slate-100' }) : null;

    var search = [c.work_order_no, c.case_no, c.patient && c.patient.name].join(' ');



    var bomStage = c.bom && c.bom.stage ? c.bom.stage : '';

    return '<tr class="ops-row hover:bg-slate-50" data-case-id="' + c.id + '" data-search="' + esc(search) + '"' +

      ' data-bom-stage="' + esc(bomStage) + '" data-stage-key="' + esc(c.stage_key || '') + '"' +

      ' data-path="' + (isMil ? 'military' : 'civilian') + '" data-filter-hidden="0">' +

      '<td class="px-4 py-3 font-mono font-bold text-cyan-700">' + esc(c.work_order_no || '—') + '</td>' +

      '<td class="px-4 py-3"><div class="font-semibold text-slate-800">' + esc(c.patient && c.patient.name) + '</div>' +

        '<div class="text-xs text-slate-400">' + esc(c.case_no) + '</div></td>' +

      '<td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-lg ' +

        (isMil ? 'bg-indigo-100 text-indigo-700">🪖 عسكري' : 'bg-emerald-100 text-emerald-700">🌐 مدني') + '</span></td>' +

      '<td class="px-4 py-3">' +

        (bomMeta

          ? '<span class="text-xs font-bold px-2 py-1 rounded-lg ' + bomMeta.cls + '">' + bomMeta.label + '</span>'

          : '<span class="text-xs text-slate-400">بدون BOM</span>') +

      '</td>' +

      '<td class="px-4 py-3 text-center">' + renderItemsCell(c) + '</td>' +

      '<td class="px-4 py-3">' + renderActionCell(c) + '</td>' +

      '</tr>';

  }



  function updateSummary(cases, summary) {

    summary = summary || {};

    var raw = 0, wip = 0, mil = 0;

    cases.forEach(function (c) {

      if (c.patient_type === 'military' || c.path === 'military') mil++;

      if (!c.bom) return;

      if (c.bom.stage === 'raw') raw++;

      else if (c.bom.stage === 'wip') wip++;

    });

    var done = summary.done != null ? summary.done : 0;

    if ($('sumRaw')) $('sumRaw').textContent = summary.raw != null ? summary.raw : raw;

    if ($('sumWip')) $('sumWip').textContent = summary.wip != null ? summary.wip : wip;

    if ($('sumDone')) $('sumDone').textContent = done;

    if ($('sumTotal')) $('sumTotal').textContent = summary.total_active != null ? summary.total_active : cases.length;



    var analytics = document.getElementById('analytics-operations');

    if (!analytics) return;

    var values = analytics.querySelectorAll('.ck-stat-value');

    if (values.length < 4) return;

    values[0].textContent = cases.length;

    values[1].textContent = mil;

    values[2].textContent = cases.length - mil;

    values[3].textContent = raw;

  }



  function bindTableEvents() {

    document.querySelectorAll('.btn-complete-manufacturing').forEach(function (btn) {

      btn.addEventListener('click', completeManufacturing);

    });

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



  function completeManufacturing(ev) {

    var btn = ev.currentTarget;

    var caseId = btn.getAttribute('data-case-id');

    if (!caseId || !window.axios) return;



    if (!window.confirm('تأكيد تم التصنيع؟ ستُغلق قائمة المواد وتُحوَّل الحالة للتسليم.')) return;



    btn.disabled = true;

    axios.post('/operations/operations/' + caseId + '/finish-quality')

      .then(function () {

        toast('✅ تم التصنيع — الحالة جاهزة للتسليم');

        refreshList();

      })

      .catch(function (err) {

        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر إتمام التصنيع', true);

        btn.disabled = false;

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

    var bomStage = row.getAttribute('data-bom-stage') || '';
    var stageKey = row.getAttribute('data-stage-key') || '';
    var path = row.getAttribute('data-path') || '';

    if (activeOpsFilter === 'wip') return bomStage === 'wip';
    if (activeOpsFilter === 'ready') return stageKey === 'ready_delivery';
    if (activeOpsFilter === 'military') return path === 'military';
    if (activeOpsFilter === 'civilian') return path === 'civilian';

    return true;
  }



  function renderItemsCell(c) {

    var items = (c.bom && c.bom.items) || [];

    if (!items.length) {

      return '<span class="text-xs text-slate-400">—</span>';

    }

    return '<button type="button" class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50" data-case-id="' + c.id + '">عرض</button>';

  }



  function openBomItemsModal(caseData) {

    var modal = $('opsBomItemsModal');

    var tbody = $('opsBomItemsBody');

    var subtitle = $('opsBomItemsSubtitle');

    if (!modal || !tbody) return;



    var patient = (caseData.patient && caseData.patient.name) || '—';

    var caseNo = caseData.case_no || '—';

    var wo = caseData.work_order_no || '—';

    if (subtitle) {

      subtitle.textContent = patient + ' · ' + caseNo + ' · ' + wo;

    }



    var items = (caseData.bom && caseData.bom.items) || [];

    if (!items.length) {

      tbody.innerHTML = '<tr><td colspan="3" class="px-3 py-8 text-center text-slate-400">لا توجد بنود.</td></tr>';

    } else {

      tbody.innerHTML = items.map(function (item) {

        return '<tr>' +

          '<td class="px-3 py-2 font-mono text-xs text-slate-500">' + esc(item.stock_item_code) + '</td>' +

          '<td class="px-3 py-2 font-semibold text-slate-800">' + esc(item.name || item.stock_item_code) + '</td>' +

          '<td class="px-3 py-2 text-center font-bold">' + esc(item.qty) + '</td>' +

          '</tr>';

      }).join('');

    }



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

    try {

      items = JSON.parse(btn.getAttribute('data-items') || '[]');

    } catch (e) {

      items = [];

    }



    return {

      id: caseId,

      case_no: btn.getAttribute('data-case-no'),

      work_order_no: btn.getAttribute('data-work-order'),

      patient: { name: btn.getAttribute('data-patient') },

      bom: { items: items },

    };

  }



  function setRefreshBusy(busy) {

    var btn = $('btnRefreshOps');

    if (!btn) return;

    btn.disabled = busy;

    btn.setAttribute('aria-busy', busy ? 'true' : 'false');

    btn.textContent = busy ? '↻ جاري التحديث...' : '↻ تحديث';

  }



  function refreshList(ev) {

    if (ev && ev.preventDefault) ev.preventDefault();

    if (!window.axios) {

      toast('تعذّر التحديث — axios غير متاح', true);

      return;

    }

    if (refreshInFlight) return;



    refreshInFlight = true;

    setRefreshBusy(true);



    axios.get('/operations/operations/list')

      .then(function (res) {

        var cases = res.data.data || [];

        casesCache = cases;

        var tbody = $('opsTableBody');

        if (!tbody) return;

        if (!cases.length) {

          tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر تشغيل نشطة.</td></tr>';

        } else {

          tbody.innerHTML = cases.map(renderRow).join('');

          bindTableEvents();

        }

        updateSummary(cases, res.data.summary || {});

        applyFilters();

      })

      .catch(function (err) {

        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تحديث القائمة';

        toast(msg, true);

      })

      .finally(function () {

        refreshInFlight = false;

        setRefreshBusy(false);

      });

  }



  function applyFilters() {

    var q = ($('opsSearch') && $('opsSearch').value || '').trim().toLowerCase();

    document.querySelectorAll('.ops-row').forEach(function (row) {

      var hay = (row.getAttribute('data-search') || '').toLowerCase();

      var searchOk = !q || hay.indexOf(q) !== -1;

      var filterOk = rowMatchesFilter(row);

      row.dataset.filterHidden = searchOk && filterOk ? '0' : '1';

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

    else if (window.console && console.warn) console.warn('operations-dashboard: #btnRefreshOps not found');



    var closeBomModal = $('closeOpsBomItemsModal');

    var bomModal = $('opsBomItemsModal');

    if (closeBomModal) closeBomModal.addEventListener('click', closeBomItemsModal);

    if (bomModal) {

      bomModal.addEventListener('click', function (e) {

        if (e.target === bomModal) closeBomItemsModal();

      });

    }

    applyFilters();

  });

})();


