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
    patientType: 'civilian',
    catalog: [],
    items: [],
    locked: false,
    submitting: false,
    loadingCase: false,
  };

  function isMilitaryPatient(type) {
    return (type || state.patientType) === 'military';
  }

  function submitSuccessMessage(type, requestNo) {
    var suffix = requestNo ? ' — ' + requestNo : '';
    return isMilitaryPatient(type)
      ? 'تم اعتماد التوصيف — جاهز للتشغيل' + suffix
      : 'تم الإرسال للمعدلات' + suffix;
  }

  function updateSubmitLabels(type) {
    var patientType = type || state.patientType;
    var submitBtn = $('btnSubmitSpec');
    var banner = $('specSubmittedBanner');
    if (submitBtn) {
      submitBtn.textContent = isMilitaryPatient(patientType)
        ? '📤 اعتماد وإرسال للتشغيل'
        : '📤 اعتماد وإرسال';
    }
    var bannerText = $('specSubmittedBannerText');
    if (bannerText) {
      bannerText.textContent = isMilitaryPatient(patientType)
        ? '✅ تم اعتماد التوصيف — جاهز للتشغيل'
        : '✅ تم الإرسال للمعدلات';
    }
  }

  function $(id) { return document.getElementById(id); }

  function showToast(msg, isError) {
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
    var el = $('specFormError');
    if (!el) { alert(msg); return; }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function clearError() {
    var el = $('specFormError');
    if (el) el.classList.add('hidden');
  }

  function renderReworkBanner(rework) {
    var banner = $('specReworkBanner');
    if (!banner) return;
    if (!rework) {
      banner.classList.add('hidden');
      return;
    }
    banner.classList.remove('hidden');
    if ($('specReworkTitle')) {
      $('specReworkTitle').textContent = '↩️ ' + (rework.target_label || 'إرجاع من مكتب التشغيل');
    }
    if ($('specReworkMeta')) {
      $('specReworkMeta').textContent = rework.returned_by ? ('بواسطة: ' + rework.returned_by) : '';
    }
    if ($('specReworkReason')) {
      $('specReworkReason').textContent = rework.reason || '';
    }
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
      requestEl.textContent = requestNo ? ' — ' + requestNo : '';
    }
    if (addBtn) addBtn.disabled = locked || state.submitting;
    if (notes) notes.disabled = locked;
  }

  function catalogEntry(code) {
    return state.catalog.find(function (s) { return s.code === code; });
  }

  function itemAvailableMax(code) {
    var entry = catalogEntry(code);
    return entry ? Math.max(0, parseInt(entry.available_max, 10) || 0) : 0;
  }

  function clampItemQty(code, qty) {
    var max = itemAvailableMax(code);
    if (max <= 0) return 0;
    return Math.min(max, Math.max(1, parseInt(qty, 10) || 1));
  }

  function mapSpecItems(items) {
    return (items || []).map(function (i) {
      var cat = catalogEntry(i.stock_item_code);
      return {
        stock_item_code: i.stock_item_code,
        name: i.name,
        qty: clampItemQty(i.stock_item_code, i.qty || 1),
        uom: cat ? cat.uom : '',
      };
    });
  }

  function seedItemsFromMedical(data) {
    if (!data.medical_record || !data.medical_record.items || !data.medical_record.items.length) {
      return [];
    }

    return data.medical_record.items.map(function (i) {
      var cat = catalogEntry(i.stock_item_code);
      return {
        stock_item_code: i.stock_item_code,
        name: i.name,
        qty: clampItemQty(i.stock_item_code, i.qty || 1),
        uom: cat ? cat.uom : '',
      };
    });
  }

  function validateItemQuantities(items) {
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var max = itemAvailableMax(item.stock_item_code);
      var label = item.name ? ('«' + item.name + '»') : item.stock_item_code;
      if (max <= 0) {
        return {
          message: 'الصنف ' + label + ' غير متوفر في المخزون حالياً.',
          item: item,
        };
      }
      if (item.qty > max) {
        return {
          message: 'الكمية المطلوبة للصنف ' + label + ' غير متاحة — الكمية المتاحة: ' + max + '.',
          item: item,
        };
      }
    }
    return null;
  }

  function readItemsFromInputs() {
    var tbody = $('specItemsBody');
    return state.items.map(function (item, idx) {
      var qty = item.qty || 1;
      if (tbody) {
        var input = tbody.querySelector('[data-qty-idx="' + idx + '"]');
        if (input) {
          qty = Math.max(1, parseInt(input.value, 10) || 1);
        }
      }
      return {
        stock_item_code: item.stock_item_code,
        name: item.name,
        qty: qty,
        uom: item.uom,
      };
    }).filter(function (item) {
      return item && item.stock_item_code && item.name;
    });
  }

  function highlightQtyIssue(item) {
    var tbody = $('specItemsBody');
    if (!tbody || !item) return;
    tbody.querySelectorAll('.spec-qty-input').forEach(function (input) {
      input.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
    });
    var idx = state.items.findIndex(function (row) {
      return row.stock_item_code === item.stock_item_code;
    });
    if (idx < 0) return;
    var input = tbody.querySelector('[data-qty-idx="' + idx + '"]');
    if (input) {
      input.classList.add('border-red-500', 'ring-2', 'ring-red-200');
      input.focus();
      input.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }

  function resolveItemsForSubmit() {
    return readItemsFromInputs().map(function (item) {
      return {
        stock_item_code: item.stock_item_code,
        name: item.name,
        qty: clampItemQty(item.stock_item_code, item.qty),
        uom: item.uom,
      };
    });
  }

  function renderItemsTable() {
    var tbody = $('specItemsBody');
    if (!tbody) return;
    if (!state.items.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">أضف أصنافاً من الكاتلوج</td></tr>';
      return;
    }
    tbody.innerHTML = state.items.map(function (item, idx) {
      return '<tr data-idx="' + idx + '">' +
        '<td class="px-4 py-3 font-mono text-xs">' + item.stock_item_code + '</td>' +
        '<td class="px-4 py-3 font-semibold text-slate-800">' + item.name + '</td>' +
        '<td class="px-4 py-3 text-slate-500">' + (item.uom || '—') + '</td>' +
        '<td class="px-4 py-3"><input type="number" min="1" value="' + item.qty + '" data-qty-idx="' + idx + '" ' +
          (state.locked ? 'disabled' : '') +
          ' class="spec-qty-input w-20 rounded-lg border border-slate-200 px-2 py-1 text-center text-sm"></td>' +
        '<td class="px-4 py-3 text-center">' +
          (state.locked ? '—' : '<button type="button" data-remove-idx="' + idx + '" class="text-red-500 hover:text-red-700 font-bold">✕</button>') +
        '</td></tr>';
    }).join('');

    tbody.querySelectorAll('[data-qty-idx]').forEach(function (input) {
      input.addEventListener('input', function () {
        clearError();
        input.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
      });
      input.addEventListener('change', function () {
        var i = parseInt(input.getAttribute('data-qty-idx'), 10);
        var requested = Math.max(1, parseInt(input.value, 10) || 1);
        state.items[i].qty = requested;
        input.value = String(requested);
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
    state.patientType = 'civilian';
    state.items = [];
    state.locked = false;
    state.submitting = false;
    state.loadingCase = false;
    var workspace = $('specWorkspace');
    var empty = $('emptyState');
    if (workspace) workspace.classList.add('hidden');
    if (empty) empty.classList.remove('hidden');
    setLockedUI(false);
    renderReworkBanner(null);
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
        if (c.patient_type) {
          state.patientType = c.patient_type;
        } else if (c.path === 'military') {
          state.patientType = 'military';
        } else {
          state.patientType = 'civilian';
        }
        updateSubmitLabels(state.patientType);
        state.catalog = data.stock_catalog || [];

        $('bannerName').textContent = c.patient?.name || data.medical_record?.patient_name || '—';
        $('bannerCaseNo').textContent = c.case_no || '—';
        $('bannerOrderRef').textContent = c.order_ref || '—';
        $('bannerDoctor').textContent = data.medical_record?.doctor_name || '—';
        $('bannerCompany').textContent = c.display_entity
          || (state.patientType === 'military'
            ? (c.sovereign_entity || c.patient?.sovereign_entity || 'القوات المسلحة')
            : (c.company_name || '—'));

        renderReworkBanner(c.rework || null);

        var medBox = $('medicalSummary');
        if (data.medical_record) {
          medBox.classList.remove('hidden');
          $('medDiagnosis').textContent = data.medical_record.diagnosis || '—';
          $('medPrescription').textContent = data.medical_record.prescription || '—';
        } else {
          medBox.classList.add('hidden');
        }

        if (data.submitted_spec && c.stage_key !== 'technical') {
          state.specId = data.submitted_spec.id;
          $('techNotes').value = data.submitted_spec.tech_notes || '';
          state.items = mapSpecItems(data.submitted_spec.items);
          renderItemsTable();
          openWorkspace();
          setLockedUI(true);
          return;
        }

        if (data.submitted_spec && c.stage_key === 'technical') {
          state.specId = data.submitted_spec.id;
          $('techNotes').value = data.submitted_spec.tech_notes || '';
          state.items = mapSpecItems(data.submitted_spec.items);
          renderItemsTable();
          openWorkspace();
          setLockedUI(false);
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
      var unavailable = (parseInt(item.available_max, 10) || 0) <= 0;
      return '<button type="button" data-pick-code="' + item.code + '" ' +
        (already || unavailable ? 'disabled' : '') +
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
          qty: clampItemQty(item.code, 1),
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

      var items = readItemsFromInputs();
      if (!items.length) {
        showError('أضف بنداً واحداً على الأقل');
        return;
      }

      var qtyErr = validateItemQuantities(items);
      if (qtyErr) {
        showError(qtyErr.message);
        highlightQtyIssue(qtyErr.item);
        var errBox = $('specFormError');
        if (errBox) errBox.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        return;
      }

      state.items = resolveItemsForSubmit();

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
          var pricing = res.data.pricing_request || {};
          var patientType = pricing.patient_type || state.patientType;
          var requestNo = pricing.request_no || '';
          var submittedCaseId = state.caseId;
          showToast(submitSuccessMessage(patientType, requestNo));
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
  updateSubmitLabels('civilian');

  var params = new URLSearchParams(window.location.search);
  if (params.get('case')) {
    loadCase(params.get('case'));
  }
})();
