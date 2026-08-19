/**
 * Reception — final delivery & case closure (Axios + Tailwind).
 */
(function () {
  if (document.body.dataset.activePage !== 'delivery') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var state = { caseId: null };
  var postReturnBoms = [];

  var POST_RETURN_CREATE_URL = '/reception/returns/create?post_delivery=1';
  var POST_RETURN_STORE_URL = '/reception/returns';

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function showPostReturnError(msg) {
    var el = $('postReturnError');
    if (!el) { toast(msg, true); return; }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function clearPostReturnError() {
    var el = $('postReturnError');
    if (el) el.classList.add('hidden');
  }

  function renderPostReturnLines() {
    var sel = $('postReturnBomSelect');
    var container = $('postReturnLines');
    if (!sel || !container) return;

    var bom = postReturnBoms.find(function (b) { return String(b.id) === String(sel.value); });
    if (!bom || !(bom.items || []).length) {
      container.innerHTML = '<p class="text-sm text-slate-500">لا بنود قابلة للارتجاع.</p>';
      return;
    }

    container.innerHTML = bom.items.map(function (it) {
      var max = it.returnable_qty || 0;
      return '<label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-amber-50/50">' +
        '<input type="checkbox" class="post-return-line-chk mt-1" data-code="' + esc(it.stock_item_code) + '" data-name="' + esc(it.name || it.stock_item_code) + '" checked>' +
        '<span class="flex-1 text-sm">' +
          '<span class="font-bold text-slate-800">' + esc(it.name || it.stock_item_code) + '</span>' +
          '<span class="block text-xs text-slate-500 mt-1">كود: ' + esc(it.stock_item_code) + ' · الحد: ' + max + '</span>' +
        '</span>' +
        '<input type="number" min="1" max="' + max + '" value="' + max + '" class="post-return-line-qty w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm text-center">' +
      '</label>';
    }).join('');
  }

  function loadPostReturnBoms() {
    if (!window.axios) return Promise.reject(new Error('axios'));
    return axios.get(POST_RETURN_CREATE_URL).then(function (res) {
      postReturnBoms = (res.data.boms || []).filter(function (b) { return (b.items || []).length > 0; });
      return postReturnBoms;
    });
  }

  function openPostDeliveryReturnModal() {
    if (!window.axios) {
      toast('تعذّر الاتصال — أعد تحميل الصفحة.', true);
      return;
    }

    clearPostReturnError();
    loadPostReturnBoms()
      .then(function (boms) {
        if (!boms.length) {
          toast('لا توجد حالات مُسلَّمة بمواد قابلة للارتجاع.', true);
          return;
        }

        var sel = $('postReturnBomSelect');
        if (!sel) return;

        sel.innerHTML = boms.map(function (b) {
          var label = b.bom_no + ' — ' + (b.patient_name || '—') + ' (' + (b.work_order_no || '—') + ')';
          return '<option value="' + b.id + '">' + esc(label) + '</option>';
        }).join('');

        if ($('postReturnReason')) $('postReturnReason').value = '';
        renderPostReturnLines();
        $('postDeliveryReturnModal') && $('postDeliveryReturnModal').classList.remove('hidden');
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تحميل الحالات المُسلَّمة';
        toast(msg, true);
      });
  }

  function closePostDeliveryReturnModal() {
    $('postDeliveryReturnModal') && $('postDeliveryReturnModal').classList.add('hidden');
    clearPostReturnError();
  }

  function submitPostDeliveryReturn() {
    if (!window.axios) return;

    var bomId = $('postReturnBomSelect') && $('postReturnBomSelect').value;
    var reason = ($('postReturnReason') && $('postReturnReason').value || '').trim();
    if (!bomId) {
      showPostReturnError('اختر قائمة مواد.');
      return;
    }
    if (reason.length < 3) {
      showPostReturnError('سبب الارتجاع مطلوب (3 أحرف على الأقل).');
      return;
    }

    var lines = [];
    document.querySelectorAll('.post-return-line-chk:checked').forEach(function (chk) {
      var card = chk.closest('label');
      var qtyEl = card && card.querySelector('.post-return-line-qty');
      var qty = parseInt(qtyEl && qtyEl.value, 10) || 0;
      if (qty > 0) {
        lines.push({
          stock_item_code: chk.getAttribute('data-code'),
          name: chk.getAttribute('data-name'),
          qty: qty,
        });
      }
    });

    if (!lines.length) {
      showPostReturnError('اختر بنداً واحداً على الأقل.');
      return;
    }

    clearPostReturnError();
    var btn = $('btnSubmitPostReturn');
    if (btn) btn.disabled = true;

    axios.post(POST_RETURN_STORE_URL, { bom_id: bomId, reason: reason, lines: lines })
      .then(function (res) {
        closePostDeliveryReturnModal();
        toast(res.data.message || 'تم إرسال طلب الارتجاع للمخزن.');
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر إرسال طلب الارتجاع';
        showPostReturnError(msg);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

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

  function setConfirmButtonsDisabled(disabled) {
    ['btnConfirmDelivery', 'btnConfirmDeliveryHeader'].forEach(function (id) {
      var btn = $(id);
      if (btn) btn.disabled = disabled;
    });
  }

  function toggleConfirmButtons(show) {
    var headerBtn = $('btnConfirmDeliveryHeader');
    if (headerBtn) headerBtn.classList.toggle('hidden', !show);
  }

  function selectCase(caseId, label) {
    state.caseId = caseId;
    $('deliveryEmpty') && $('deliveryEmpty').classList.add('hidden');
    $('deliveryWorkspace') && $('deliveryWorkspace').classList.remove('hidden');
    toggleConfirmButtons(true);
    setConfirmButtonsDisabled(false);
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
        var typeEl = $('delPatientType');
        if (typeEl) {
          if (c.patient_type === 'military') {
            typeEl.textContent = '🪖 عسكري';
            typeEl.className = 'text-[11px] font-bold px-2 py-1 rounded-lg shrink-0 bg-indigo-100 text-indigo-700';
          } else {
            typeEl.textContent = '🌐 مدني';
            typeEl.className = 'text-[11px] font-bold px-2 py-1 rounded-lg shrink-0 bg-cyan-100 text-cyan-700';
          }
        }
        var finEl = $('delFinishedAt');
        if (finEl) {
          finEl.textContent = c.bom && c.bom.finished_at
            ? new Date(c.bom.finished_at).toLocaleString('ar-EG')
            : '—';
        }
      })
      .catch(function () {
        showError('تعذّر تحميل تفاصيل الحالة.');
      });
  }

  function confirmDelivery() {
    if (!window.axios) {
      showError('تعذّر الاتصال — أعد تحميل الصفحة.');
      return;
    }
    if (!state.caseId) {
      showError('اختر حالة للتسليم أولاً.');
      return;
    }

    if (!window.confirm('تأكيد تسليم الطرف وإغلاق الحالة؟')) return;

    clearError();
    setConfirmButtonsDisabled(true);

    axios.post('/reception/delivery/' + state.caseId + '/confirm')
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
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر التسليم — تحقق من حالة BOM.';
        showError(msg);
        setConfirmButtonsDisabled(false);
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
          var search = [c.patient && c.patient.name, c.work_order_no, c.case_no].join(' ');
          var typeBadge = c.patient_type === 'military'
            ? '<span class="inline-block mt-2 text-[10px] font-bold px-2 py-0.5 rounded bg-indigo-100 text-indigo-700">🪖 عسكري</span>'
            : '<span class="inline-block mt-2 text-[10px] font-bold px-2 py-0.5 rounded bg-cyan-100 text-cyan-700">🌐 مدني</span>';
          return '<li class="delivery-item cursor-pointer px-5 py-4 hover:bg-emerald-50 transition-colors" data-case-id="' + c.id +
            '" data-search="' + search + '">' +
            '<div class="flex items-start justify-between gap-2"><div>' +
            '<p class="font-bold text-slate-800">' + (c.patient && c.patient.name || '—') + '</p>' +
            '<p class="text-xs text-slate-500 mt-1">' + c.case_no + ' · ' + (c.work_order_no || '—') + '</p>' +
            '<p class="text-xs text-slate-400">' + (c.company_name || '—') + '</p>' + typeBadge + '</div>' +
            '<span class="text-[11px] font-bold px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 shrink-0">BOM تام</span></div></li>';
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
        selectCase(li.getAttribute('data-case-id'));
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
    $('btnConfirmDeliveryHeader') && $('btnConfirmDeliveryHeader').addEventListener('click', confirmDelivery);

    $('btnPostDeliveryReturn') && $('btnPostDeliveryReturn').addEventListener('click', openPostDeliveryReturnModal);
    $('postReturnBomSelect') && $('postReturnBomSelect').addEventListener('change', renderPostReturnLines);
    $('btnCancelPostReturn') && $('btnCancelPostReturn').addEventListener('click', closePostDeliveryReturnModal);
    $('btnSubmitPostReturn') && $('btnSubmitPostReturn').addEventListener('click', submitPostDeliveryReturn);
    $('postDeliveryReturnBackdrop') && $('postDeliveryReturnBackdrop').addEventListener('click', closePostDeliveryReturnModal);

    $('btnCloseDeliverySuccess') && $('btnCloseDeliverySuccess').addEventListener('click', function () {
      $('deliverySuccessModal') && $('deliverySuccessModal').classList.add('hidden');
      $('deliveryWorkspace') && $('deliveryWorkspace').classList.add('hidden');
      $('deliveryEmpty') && $('deliveryEmpty').classList.remove('hidden');
      toggleConfirmButtons(false);
      state = { caseId: null };
      setConfirmButtonsDisabled(false);
    });
    $('deliverySuccessBackdrop') && $('deliverySuccessBackdrop').addEventListener('click', function () {
      $('btnCloseDeliverySuccess') && $('btnCloseDeliverySuccess').click();
    });
  });
})();
