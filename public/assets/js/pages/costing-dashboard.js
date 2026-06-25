/**
 * Costing Dashboard — لوحة مستقلة وبسيطة (read-only + تأكيد).
 */
(function () {
  if (document.body.dataset.dashboard !== 'costing') return;
  if (document.body.dataset.activePage !== 'costing') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/costing/queue/list';
  var SHOW_URL = function (id) { return '/costing/queue/' + id; };
  var CONFIRM_URL = function (id) { return '/costing/queue/' + id + '/confirm'; };

  var activeCaseId = null;
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
    var search = [c.case_no, c.order_ref, c.patient && c.patient.name].join(' ');

    return '<tr class="costing-row" data-search="' + esc(search) + '">' +
      '<td><strong>' + esc(c.case_no) + '</strong></td>' +
      '<td>' + esc(c.patient && c.patient.name) + '</td>' +
      '<td>' + (isMil ? '🪖 عسكري' : '🌐 مدني') + '</td>' +
      '<td>' + (c.computed_total != null ? fmt(c.computed_total) + ' ج.م' : '—') + '</td>' +
      '<td class="col-actions">' +
        (window.TechNotesModal ? window.TechNotesModal.buttonHtml(c.tech_notes, c.case_no) : '') +
        '<button type="button" class="btn-action primary btn-open-costing" data-case-id="' + c.id + '">مراجعة</button>' +
      '</td></tr>';
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-open-costing').forEach(function (btn) {
      btn.addEventListener('click', function () { openModal(btn.getAttribute('data-case-id')); });
    });
  }

  function filterSearch() {
    var q = ($('costingSearch') && $('costingSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.costing-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function refreshList() {
    if (!window.axios || refreshInFlight) return;
    refreshInFlight = true;
    var btn = $('btnRefreshCosting');
    if (btn) { btn.disabled = true; btn.textContent = '↻ …'; }

    axios.get(LIST_URL)
      .then(function (res) {
        var cases = res.data.data || [];
        var tbody = $('costingTable');
        if (!tbody) return;
        if ($('costingBadge')) $('costingBadge').textContent = cases.length;
        if (!cases.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="empty-cell">لا توجد حالات بانتظار التكاليف.</td></tr>';
        } else {
          tbody.innerHTML = cases.map(renderRow).join('');
          bindTableEvents();
          if (window.TechNotesModal) window.TechNotesModal.bind();
        }
        if (window.TablePagination) TablePagination.refreshById('costingTable');
        filterSearch();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر التحميل'), true); })
      .finally(function () {
        refreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث'; }
      });
  }

  function openModal(caseId) {
    activeCaseId = caseId;
    axios.get(SHOW_URL(caseId))
      .then(function (res) {
        var c = res.data.case || {};
        var pricing = res.data.pricing || {};
        var canInternal = res.data.can_see_internal;

        if ($('costingModalTitle')) {
          $('costingModalTitle').textContent = '💰 ' + (c.case_no || '') + ' — ' + (c.patient && c.patient.name || '');
        }
        if ($('costingMeta')) {
          $('costingMeta').textContent = 'طلب: ' + (pricing.request_no || '—') + ' · ' + (c.pathway_label || '');
        }
        if ($('costingWacHeader')) $('costingWacHeader').style.display = canInternal ? '' : 'none';

        var body = $('costingItemsBody');
        if (body) {
          body.innerHTML = (pricing.items || []).map(function (it) {
            return '<tr>' +
              '<td><code>' + esc(it.stock_item_code) + '</code></td>' +
              '<td>' + esc(it.name) + '</td>' +
              '<td>' + esc(it.qty) + '</td>' +
              '<td>' + fmt(it.unit_price) + '</td>' +
              (canInternal ? '<td>' + fmt(it.wac_unit) + '</td>' : '') +
              '<td><strong>' + fmt(it.line_total) + '</strong></td></tr>';
          }).join('');
        }

        if ($('costingTotalDisplay')) $('costingTotalDisplay').textContent = fmt(pricing.computed_total) + ' ج.م';
        if ($('costingInternalRow')) $('costingInternalRow').style.display = canInternal ? '' : 'none';
        if ($('costingInternalDisplay') && canInternal) {
          $('costingInternalDisplay').textContent = fmt(pricing.internal_total) + ' ج.م';
        }

        var modal = $('costingModal');
        if (modal) modal.classList.add('visible');
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر فتح التفاصيل'), true); });
  }

  function closeModal() {
    activeCaseId = null;
    var modal = $('costingModal');
    if (modal) modal.classList.remove('visible');
  }

  function confirmCosting() {
    if (!activeCaseId) return;

    var btn = $('btnConfirmCosting');
    if (btn) btn.disabled = true;

    axios.post(CONFIRM_URL(activeCaseId))
      .then(function (res) {
        toast(res.data.message || 'تم التأكيد');
        closeModal();
        refreshList();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر التأكيد'), true); })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    refreshList();
    if ($('costingSearch')) $('costingSearch').addEventListener('input', filterSearch);
    if ($('btnRefreshCosting')) $('btnRefreshCosting').addEventListener('click', refreshList);
    if ($('closeCostingModal')) $('closeCostingModal').addEventListener('click', closeModal);
    if ($('btnCancelCosting')) $('btnCancelCosting').addEventListener('click', closeModal);
    if ($('btnConfirmCosting')) $('btnConfirmCosting').addEventListener('click', confirmCosting);
    var modal = $('costingModal');
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  });
})();
