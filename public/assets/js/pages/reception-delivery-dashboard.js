/**
 * Reception — final QR delivery & case closure (Axios + Tailwind).
 */
(function () {
  if (document.body.dataset.activePage !== 'delivery') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var state = { caseId: null, patientQr: null };

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

  function showError(msg) {
    var el = $('deliveryError');
    if (!el) { toast(msg, true); return; }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function clearError() {
    var el = $('deliveryError');
    if (el) el.classList.add('hidden');
  }

  function selectCase(caseId, patientQr, label) {
    state.caseId = caseId;
    state.patientQr = patientQr;
    $('deliveryEmpty') && $('deliveryEmpty').classList.add('hidden');
    $('deliveryWorkspace') && $('deliveryWorkspace').classList.remove('hidden');
    clearError();

    if (!window.axios || !caseId) return;

    axios.get('/reception/delivery/' + caseId)
      .then(function (res) {
        var c = res.data;
        $('delPatientName') && ($('delPatientName').textContent = c.patient && c.patient.name || label || '—');
        $('delCaseNo') && ($('delCaseNo').textContent = c.case_no || '—');
        $('delWorkOrder') && ($('delWorkOrder').textContent = c.work_order_no || '—');
        $('delCompany') && ($('delCompany').textContent = c.company_name || '—');
        $('delBomStage') && ($('delBomStage').textContent = c.bom && c.bom.stage === 'finished' ? '✅ تام' : '—');
        if ($('deliveryQrInput') && patientQr) $('deliveryQrInput').value = patientQr;
      })
      .catch(function () {
        showError('تعذّر تحميل تفاصيل الحالة.');
      });
  }

  function confirmDelivery() {
    if (!window.axios) return;
    var qrInput = $('deliveryQrInput');
    if (window.DashboardValidation && qrInput) {
      var qrErr = DashboardValidation.validateField(qrInput);
      if (qrErr) {
        showError(qrErr);
        qrInput.focus();
        return;
      }
    }
    var qr = (qrInput && qrInput.value || '').trim();
    if (!qr) { showError('يجب مسح بطاقة المريض.'); return; }

    clearError();
    var btn = $('btnConfirmDelivery');
    if (btn) btn.disabled = true;

    axios.post('/reception/delivery/scan', { scanned_qr: qr })
      .then(function (res) {
        var data = res.data;
        var modal = $('deliverySuccessModal');
        var text = $('deliverySuccessText');
        var inv = $('deliveryInvoiceRef');

        if (text) {
          text.textContent = data.message || 'تم إغلاق الحالة بنجاح.';
        }
        if (inv) {
          if (data.invoice_no) {
            inv.textContent = 'مرجع الفاتورة: ' + data.invoice_no;
            inv.classList.remove('hidden');
          } else {
            inv.textContent = 'تم تسجيل التكلفة السيادية — مسار عسكري';
            inv.classList.remove('hidden');
          }
        }
        if (modal) modal.classList.remove('hidden');
        bumpStat('delivered', 1);
        refreshList();
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر التسليم — تحقق من QR وحالة BOM.';
        showError(msg);
        if (btn) btn.disabled = false;
      });
  }

  function setStat(key, value) {
    var el = document.querySelector('#analytics-delivery [data-stat="' + key + '"]');
    if (el) el.textContent = String(value);
  }

  function bumpStat(key, delta) {
    var el = document.querySelector('#analytics-delivery [data-stat="' + key + '"]');
    if (!el) return;
    var next = Math.max(0, parseInt(el.textContent, 10) + delta);
    el.textContent = String(isNaN(next) ? 0 : next);
  }

  function updateDeliveryStats(cases) {
    var list = cases || [];
    var military = list.filter(function (c) { return c.patient_type === 'military'; }).length;
    setStat('ready', list.length);
    setStat('military', military);
    setStat('civilian', list.length - military);
    setStat('bom_finished', list.length);
  }

  function refreshList() {
    if (!window.axios) return;
    axios.get('/reception/delivery/list')
      .then(function (res) {
        var cases = res.data.data || [];
        var list = $('deliveryList');
        var count = $('deliveryCount');
        if (count) count.textContent = cases.length;
        updateDeliveryStats(cases);

        if (!list) return;
        if (!cases.length) {
          list.innerHTML = '<li class="pagination-empty-msg px-5 py-10 text-center text-slate-400 text-sm">لا توجد حالات جاهزة للتسليم.</li>';
          $('deliveryWorkspace') && $('deliveryWorkspace').classList.add('hidden');
          $('deliveryEmpty') && $('deliveryEmpty').classList.remove('hidden');
          if (window.TablePagination) TablePagination.refreshById('deliveryList');
          return;
        }

        list.innerHTML = cases.map(function (c) {
          var qr = c.patient && c.patient.patient_qr ? c.patient.patient_qr : '';
          var search = [c.patient && c.patient.name, c.work_order_no, c.case_no].join(' ');
          return '<li class="delivery-item cursor-pointer px-5 py-4 hover:bg-emerald-50 transition-colors" data-case-id="' + c.id +
            '" data-patient-qr="' + qr + '" data-search="' + search + '">' +
            '<div class="flex items-start justify-between gap-2"><div>' +
            '<p class="font-bold text-slate-800">' + (c.patient && c.patient.name || '—') + '</p>' +
            '<p class="text-xs text-slate-500 mt-1">' + c.case_no + ' · ' + (c.work_order_no || '—') + '</p>' +
            '<p class="text-xs text-slate-400">' + (c.company_name || '—') + '</p></div>' +
            '<span class="text-[11px] font-bold px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700">BOM تام</span></div></li>';
        }).join('');

        bindListEvents();
        if (window.TablePagination) TablePagination.refreshById('deliveryList');
      })
      .catch(function () { toast('تعذّر تحديث القائمة', true); });
  }

  function bindListEvents() {
    document.querySelectorAll('.delivery-item').forEach(function (li) {
      li.addEventListener('click', function () {
        document.querySelectorAll('.delivery-item').forEach(function (x) {
          x.classList.remove('bg-emerald-50', 'ring-2', 'ring-recv/30');
        });
        li.classList.add('bg-emerald-50', 'ring-2', 'ring-recv/30');
        selectCase(li.getAttribute('data-case-id'), li.getAttribute('data-patient-qr'));
      });
    });
  }

  function filterSearch() {
    var q = ($('deliverySearch') && $('deliverySearch').value || '').trim().toLowerCase();
    var visible = 0;
    document.querySelectorAll('.delivery-item').forEach(function (li) {
      var hay = (li.getAttribute('data-search') || '').toLowerCase();
      var show = !q || hay.indexOf(q) !== -1;
      if (show) {
        delete li.dataset.paginationSkip;
        visible++;
      } else {
        li.dataset.paginationSkip = '1';
      }
    });
    var count = $('deliveryCount');
    if (count) count.textContent = visible;
    if (window.TablePagination) TablePagination.refreshById('deliveryList');
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindListEvents();

    $('deliverySearch') && $('deliverySearch').addEventListener('input', filterSearch);
    $('btnConfirmDelivery') && $('btnConfirmDelivery').addEventListener('click', confirmDelivery);
    $('deliveryQrInput') && $('deliveryQrInput').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); confirmDelivery(); }
    });

    $('btnCloseDeliverySuccess') && $('btnCloseDeliverySuccess').addEventListener('click', function () {
      $('deliverySuccessModal') && $('deliverySuccessModal').classList.add('hidden');
      $('deliveryWorkspace') && $('deliveryWorkspace').classList.add('hidden');
      $('deliveryEmpty') && $('deliveryEmpty').classList.remove('hidden');
      if ($('deliveryQrInput')) $('deliveryQrInput').value = '';
      state = { caseId: null, patientQr: null };
      var btn = $('btnConfirmDelivery');
      if (btn) btn.disabled = false;
    });
    $('deliverySuccessBackdrop') && $('deliverySuccessBackdrop').addEventListener('click', function () {
      $('btnCloseDeliverySuccess') && $('btnCloseDeliverySuccess').click();
    });
  });
})();
