/**
 * Operations Desk — manufacturing queue, WO display, stage advance (Axios).
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

  var MFG_NEXT = {
    warehouse: 'issue',
    issue: 'generation',
    generation: 'assembly',
    assembly: 'casting',
    casting: 'finishing',
  };

  var MFG_LABELS = {
    warehouse: 'المخزن',
    issue: 'صرف خامات',
    generation: 'توليد',
    assembly: 'تجميع',
    casting: 'صب',
    finishing: 'تشطيب',
  };

  var BOM_LABELS = {
    raw: { label: 'خام', cls: 'bg-amber-100 text-amber-800' },
    wip: { label: 'تحت التشغيل', cls: 'bg-cyan-100 text-cyan-800' },
    finished: { label: 'تام', cls: 'bg-emerald-100 text-emerald-800' },
  };

  function $(id) { return document.getElementById(id); }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, {
        id: 'opsToast',
        prefix: '',
        isError: isError,
        render: function (el, text, opts) {
          el.textContent = text;
          el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[300] rounded-xl px-6 py-3 text-sm font-bold shadow-lg ' +
            (opts.isError ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white');
        },
      });
      return;
    }
    var el = $('opsToast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[300] rounded-xl px-6 py-3 text-sm font-bold shadow-lg ' +
      (isError ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white');
    el.classList.remove('hidden');
    setTimeout(function () { el.classList.add('hidden'); }, 5000);
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function renderActionCell(c) {
    var bomStage = c.bom && c.bom.stage;
    var mfg = c.manufacturing_stage;

    if (mfg === 'finishing' && bomStage === 'wip') {
      return '<button type="button" class="btn-finish-quality text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700" data-case-id="' + c.id + '">✓ فحص جودة</button>';
    }
    if (bomStage === 'finished') {
      return '<span class="text-xs font-bold text-emerald-700">جاهز للتسليم</span>';
    }
    if (mfg !== 'finishing' && MFG_NEXT[mfg]) {
      return '<button type="button" class="btn-advance-stage text-xs font-bold rounded-lg bg-slate-800 text-white px-3 py-1.5 hover:bg-slate-700" data-case-id="' + c.id + '" data-mfg-stage="' + esc(mfg) + '">▶ تقدم مرحلة</button>';
    }
    return '<span class="text-xs text-slate-400">—</span>';
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var bomMeta = c.bom && c.bom.stage ? (BOM_LABELS[c.bom.stage] || { label: c.bom.stage, cls: 'bg-slate-100' }) : null;
    var itemsCount = (c.bom && c.bom.items_count) ? c.bom.items_count : 0;
    var mfgLabel = MFG_LABELS[c.manufacturing_stage] || c.manufacturing_stage || '—';
    var search = [c.work_order_no, c.case_no, c.patient && c.patient.name].join(' ');

    return '<tr class="ops-row hover:bg-slate-50" data-case-id="' + c.id + '" data-search="' + esc(search) + '">' +
      '<td class="px-4 py-3 font-mono font-bold text-cyan-700">' + esc(c.work_order_no || '—') + '</td>' +
      '<td class="px-4 py-3"><div class="font-semibold text-slate-800">' + esc(c.patient && c.patient.name) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.case_no) + '</div></td>' +
      '<td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-lg ' +
        (isMil ? 'bg-indigo-100 text-indigo-700">🪖 عسكري' : 'bg-emerald-100 text-emerald-700">🌐 مدني') + '</span></td>' +
      '<td class="px-4 py-3">' +
        (bomMeta
          ? '<span class="text-xs font-bold px-2 py-1 rounded-lg ' + bomMeta.cls + '">' + bomMeta.label + '</span>'
          : '<span class="text-xs text-slate-400">بدون BOM</span>') +
        '<div class="text-xs text-slate-500 mt-1">' + esc(mfgLabel) + '</div></td>' +
      '<td class="px-4 py-3 text-center font-bold">' + itemsCount + '</td>' +
      '<td class="px-4 py-3">' + renderActionCell(c) + '</td>' +
      '</tr>';
  }

  function updateSummary(cases) {
    var raw = 0, wip = 0, done = 0, mil = 0;
    cases.forEach(function (c) {
      if (c.patient_type === 'military' || c.path === 'military') mil++;
      if (!c.bom) return;
      if (c.bom.stage === 'raw') raw++;
      else if (c.bom.stage === 'wip') wip++;
      else if (c.bom.stage === 'finished') done++;
    });
    if ($('sumRaw')) $('sumRaw').textContent = raw;
    if ($('sumWip')) $('sumWip').textContent = wip;
    if ($('sumDone')) $('sumDone').textContent = done;
    if ($('sumTotal')) $('sumTotal').textContent = cases.length;

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
    document.querySelectorAll('.btn-advance-stage').forEach(function (btn) {
      btn.addEventListener('click', advanceStage);
    });
    document.querySelectorAll('.btn-finish-quality').forEach(function (btn) {
      btn.addEventListener('click', finishQuality);
    });
  }

  function finishQuality(ev) {
    var btn = ev.currentTarget;
    var caseId = btn.getAttribute('data-case-id');
    if (!caseId || !window.axios) return;

    if (!window.confirm('تأكيد فحص الجودة وإغلاق BOM؟')) return;

    btn.disabled = true;
    axios.post('/operations/operations/' + caseId + '/finish-quality')
      .then(function () {
        toast('✅ BOM تام — الحالة جاهزة للتسليم');
        refreshList();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر فحص الجودة', true);
        btn.disabled = false;
      });
  }

  var refreshInFlight = false;

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
        var tbody = $('opsTableBody');
        if (!tbody) return;
        if (!cases.length) {
          tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر تشغيل نشطة.</td></tr>';
        } else {
          tbody.innerHTML = cases.map(renderRow).join('');
          bindTableEvents();
        }
        updateSummary(cases);
        if (window.TablePagination) TablePagination.refreshById('opsTableBody');
        filterSearch();
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

  function advanceStage(ev) {
    var btn = ev.currentTarget;
    var caseId = btn.getAttribute('data-case-id');
    var current = btn.getAttribute('data-mfg-stage');
    var next = MFG_NEXT[current];
    if (!next || !window.axios) return;

    btn.disabled = true;
    axios.post('/operations/operations/' + caseId + '/advance', { manufacturing_stage: next })
      .then(function () {
        toast('✅ تم تقدم مرحلة التصنيع');
        refreshList();
      })
      .catch(function (err) {
        toast((err.response && err.response.data && err.response.data.message) || 'تعذّر التقدم', true);
        btn.disabled = false;
      });
  }

  function filterSearch() {
    var q = ($('opsSearch') && $('opsSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.ops-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindTableEvents();
    var search = $('opsSearch');
    if (search) search.addEventListener('input', filterSearch);
    var refresh = $('btnRefreshOps');
    if (refresh) refresh.addEventListener('click', refreshList);
    else if (window.console && console.warn) console.warn('operations-dashboard: #btnRefreshOps not found');
  });
})();
