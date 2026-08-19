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
  var HISTORY_URL = function (id) { return '/cashier/payments/' + id + '/history'; };

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
    var toastEl = document.getElementById('toast') || document.getElementById('notifToast');
    if (window.DashboardToast && toastEl) {
      window.DashboardToast.show(msg, Object.assign({ id: toastEl.id, isError: !!isError }, extra || {}));
      return;
    }
    window.alert(msg);
  }

  function showPaymentError(msg) {
    var el = $('cashierPaymentError');
    if (!el) {
      toast(msg, true);
      return;
    }
    if (!msg) {
      el.textContent = '';
      el.classList.add('hidden');
      return;
    }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function loadMethods() {
    var root = $('cashierDeskRoot');
    if (!root) return;
    try { methods = JSON.parse(root.getAttribute('data-methods') || '[]'); } catch (e) { methods = []; }
  }

  var activeRemaining = 0;
  var activeManualDue = false;

  function renderRow(c) {
    var quote = c.quote || null;
    var search = [c.case_no, c.quote_no, c.patient && c.patient.name].join(' ');
    var amountDue = parseFloat(c.amount_due != null ? c.amount_due : c.amount) || 0;
    var paid = parseFloat(c.paid) || 0;
    var remaining = parseFloat(c.remaining != null ? c.remaining : Math.max(0, amountDue - paid)) || 0;
    var printBtn = quote && quote.print_url
      ? '<a href="' + esc(quote.print_url) + '" target="_blank" rel="noopener" class="text-xs font-bold rounded-lg border border-cyan-700 text-cyan-800 px-3 py-1.5 hover:bg-cyan-50 inline-block mb-1">🖨️ طباعة عرض السعر</a> '
      : '';
    var btnLabel = paid > 0 ? 'تسجيل دفعة' : 'تأكيد استلام المبلغ';

    return '<tr class="cashier-row hover:bg-slate-50" data-case-id="' + c.id + '" data-search="' + esc(search) + '" data-filter-hidden="0">' +
      '<td class="px-4 py-3"><div class="font-mono font-bold text-cyan-700">' + esc(c.case_no) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.order_ref) + '</div></td>' +
      '<td class="px-4 py-3"><div class="font-semibold text-slate-800">' + esc(c.patient && c.patient.name) + '</div>' +
        '<div class="text-xs text-slate-400">' + esc(c.patient && c.patient.phone) + '</div></td>' +
      '<td class="px-4 py-3 font-mono text-xs text-slate-600">' + esc((quote && quote.quote_no) || c.quote_no || '—') + '</td>' +
      '<td class="px-4 py-3 font-bold text-emerald-700">' + fmt(amountDue) + ' ج.م</td>' +
      '<td class="px-4 py-3 font-semibold text-slate-700">' + fmt(paid) + ' ج.م</td>' +
      '<td class="px-4 py-3 font-bold ' + (remaining > 0 ? 'text-amber-700' : 'text-emerald-700') + '">' + fmt(remaining) + ' ج.م</td>' +
      '<td class="px-4 py-3 whitespace-nowrap">' + printBtn +
        '<button type="button" class="btn-confirm-payment text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700" ' +
          'data-case-id="' + c.id + '" data-case-no="' + esc(c.case_no) + '" ' +
          'data-patient="' + esc(c.patient && c.patient.name) + '" data-amount-due="' + esc(amountDue) + '" ' +
          'data-paid="' + esc(paid) + '" data-remaining="' + esc(remaining) + '">✓ ' + btnLabel + '</button>' +
      '</td></tr>';
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-confirm-payment').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openPaymentModal(
          btn.getAttribute('data-case-id'),
          btn.getAttribute('data-case-no'),
          btn.getAttribute('data-patient'),
          parseFloat(btn.getAttribute('data-amount-due') || '0'),
          parseFloat(btn.getAttribute('data-paid') || '0'),
          parseFloat(btn.getAttribute('data-remaining') || '0')
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

  function openPaymentModal(caseId, caseNo, patient, amountDue, paid, remaining) {
    activeCaseId = caseId;
    activeRemaining = remaining || 0;
    activeManualDue = activeRemaining <= 0.009 && amountDue <= 0.009;
    showPaymentError('');
    var subtitle = $('cashierPaymentSubtitle');
    if (subtitle) subtitle.textContent = (patient || '—') + ' · ' + (caseNo || '—');
    var amountEl = $('cashierPaymentAmount');
    if (amountEl) {
      amountEl.value = activeRemaining > 0 ? activeRemaining : '';
      amountEl.removeAttribute('max');
      if (activeRemaining > 0) amountEl.max = activeRemaining;
    }
    var hint = $('cashierPaymentHint');
    if (hint) {
      hint.textContent = activeManualDue
        ? 'لم يُحسب مستحق من عرض السعر — أدخل المبلغ المستلم فعلياً ثم أكّد.'
        : 'يمكن تسجيل دفعة جزئية أو كامل المتبقي. عند اكتمال المبلغ تُعاد الحالة لمكتب التشغيل لاعتماد إصدار أمر الشغل.';
    }
    var summary = $('cashierPaymentSummary');
    if (summary) {
      summary.classList.remove('hidden');
      var dueEl = $('cashierSummaryDue');
      var paidEl = $('cashierSummaryPaid');
      var remEl = $('cashierSummaryRemaining');
      if (dueEl) dueEl.textContent = fmt(amountDue);
      if (paidEl) paidEl.textContent = fmt(paid);
      if (remEl) remEl.textContent = fmt(activeRemaining);
    }
    loadPaymentHistory(caseId, paid);
    var refEl = $('cashierPaymentReference');
    if (refEl) refEl.value = '';
    var notesEl = $('cashierPaymentNotes');
    if (notesEl) notesEl.value = '';
    renderMethodButtons();
    if (methods.length) selectMethod(methods[0].value);
    var modal = $('cashierPaymentModal');
    if (modal) modal.classList.remove('hidden');
  }

  function loadPaymentHistory(caseId, paid) {
    var wrap = $('cashierPaymentHistory');
    var list = $('cashierPaymentHistoryList');
    if (!wrap || !list || !window.axios) return;
    if (!paid || paid <= 0) {
      wrap.classList.add('hidden');
      list.innerHTML = '';
      return;
    }
    axios.get(HISTORY_URL(caseId))
      .then(function (res) {
        var rows = (res.data && res.data.data) || [];
        if (!rows.length) {
          wrap.classList.add('hidden');
          return;
        }
        wrap.classList.remove('hidden');
        list.innerHTML = rows.map(function (p) {
          return '<div class="flex items-center justify-between gap-2 border-b border-emerald-100 pb-1">' +
            '<span><strong>#' + esc(p.installment_no) + '</strong> · ' + esc(p.payment_no) + ' · ' + fmt(p.amount) + ' ج.م</span>' +
            '<a href="' + esc(p.receipt_url) + '" target="_blank" rel="noopener" class="text-emerald-800 font-bold whitespace-nowrap">🖨️ إيصال</a></div>';
        }).join('');
      })
      .catch(function () { wrap.classList.add('hidden'); });
  }

  function closePaymentModal() {
    activeCaseId = null;
    var modal = $('cashierPaymentModal');
    if (modal) modal.classList.add('hidden');
  }

  function submitPayment() {
    if (!activeCaseId) { showPaymentError('تعذّر تحديد الحالة.'); return; }
    if (!window.axios) { showPaymentError('تعذّر الاتصال بالخادم — حدّث الصفحة.'); return; }
    if (!selectedMethod) { showPaymentError('اختر وسيلة الدفع أولاً.'); return; }

    var amount = parseFloat(($('cashierPaymentAmount') && $('cashierPaymentAmount').value) || '0');
    if (!amount || amount <= 0) { showPaymentError('أدخل مبلغاً صحيحاً.'); return; }

    var reference = ($('cashierPaymentReference') && $('cashierPaymentReference').value) || null;
    if (selectedMethod !== 'cash' && !reference) {
      showPaymentError('يرجى إدخال رقم الشيك أو مرجع التحويل.');
      return;
    }

    if (!activeManualDue && amount > activeRemaining + 0.009) {
      showPaymentError('المبلغ يتجاوز المتبقي (' + fmt(activeRemaining) + ' ج.م).');
      return;
    }

    showPaymentError('');

    var confirmMsg = activeManualDue
      ? 'تأكيد استلام مبلغ ' + fmt(amount) + ' ج.م؟\n\n(تحصيل يدوي — لم يُسجَّل مستحق من عرض السعر)'
      : (amount >= activeRemaining - 0.009
        ? 'تأكيد استلام مبلغ ' + fmt(amount) + ' ج.م (اكتمال الدفع)؟\n\nسيُطبع الإيصال وتُعاد الحالة لمكتب التشغيل.'
        : 'تأكيد تسجيل دفعة ' + fmt(amount) + ' ج.م؟\n\nالمتبقي بعد الدفعة: ' + fmt(activeRemaining - amount) + ' ج.م');
    if (!window.confirm(confirmMsg)) return;

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
        var installment = res.data && res.data.payment && res.data.payment.installment_no;
        if (installment) {
          toast('إيصال دفعة #' + installment + ' — سيريال ' + (res.data.payment.payment_no || ''), false, { duration: 5000 });
        }
        closePaymentModal();
        refreshList();
        // فتح إيصال الدفع للطباعة تلقائياً.
        var receiptUrl = res.data && res.data.payment && res.data.payment.receipt_url;
        if (receiptUrl) { window.open(receiptUrl, '_blank', 'noopener'); }
      })
      .catch(function (err) { showPaymentError(apiMessage(err, 'تعذّر تأكيد الدفع')); })
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
          : '<tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">لا توجد حالات بانتظار الدفع حالياً.</td></tr>';
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
