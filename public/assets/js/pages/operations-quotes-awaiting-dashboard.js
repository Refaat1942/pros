/**
 * Operations — issued quotes awaiting entity approval (sent to reception, not OCR-approved yet).
 */
(function () {
  if (document.body.dataset.dashboard !== 'operations') return;
  if (document.body.dataset.activePage !== 'quotes-awaiting') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/operations/quotes-awaiting/list';
  var quotesCache = [];
  var refreshInFlight = false;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function fmt(n) {
    return String(Math.round(parseFloat(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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

  function renderRow(q) {
    var search = [q.quote_no, q.order_ref, q.patient_name, q.company_name, q.case && q.case.case_no].join(' ');
    var printBtn = q.print_url
      ? '<a href="' + esc(q.print_url) + '" target="_blank" rel="noopener" class="btn-action">🖨️ طباعة عرض السعر</a>'
      : '';

    return '<tr class="quotes-awaiting-row" data-quote-id="' + q.id + '" data-search="' + esc(search) + '">' +
      '<td><strong class="font-mono text-xs">' + esc(q.quote_serial || q.quote_no) + '</strong></td>' +
      '<td><div>' + esc(q.patient_name) + '</div>' +
        '<div class="text-xs text-muted">' + esc(q.company_name || '—') + '</div></td>' +
      '<td><span class="patient-type-badge civilian">' + esc(q.stage_label || '—') + '</span></td>' +
      '<td>' + (q.total ? fmt(q.total) + ' ج.م' : '—') + '</td>' +
      '<td class="col-actions" style="white-space:nowrap;">' + printBtn + '</td></tr>';
  }

  function updateAnalytics(quotes) {
    var total = quotes.length;
    var warehouse = quotes.filter(function (q) {
      return q.stage_label && q.stage_label.indexOf('المخزن') !== -1;
    }).length;

    if ($('quotesAwaitingBadge')) $('quotesAwaitingBadge').textContent = total;

    var analytics = $('analytics-quotes-awaiting');
    if (analytics) {
      var values = analytics.querySelectorAll('.ck-stat-value');
      if (values.length >= 4) {
        values[0].textContent = total;
        values[1].textContent = total;
        values[2].textContent = warehouse;
        values[3].textContent = total;
      }
    }
  }

  function filterSearch() {
    var q = ($('quotesAwaitingSearch') && $('quotesAwaitingSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.quotes-awaiting-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function refreshList() {
    if (!window.axios || refreshInFlight) return;
    refreshInFlight = true;
    var btn = $('btnRefreshQuotesAwaiting');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get(LIST_URL)
      .then(function (res) {
        quotesCache = res.data.data || [];
        var tbody = $('quotesAwaitingTable');
        if (!tbody) return;
        if (!quotesCache.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="empty-cell">لا توجد عروض بانتظار موافقة الجهة حالياً.</td></tr>';
        } else {
          tbody.innerHTML = quotesCache.map(renderRow).join('');
        }
        updateAnalytics(quotesCache);
        if (window.TablePagination) TablePagination.refreshById('quotesAwaitingTable');
        filterSearch();
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تحميل العروض.';
        toast(msg, true);
      })
      .finally(function () {
        refreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث'; }
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    refreshList();
    var search = $('quotesAwaitingSearch');
    if (search) search.addEventListener('input', filterSearch);
    var refresh = $('btnRefreshQuotesAwaiting');
    if (refresh) refresh.addEventListener('click', refreshList);
  });
})();
