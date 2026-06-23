/**
 * Operations Pending Desk — approve, rework, quote print.
 */
(function () {
  if (document.body.dataset.dashboard !== 'operations') return;
  if (document.body.dataset.activePage !== 'pending') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/operations/pending/list';
  var APPROVE_URL = function (id) { return '/operations/pending/' + id + '/approve'; };
  var RETURN_URL = function (id) { return '/operations/pending/' + id + '/return'; };

  var casesCache = [];
  var reworkCaseId = null;
  var refreshInFlight = false;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function fmt(n) {
    return String(Math.round(parseFloat(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function apiMessage(err, fallback) {
    var data = err && err.response && err.response.data;
    if (data && data.message) return data.message;
    return fallback || 'حدث خطأ غير متوقع.';
  }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', prefix: isError ? '' : '✅ ', isError: isError });
      return;
    }
    var el = $('toast');
    if (!el) { if (isError) window.alert(msg); return; }
    el.textContent = msg;
    el.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(function () { el.classList.remove('show'); }, 4500);
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var quote = c.quote || null;
    var quoteNo = quote ? quote.quote_no : (c.quote_no || '—');
    var total = quote ? quote.total : c.quote_total;
    var printBtn = quote && quote.print_url
      ? '<a href="' + esc(quote.print_url) + '" target="_blank" rel="noopener" class="btn-action" style="margin-left:4px;">🖨️ طباعة العرض</a>'
      : (isMil ? '<span class="text-xs text-muted">بدون عرض (عسكري)</span>' : '');

    var search = [c.case_no, c.order_ref, c.patient && c.patient.name, quoteNo].join(' ');

    return '<tr class="pending-row" data-case-id="' + c.id + '" data-search="' + esc(search) + '">' +
      '<td><strong>' + esc(c.case_no) + '</strong>' +
        '<div class="text-xs text-muted">' + esc(quoteNo) + '</div></td>' +
      '<td>' + esc(c.patient && c.patient.name) + '</td>' +
      '<td><span class="patient-type-badge ' + (isMil ? 'military' : 'civilian') + '">' +
        (isMil ? '🪖 عسكري' : '🌐 مدني') + '</span></td>' +
      '<td>' + (total ? fmt(total) + ' ج.م' : '—') + '</td>' +
      '<td class="col-actions" style="white-space:nowrap;">' +
        printBtn +
        '<button type="button" class="btn-action success btn-approve-pending" data-case-id="' + c.id + '" style="margin-left:4px;">✅ موافقة واعتماد الصرف</button>' +
        '<button type="button" class="btn-action btn-rework-pending" data-case-id="' + c.id + '" data-case-no="' + esc(c.case_no) + '" style="margin-left:4px;background:#fee2e2;color:#b91c1c;">↩️ إرجاع للتعديل</button>' +
      '</td></tr>';
  }

  function updateAnalytics(cases) {
    var total = cases.length;
    var mil = cases.filter(function (c) { return c.patient_type === 'military' || c.path === 'military'; }).length;
    var withQuote = cases.filter(function (c) { return c.quote || c.quote_no; }).length;

    if ($('pendingBadge')) $('pendingBadge').textContent = total;

    var analytics = $('analytics-pending');
    if (analytics) {
      var values = analytics.querySelectorAll('.ck-stat-value');
      if (values.length >= 4) {
        values[0].textContent = total;
        values[1].textContent = withQuote;
        values[2].textContent = total - mil;
        values[3].textContent = mil;
      }
    }
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-approve-pending').forEach(function (btn) {
      btn.addEventListener('click', function () { approveCase(btn.getAttribute('data-case-id'), btn); });
    });
    document.querySelectorAll('.btn-rework-pending').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openReworkModal(btn.getAttribute('data-case-id'), btn.getAttribute('data-case-no'));
      });
    });
  }

  function filterSearch() {
    var q = ($('pendingSearch') && $('pendingSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.pending-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function refreshList() {
    if (!window.axios || refreshInFlight) return;
    refreshInFlight = true;
    var btn = $('btnRefreshPending');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get(LIST_URL)
      .then(function (res) {
        casesCache = res.data.data || [];
        var tbody = $('pendingTable');
        if (!tbody) return;
        if (!casesCache.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="empty-cell">لا توجد حالات بانتظار موافقة مكتب التشغيل.</td></tr>';
        } else {
          tbody.innerHTML = casesCache.map(renderRow).join('');
          bindTableEvents();
        }
        updateAnalytics(casesCache);
        if (window.TablePagination) TablePagination.refreshById('pendingTable');
        filterSearch();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر تحميل الطابور'), true); })
      .finally(function () {
        refreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث'; }
      });
  }

  function approveCase(caseId, btn) {
    if (!caseId || !window.axios) return;
    if (!window.confirm('اعتماد الصرف؟ سيتم حجز المواد فوراً وتحويل الحالة للمخزن.')) return;

    if (btn) btn.disabled = true;
    axios.post(APPROVE_URL(caseId))
      .then(function (res) {
        toast(res.data.message || 'تم الاعتماد بنجاح');
        refreshList();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر الاعتماد'), true); })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  function openReworkModal(caseId, caseNo) {
    reworkCaseId = caseId;
    if ($('reworkCaseLabel')) {
      $('reworkCaseLabel').textContent = 'إرجاع الحالة ' + (caseNo || caseId) + ' للتعديل:';
    }
    if ($('reworkTarget')) $('reworkTarget').value = 'adjustments';
    if ($('reworkReason')) $('reworkReason').value = '';
    var modal = $('reworkModal');
    if (modal) modal.classList.add('show');
  }

  function closeReworkModal() {
    reworkCaseId = null;
    var modal = $('reworkModal');
    if (modal) modal.classList.remove('show');
  }

  function submitRework() {
    if (!reworkCaseId || !window.axios) return;
    if (!window.confirm('تأكيد إرجاع الحالة للتعديل؟')) return;

    var btn = $('btnSubmitRework');
    if (btn) btn.disabled = true;

    axios.post(RETURN_URL(reworkCaseId), {
      target: ($('reworkTarget') && $('reworkTarget').value) || 'adjustments',
      reason: ($('reworkReason') && $('reworkReason').value) || null,
    })
      .then(function (res) {
        toast(res.data.message || 'تمت إعادة الحالة للتعديل');
        closeReworkModal();
        refreshList();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر الإرجاع'), true); })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    refreshList();
    var search = $('pendingSearch');
    if (search) search.addEventListener('input', filterSearch);
    var refresh = $('btnRefreshPending');
    if (refresh) refresh.addEventListener('click', refreshList);
    var closeBtn = $('closeReworkModal');
    if (closeBtn) closeBtn.addEventListener('click', closeReworkModal);
    var cancelBtn = $('btnCancelRework');
    if (cancelBtn) cancelBtn.addEventListener('click', closeReworkModal);
    var submitBtn = $('btnSubmitRework');
    if (submitBtn) submitBtn.addEventListener('click', submitRework);
    var modal = $('reworkModal');
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeReworkModal(); });
  });
})();
