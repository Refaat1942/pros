/**
 * Adjustments Desk — fitting trials queue (Axios + DB).
 */
(function () {
  if (document.body.dataset.dashboard !== 'adjustments') return;
  if (document.body.dataset.activePage !== 'adjustments') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/adjustments/adjustments/list';
  var STORE_URL = '/adjustments/adjustments';

  var casesCache = [];
  var activeCaseId = null;
  var refreshInFlight = false;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, {
        id: 'toast',
        prefix: '',
        isError: isError,
      });
      return;
    }
    var el = $('toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'toast visible' + (isError ? ' error' : '');
    setTimeout(function () { el.classList.remove('visible'); }, 4500);
  }

  function isoDate(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
  }

  function displayDate(value) {
    var iso = isoDate(value);
    if (!iso) return '—';
    var parts = iso.split('-');
    if (parts.length !== 3) return iso;
    return parts[2] + '/' + parts[1] + '/' + parts[0];
  }

  function trialStatusMeta(trial) {
    if (!trial || (!trial.trial1_date && !trial.trial2_date)) {
      return { cls: 'waiting', label: 'بانتظار' };
    }
    if (trial.trial2_date || trial.status === 'completed') {
      return { cls: 'done', label: 'مكتمل' };
    }
    return { cls: 'progress', label: 'تجربة 1' };
  }

  function renderRow(c) {
    var trial = c.fitting_trial || {};
    var status = trialStatusMeta(trial);
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var wo = c.work_order_no || c.order_ref || c.case_no || '—';
    var stageLine = c.stage_key === 'ready_delivery'
      ? esc(c.stage_label || 'جاهزة للتسليم')
      : esc(c.manufacturing_label || c.stage_label || '—');
    var search = [wo, c.case_no, c.patient && c.patient.name].join(' ');

    return '<tr class="adj-row" data-case-id="' + c.id + '" data-search="' + esc(search) + '">' +
      '<td><strong>' + esc(wo) + '</strong><div class="text-xs text-muted">' + esc(c.case_no) + '</div></td>' +
      '<td><div>' + esc(c.patient && c.patient.name) + '</div>' +
        '<span class="patient-type-badge ' + (isMil ? 'military' : 'civilian') + '">' +
        (isMil ? '🪖 عسكري' : '🌐 مدني') + '</span></td>' +
      '<td>' + stageLine + '</td>' +
      '<td>' + displayDate(trial.trial1_date) + '</td>' +
      '<td>' + displayDate(trial.trial2_date) + '</td>' +
      '<td style="max-width:180px;font-size:13px;color:var(--text-muted);">' + esc(trial.notes || '—') + '</td>' +
      '<td class="col-actions">' +
        '<button type="button" class="btn-action primary btn-open-fitting" data-case-id="' + c.id + '">تسجيل تجربة</button> ' +
        '<span class="badge ' + status.cls + '">' + status.label + '</span>' +
      '</td></tr>';
  }

  function updateAnalytics(cases) {
    var total = cases.length;
    var trial1 = cases.filter(function (c) { return c.fitting_trial && c.fitting_trial.trial1_date; }).length;
    var trial2 = cases.filter(function (c) { return c.fitting_trial && c.fitting_trial.trial2_date; }).length;
    var pending = total - trial1;

    if ($('adjBadge')) $('adjBadge').textContent = total;

    var analytics = $('analytics-adjustments');
    if (analytics) {
      var values = analytics.querySelectorAll('.ck-stat-value');
      if (values.length >= 4) {
        values[0].textContent = total;
        values[1].textContent = trial1;
        values[2].textContent = trial2;
        values[3].textContent = pending;
      }
    }

    var sumEl = $('adjSummary');
    if (sumEl) {
      sumEl.innerHTML = [
        { cls: 'wip', icon: '📏', label: 'حالات نشطة', val: total },
        { cls: 'raw', icon: '1️⃣', label: 'تجربة أولى', val: trial1 },
        { cls: 'finished', icon: '2️⃣', label: 'تجربة ثانية', val: trial2 },
      ].map(function (s) {
        return '<div class="bom-stat ' + s.cls + '"><div class="bom-stat-icon">' + s.icon + '</div>' +
          '<div><div class="bom-stat-label">' + s.label + '</div><div class="bom-stat-value">' + s.val + '</div></div></div>';
      }).join('');
    }
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-open-fitting').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openFittingModal(btn.getAttribute('data-case-id'));
      });
    });
  }

  function filterSearch() {
    var q = ($('adjSearch') && $('adjSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.adj-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function setRefreshBusy(busy) {
    var btn = $('btnRefreshAdj');
    if (!btn) return;
    btn.disabled = busy;
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

    axios.get(LIST_URL)
      .then(function (res) {
        casesCache = res.data.data || [];
        var tbody = $('adjustmentsTable');
        if (!tbody) return;

        if (!casesCache.length) {
          tbody.innerHTML = '<tr><td colspan="7" class="empty-cell">لا توجد حالات للمعدلات حالياً — تظهر بعد صرف BOM للورشة.</td></tr>';
        } else {
          tbody.innerHTML = casesCache.map(renderRow).join('');
          bindTableEvents();
        }

        updateAnalytics(casesCache);
        if (window.TablePagination) TablePagination.refreshById('adjustmentsTable');
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

  function openFittingModal(caseId) {
    var c = casesCache.find(function (row) { return String(row.id) === String(caseId); });
    if (!c) return;

    activeCaseId = c.id;
    var trial = c.fitting_trial || {};
    var modal = $('fittingModal');
    if (!modal) return;

    var title = $('fittingModalTitle');
    if (title) {
      title.textContent = '📏 ' + (c.patient && c.patient.name || '—') + ' — ' + (c.work_order_no || c.order_ref || c.case_no);
    }

    if ($('fittingTrial1')) $('fittingTrial1').value = isoDate(trial.trial1_date);
    if ($('fittingTrial2')) $('fittingTrial2').value = isoDate(trial.trial2_date);
    if ($('fittingNotes')) $('fittingNotes').value = trial.notes || '';

    modal.classList.add('visible');
  }

  function closeFittingModal() {
    var modal = $('fittingModal');
    if (modal) modal.classList.remove('visible');
    activeCaseId = null;
  }

  function validateModalFields() {
    if (!window.DashboardValidation) return true;
    var ids = ['fittingTrial1', 'fittingTrial2', 'fittingNotes'];
    for (var i = 0; i < ids.length; i++) {
      var el = $(ids[i]);
      if (el && !DashboardValidation.isFieldValid(el)) return false;
    }
    return true;
  }

  function saveFitting() {
    if (!validateModalFields()) return;
    if (!activeCaseId || !window.axios) return;

    var payload = {
      case_id: activeCaseId,
      trial1_date: ($('fittingTrial1') && $('fittingTrial1').value.trim()) || null,
      trial2_date: ($('fittingTrial2') && $('fittingTrial2').value.trim()) || null,
      notes: ($('fittingNotes') && $('fittingNotes').value.trim()) || null,
    };

    var btn = $('btnSaveFitting');
    if (btn) btn.disabled = true;

    axios.post(STORE_URL, payload)
      .then(function () {
        toast('✅ تم حفظ بيانات المعد');
        closeFittingModal();
        refreshList();
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر حفظ التجربة';
        toast(msg, true);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var refresh = $('btnRefreshAdj');
    if (refresh) refresh.addEventListener('click', refreshList);

    var search = $('adjSearch');
    if (search) search.addEventListener('input', filterSearch);

    var closeBtn = $('closeFittingModal');
    var cancelBtn = $('btnCancelFitting');
    var saveBtn = $('btnSaveFitting');
    var modal = $('fittingModal');

    if (closeBtn) closeBtn.addEventListener('click', closeFittingModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeFittingModal);
    if (saveBtn) saveBtn.addEventListener('click', saveFitting);
    if (modal) {
      modal.addEventListener('click', function (ev) {
        if (ev.target === modal) closeFittingModal();
      });
    }

    refreshList();
  });
})();
