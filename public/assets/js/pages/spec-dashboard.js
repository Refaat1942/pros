/**
 * Spec Dashboard — AJAX wiring to SpecService (financial blindness enforced server-side).
 */
(function () {
  if (document.body.dataset.dashboard !== 'spec') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var state = {
    caseId: null,
    specId: null,
    catalog: [],
    items: [],
    locked: false,
    submitting: false,
    loadingCase: false,
  };

  function $(id) { return document.getElementById(id); }

  function showToast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, {
        id: 'specToast',
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
    var el = $('specToast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[300] rounded-xl px-6 py-3 text-sm font-bold shadow-lg ' +
      (isError ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white');
    el.classList.remove('hidden');
    setTimeout(function () { el.classList.add('hidden'); }, 5000);
  }

  function showError(msg) {
    var el = $('specFormError');
    if (!el) { alert(msg); return; }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function clearError() {
    var el = $('specFormError');
    if (el) el.classList.add('hidden');
  }

  function setLockedUI(locked, requestNo) {
    state.locked = !!locked;
    var submitBtn = $('btnSubmitSpec');
    var banner = $('specSubmittedBanner');
    var requestEl = $('specSubmittedRequestNo');
    var addBtn = $('btnAddCatalogItem');
    var notes = $('techNotes');

    if (submitBtn) {
      if (locked) {
        submitBtn.classList.add('hidden');
        submitBtn.disabled = true;
      } else {
        submitBtn.classList.remove('hidden');
        submitBtn.disabled = state.submitting;
      }
    }

    if (banner) {
      banner.classList.toggle('hidden', !locked);
    }
    if (requestEl) {
      requestEl.textContent = requestNo ? '— ' + requestNo : '';
    }
    if (addBtn) addBtn.disabled = locked || state.submitting;
    if (notes) notes.disabled = locked;
  }

  function mapSpecItems(items) {
    return (items || []).map(function (i) {
      var cat = state.catalog.find(function (s) { return s.code === i.stock_item_code; });
      return {
        stock_item_code: i.stock_item_code,
        name: i.name,
        qty: i.qty || 1,
        category: cat ? cat.category : '',
        uom: cat ? cat.uom : '',
      };
    });
  }

  function seedItemsFromMedical(data) {
    if (!data.medical_record || !data.medical_record.items || !data.medical_record.items.length) {
      return [];
    }

    return data.medical_record.items.map(function (i) {
      var cat = state.catalog.find(function (s) { return s.code === i.stock_item_code; });
      return {
        stock_item_code: i.stock_item_code,
        name: i.name,
        qty: i.qty || 1,
        category: cat ? cat.category : '',
        uom: cat ? cat.uom : '',
      };
    });
  }

  function syncQtyFromInputs() {
    var tbody = $('specItemsBody');
    if (!tbody) return;
    tbody.querySelectorAll('[data-qty-idx]').forEach(function (input) {
      var idx = parseInt(input.getAttribute('data-qty-idx'), 10);
      if (!isNaN(idx) && state.items[idx]) {
        state.items[idx].qty = Math.max(1, parseInt(input.value, 10) || 1);
      }
    });
  }

  function resolveItemsForSubmit() {
    syncQtyFromInputs();
    return state.items.filter(function (item) {
      return item && item.stock_item_code && item.name;
    });
  }

  function renderItemsTable() {
    var tbody = $('specItemsBody');
    if (!tbody) return;
    if (!state.items.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">أضف أصنافاً من الكاتلوج</td></tr>';
      return;
    }
    tbody.innerHTML = state.items.map(function (item, idx) {
      return '<tr data-idx="' + idx + '">' +
        '<td class="px-4 py-3 font-mono text-xs">' + item.stock_item_code + '</td>' +
        '<td class="px-4 py-3 font-semibold text-slate-800">' + item.name + '</td>' +
        '<td class="px-4 py-3 text-slate-500">' + (item.category || '—') + '</td>' +
        '<td class="px-4 py-3 text-slate-500">' + (item.uom || '—') + '</td>' +
        '<td class="px-4 py-3"><input type="number" min="1" value="' + item.qty + '" data-qty-idx="' + idx + '" ' +
          (state.locked ? 'disabled' : '') +
          ' class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-center text-sm"></td>' +
        '<td class="px-4 py-3 text-center">' +
          (state.locked ? '—' : '<button type="button" data-remove-idx="' + idx + '" class="text-red-500 hover:text-red-700 font-bold">✕</button>') +
        '</td></tr>';
    }).join('');

    tbody.querySelectorAll('[data-qty-idx]').forEach(function (input) {
      input.addEventListener('change', function () {
        var i = parseInt(input.getAttribute('data-qty-idx'), 10);
        state.items[i].qty = Math.max(1, parseInt(input.value, 10) || 1);
      });
    });
    tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-remove-idx'), 10);
        state.items.splice(i, 1);
        renderItemsTable();
      });
    });
  }

  function openWorkspace() {
    $('emptyState').classList.add('hidden');
    $('specWorkspace').classList.remove('hidden');
  }

  function resetWorkspace() {
    state.caseId = null;
    state.specId = null;
    state.items = [];
    state.locked = false;
    state.submitting = false;
    state.loadingCase = false;
    var workspace = $('specWorkspace');
    var empty = $('emptyState');
    if (workspace) workspace.classList.add('hidden');
    if (empty) empty.classList.remove('hidden');
    setLockedUI(false);
    clearError();
  }

  function countOrders() {
    return document.querySelectorAll('.order-item[data-case-id]').length;
  }

  function updateOrdersCounts() {
    var count = countOrders();
    var badge = $('ordersCount');
    if (badge) badge.textContent = String(count);
    var root = $('specOrdersRoot');
    if (root) root.dataset.casesCount = String(count);
    var statValue = document.querySelector('#analytics-orders .ck-stat-value');
    if (statValue) statValue.textContent = String(count);
  }

  function ensureOrdersEmptyState() {
    var list = $('ordersList');
    if (!list || countOrders() > 0) return;
    list.innerHTML = '<li class="px-5 py-10 text-center text-slate-400 text-sm orders-empty-msg">لا توجد حالات بانتظار التوصيف الفني.</li>';
  }

  function removeCaseFromOrdersList(caseId) {
    var list = $('ordersList');
    if (!list || !caseId) return;
    var item = list.querySelector('.order-item[data-case-id="' + caseId + '"]');
    if (item) item.remove();
    var emptyMsg = list.querySelector('.orders-empty-msg');
    if (emptyMsg) emptyMsg.remove();
    updateOrdersCounts();
    ensureOrdersEmptyState();
    if (window.TablePagination) TablePagination.refreshById('ordersList');
  }

  function loadCase(caseId) {
    clearError();
    state.caseId = caseId;
    state.specId = null;
    state.items = [];
    state.locked = false;
    state.submitting = false;
    state.loadingCase = true;
    setLockedUI(false);
    renderItemsTable();

    var submitBtn = $('btnSubmitSpec');
    if (submitBtn) submitBtn.disabled = true;

    document.querySelectorAll('.order-item').forEach(function (li) {
      li.classList.toggle('bg-amber-50', li.dataset.caseId === String(caseId));
      li.classList.toggle('ring-2', li.dataset.caseId === String(caseId));
      li.classList.toggle('ring-spec/30', li.dataset.caseId === String(caseId));
    });

    axios.get('/spec/spec/' + caseId)
      .then(function (res) {
        var data = res.data;
        var c = data.case;
        state.catalog = data.stock_catalog || [];

        $('bannerName').textContent = c.patient?.name || data.medical_record?.patient_name || '—';
        $('bannerCaseNo').textContent = c.case_no || '—';
        $('bannerOrderRef').textContent = c.order_ref || '—';
        $('bannerDoctor').textContent = data.medical_record?.doctor_name || '—';
        $('bannerCompany').textContent = c.company_name || '—';

        var medBox = $('medicalSummary');
        if (data.medical_record) {
          medBox.classList.remove('hidden');
          $('medDiagnosis').textContent = data.medical_record.diagnosis || '—';
          $('medPrescription').textContent = data.medical_record.prescription || '—';
        } else {
          medBox.classList.add('hidden');
        }

        if (data.submitted_spec) {
          state.specId = data.submitted_spec.id;
          $('techNotes').value = data.submitted_spec.tech_notes || '';
          state.items = mapSpecItems(data.submitted_spec.items);
          renderItemsTable();
          openWorkspace();
          setLockedUI(true);
          return;
        }

        if (data.draft) {
          state.specId = data.draft.id;
          $('techNotes').value = data.draft.tech_notes || '';
        } else {
          $('techNotes').value = '';
        }

        var draftItems = data.draft?.items?.length ? mapSpecItems(data.draft.items) : [];
        state.items = draftItems.length ? draftItems : seedItemsFromMedical(data);

        renderItemsTable();
        openWorkspace();
        setLockedUI(false);
      })
      .catch(function (err) {
        var msg = err.response?.data?.message || 'تعذّر تحميل بيانات الحالة';
        showError(msg);
      })
      .finally(function () {
        state.loadingCase = false;
        var submitBtnDone = $('btnSubmitSpec');
        if (submitBtnDone && !state.locked) {
          submitBtnDone.disabled = state.submitting;
        }
      });
  }

  function payloadItems() {
    return resolveItemsForSubmit().map(function (i) {
      return { stock_item_code: i.stock_item_code, name: i.name, qty: i.qty };
    });
  }

  function saveDraft() {
    if (!state.caseId || state.locked) return Promise.reject();
    var items = payloadItems();
    var body = {
      case_id: state.caseId,
      tech_notes: $('techNotes').value.trim() || null,
      items: items,
    };
    if (state.specId) {
      return axios.put('/spec/spec/' + state.specId, body);
    }
    return axios.post('/spec/spec', body).then(function (res) {
      state.specId = res.data.id;
      return res;
    });
  }

  function handleValidationError(err) {
    var data = err.response?.data;
    var msgs = [data?.message].filter(Boolean);
    if (data?.errors) {
      Object.values(data.errors).forEach(function (arr) {
        arr.forEach(function (m) { msgs.push(m); });
      });
    }
    showError(msgs.join(' — ') || 'خطأ في الحفظ');
  }

  function bindOrdersList() {
    document.querySelectorAll('.order-item[data-case-id]').forEach(function (li) {
      li.addEventListener('click', function () {
        loadCase(li.dataset.caseId);
      });
    });

    var search = $('ordersSearch');
    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        document.querySelectorAll('.order-item[data-case-id]').forEach(function (li) {
          var hay = (li.dataset.search || '').toLowerCase();
          li.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
        });
      });
    }
  }

  function renderCatalogList(filter) {
    var list = $('catalogList');
    if (!list) return;
    var q = (filter || '').trim().toLowerCase();
    var items = state.catalog.filter(function (item) {
      if (!q) return true;
      return (item.code + ' ' + item.name + ' ' + (item.spec || '')).toLowerCase().indexOf(q) !== -1;
    });
    list.innerHTML = items.map(function (item) {
      var already = state.items.some(function (i) { return i.stock_item_code === item.code; });
      return '<button type="button" data-pick-code="' + item.code + '" ' +
        (already ? 'disabled' : '') +
        ' class="w-full text-right px-4 py-3 rounded-xl hover:bg-amber-50 border border-transparent hover:border-amber-200 mb-1 disabled:opacity-40">' +
        '<span class="font-mono text-xs text-slate-500">' + item.code + '</span> ' +
        '<span class="font-bold text-slate-800">' + item.name + '</span>' +
        (item.spec ? '<span class="block text-xs text-slate-400 mt-1">' + item.spec + '</span>' : '') +
        '</button>';
    }).join('') || '<p class="text-center text-slate-400 py-6">لا توجد أصناف</p>';

    list.querySelectorAll('[data-pick-code]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var code = btn.getAttribute('data-pick-code');
        var item = state.catalog.find(function (c) { return c.code === code; });
        if (!item) return;
        state.items.push({
          stock_item_code: item.code,
          name: item.name,
          qty: 1,
          category: item.category,
          uom: item.uom,
        });
        renderItemsTable();
        clearError();
        $('catalogModal').classList.add('hidden');
      });
    });
  }

  function bindCatalogModal() {
    var modal = $('catalogModal');
    var openBtn = $('btnAddCatalogItem');
    var closeBtn = $('closeCatalogModal');
    var search = $('catalogSearch');

    if (openBtn) openBtn.addEventListener('click', function () {
      if (state.locked) return;
      renderCatalogList('');
      modal.classList.remove('hidden');
    });
    if (closeBtn) closeBtn.addEventListener('click', function () { modal.classList.add('hidden'); });
    if (modal) modal.addEventListener('click', function (e) {
      if (e.target === modal) modal.classList.add('hidden');
    });
    if (search) search.addEventListener('input', function () { renderCatalogList(search.value); });
  }

  function bindActions() {
    var submitBtn = $('btnSubmitSpec');

    if (submitBtn) submitBtn.addEventListener('click', function () {
      if (state.locked || state.submitting || state.loadingCase) return;

      var items = resolveItemsForSubmit();
      if (!items.length) {
        showError('أضف بنداً واحداً على الأقل');
        return;
      }

      state.items = items;

      var notes = $('techNotes');
      if (window.DashboardValidation && notes) {
        var notesErr = DashboardValidation.validateField(notes);
        if (notesErr) {
          showError(notesErr);
          notes.focus();
          return;
        }
      }

      clearError();
      state.submitting = true;
      submitBtn.disabled = true;

      if (!window.axios) {
        state.submitting = false;
        submitBtn.disabled = false;
        showError('تعذّر الاتصال بالخادم — أعد تحميل الصفحة.');
        return;
      }

      saveDraft()
        .then(function () { return axios.post('/spec/spec/' + state.specId + '/submit'); })
        .then(function (res) {
          state.submitting = false;
          var requestNo = res.data.pricing_request?.request_no || '';
          var submittedCaseId = state.caseId;
          showToast('تم الإرسال للتسعير — ' + requestNo);
          removeCaseFromOrdersList(submittedCaseId);
          resetWorkspace();
        })
        .catch(function (err) {
          state.submitting = false;
          if (!state.locked) {
            submitBtn.disabled = false;
            $('btnAddCatalogItem').disabled = false;
          }
          handleValidationError(err);
        });
    });
  }

  bindOrdersList();
  bindCatalogModal();
  bindActions();

  var params = new URLSearchParams(window.location.search);
  if (params.get('case')) {
    loadCase(params.get('case'));
  }
})();
