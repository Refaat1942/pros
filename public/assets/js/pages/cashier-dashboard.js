/**
 * Cashier Desk — collect cash payments, then release case to warehouse.
 */
(function () {
  if (document.body.dataset.dashboard !== 'cashier') return;
  if (document.body.dataset.activePage !== 'payments') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/cashier/payments/list';
  var CONFIRM_URL = function (id) { return '/cashier/payments/' + id + '/confirm'; };

  var methods = [];
  var selectedMethod = null;
  var activeCaseId = null;

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
    if (data && data.errors) {
      var first = Object.keys(data.errors)[0];
      if (first) return data.errors[first][0];
    }
    return fallback || 'حدث خطأ غير متوقع.';
  }

  function toast(msg, isError, extra) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, Object.assign({ id: 'toast', isError: !!isError }, extra || {}));
      return;
    }
    if (isError) window.alert(msg);
  }

  function loadMethods() {
    var root = $('cashierDeskRoot');
    if (!root) return;
    try { methods = JSON.parse(root.getAttribute('data-methods') || '[]'); } catch (e) { methods = []; }
  }

  function renderRow(c) {
    var quote = c.quote || null;
    var search = [c.case_no, c.quote_no, c.patient && c.patient.name].join(' ');
    var printBtn = quote && quote.print_url
      ? '<a href="' + esc(quote.print_url) + '" target="_blank" rel="noopener" class="text-xs font-bold rounded-lg border border-cyan-700 text-cyan-800 px-3 py-1.5 hover:bg-cyan-50 inline-block mb-1">🖨️ طباعة عرض السعر</a> '
      : '';

    return '<tr class="cashier-row hover:bg-slate-50" data-case-id="' + c.id + '" data-search="' + esc(search) + '" data-filter-hidden="0">' +
      '<td class="px-4 py-3"><div class="font-mono font-bold text-cyan-700">' + esc(c.case_no) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.order_ref) + '</div></td>' +
      '<td class="px-4 py-3"><div class="font-semibold text-slate-800">' + esc(c.patient && c.patient.name) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.patient && c.patient.phone) + '</div></td>' +
      '<td class="px-4 py-3 font-mono text-xs text-slate-600">' + esc((quote && quote.quote_no) || c.quote_no || '—') + '</td>' +
      '<td class="px-4 py-3 font-bold text-emerald-700">' + fmt(c.amount) + ' ج.م</td>' +
      '<td class="px-4 py-3 whitespace-nowrap">' + printBtn +
        '<button type="button" class="btn-confirm-payment text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700" ' +
          'data-case-id="' + c.id + '" data-case-no="' + esc(c.case_no) + '" ' +
          'data-patient="' + esc(c.patient && c.patient.name) + '" data-amount="' + esc(c.amount) + '">✓ تأكيد استلام المبلغ</button>' +
      '</td></tr>';
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-confirm-payment').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openPaymentModal(
          btn.getAttribute('data-case-id'),
          btn.getAttribute('data-case-no'),
          btn.getAttribute('data-patient'),
          btn.getAttribute('data-amount')
        );
      });
    });
  }

  function renderMethodButtons() {
    var wrap = $('cashierPaymentMethods');
    if (!wrap) return;
    wrap.innerHTML = methods.map(function (m) {
      return '<button type="button" class="cashier-method rounded-xl border border-slate-200 px-2 py-2 text-xs font-bold text-slate-700 hover:border-emerald-400" data-method="' + esc(m.value) + '">' + esc(m.label) + '</button>';
    }).join('');
    wrap.querySelectorAll('.cashier-method').forEach(function (btn) {
      btn.addEventListener('click', function () { selectMethod(btn.getAttribute('data-method')); });
    });
    selectedMethod = null;
  }

  function selectMethod(value) {
    selectedMethod = value;
    var wrap = $('cashierPaymentMethods');
    if (!wrap) return;
    wrap.querySelectorAll('.cashier-method').forEach(function (btn) {
      var on = btn.getAttribute('data-method') === value;
      btn.classList.toggle('bg-emerald-600', on);
      btn.classList.toggle('text-white', on);
      btn.classList.toggle('border-emerald-600', on);
    });
    syncReferenceField(value);
  }

  // الكاش: المرجع اختياري؛ التحويل/الشيك: مطلوب مع تسمية مناسبة.
  function syncReferenceField(value) {
    var label = $('cashierPaymentReferenceLabel');
    var input = $('cashierPaymentReference');
    var isCash = value === 'cash';
    var text = value === 'bank_cheque'
      ? 'رقم الشيك المصرفي'
      : (value === 'bank_transfer' ? 'رقم/مرجع التحويل' : 'رقم العملية (اختياري)');
    if (label) label.textContent = text;
    if (input) input.placeholder = isCash ? 'اختياري' : text;
  }

  function openPaymentModal(caseId, caseNo, patient, amount) {
    activeCaseId = caseId;
    var subtitle = $('cashierPaymentSubtitle');
    if (subtitle) subtitle.textContent = (patient || '—') + ' · ' + (caseNo || '—');
    var amountEl = $('cashierPaymentAmount');
    if (amountEl) amountEl.value = amount || '';
    var refEl = $('cashierPaymentReference');
    if (refEl) refEl.value = '';
    var notesEl = $('cashierPaymentNotes');
    if (notesEl) notesEl.value = '';
    renderMethodButtons();
    if (methods.length) selectMethod(methods[0].value);
    var modal = $('cashierPaymentModal');
    if (modal) modal.classList.remove('hidden');
  }

  function closePaymentModal() {
    activeCaseId = null;
    var modal = $('cashierPaymentModal');
    if (modal) modal.classList.add('hidden');
  }

  function submitPayment() {
    if (!activeCaseId || !window.axios) return;
    if (!selectedMethod) { toast('اختر وسيلة الدفع أولاً.', true); return; }

    var amount = parseFloat(($('cashierPaymentAmount') && $('cashierPaymentAmount').value) || '0');
    if (!amount || amount <= 0) { toast('أدخل مبلغاً صحيحاً.', true); return; }

    var reference = ($('cashierPaymentReference') && $('cashierPaymentReference').value) || null;
    if (selectedMethod !== 'cash' && !reference) {
      toast('يرجى إدخال رقم الشيك أو مرجع التحويل.', true);
      return;
    }

    if (!window.confirm('تأكيد استلام مبلغ ' + fmt(amount) + ' ج.م؟\n\nسيُطبع إيصال الدفع وتُعاد الحالة لمكتب التشغيل.')) return;

    var btn = $('btnSubmitCashierPayment');
    if (btn) btn.disabled = true;

    axios.post(CONFIRM_URL(activeCaseId), {
      method: selectedMethod,
      amount: amount,
      reference: reference,
      notes: ($('cashierPaymentNotes') && $('cashierPaymentNotes').value) || null,
    })
      .then(function (res) {
        toast((res.data && res.data.message) || 'تم تأكيد استلام المبلغ.', false, { title: 'تم التحصيل', type: 'success', duration: 7000 });
        closePaymentModal();
        refreshList();
        // فتح إيصال الدفع للطباعة تلقائياً.
        var receiptUrl = res.data && res.data.payment && res.data.payment.receipt_url;
        if (receiptUrl) { window.open(receiptUrl, '_blank', 'noopener'); }
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر تأكيد الدفع'), true); })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  var refreshInFlight = false;

  function applyFilters() {
    var q = ($('cashierSearch') && $('cashierSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.cashier-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.dataset.filterHidden = (!q || hay.indexOf(q) !== -1) ? '0' : '1';
    });
    var tbody = $('cashierTableBody');
    if (tbody && window.TablePagination && TablePagination.repaginate) {
      TablePagination.repaginate(tbody);
    }
  }

  function refreshList() {
    if (!window.axios || refreshInFlight) return;
    refreshInFlight = true;
    var btn = $('btnRefreshCashier');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get(LIST_URL)
      .then(function (res) {
        var cases = (res.data && res.data.data) || [];
        var tbody = $('cashierTableBody');
        if (!tbody) return;
        tbody.innerHTML = cases.length
          ? cases.map(renderRow).join('')
          : '<tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">لا توجد حالات بانتظار الدفع حالياً.</td></tr>';
        bindTableEvents();
        applyFilters();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر تحديث القائمة'), true); })
      .finally(function () {
        refreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث'; }
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadMethods();
    bindTableEvents();
    var search = $('cashierSearch');
    if (search) search.addEventListener('input', applyFilters);
    var refresh = $('btnRefreshCashier');
    if (refresh) refresh.addEventListener('click', refreshList);
    var closeBtn = $('closeCashierPaymentModal');
    if (closeBtn) closeBtn.addEventListener('click', closePaymentModal);
    var cancelBtn = $('btnCancelCashierPayment');
    if (cancelBtn) cancelBtn.addEventListener('click', closePaymentModal);
    var submitBtn = $('btnSubmitCashierPayment');
    if (submitBtn) submitBtn.addEventListener('click', submitPayment);
    var modal = $('cashierPaymentModal');
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closePaymentModal(); });
    applyFilters();
  });
})();
