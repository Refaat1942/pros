/**
 * Spec preview — طلب تعديل التوصيف بعد الإرسال للمعدلات.
 */
(function () {
  if (document.body.dataset.dashboard !== 'spec') return;
  if (document.body.dataset.activePage !== 'spec') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var state = {
    specId: null,
    catalog: [],
    items: [],
    submitting: false,
  };

  function $(id) { return document.getElementById(id); }

  function showError(msg) {
    var el = $('specEditError');
    if (!el) { alert(msg); return; }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function clearError() {
    var el = $('specEditError');
    if (el) el.classList.add('hidden');
  }

  function parseItemQty(value, fallback) {
    if (value === '' || value === null || value === undefined) {
      return fallback !== undefined ? fallback : 0;
    }
    var n = parseInt(value, 10);
    return isNaN(n) ? (fallback !== undefined ? fallback : 0) : n;
  }

  function readItemsFromInputs() {
    var tbody = $('specEditItemsBody');
    return state.items.map(function (item, idx) {
      var qty = item.qty || 1;
      if (tbody) {
        var input = tbody.querySelector('[data-edit-qty="' + idx + '"]');
        if (input) {
          qty = parseItemQty(input.value, item.qty || 1);
        }
      }
      return {
        stock_item_code: item.stock_item_code,
        name: item.name,
        qty: qty,
      };
    });
  }

  function openModal() {
    var modal = $('specEditModal');
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  }

  function closeModal() {
    var modal = $('specEditModal');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    state.specId = null;
    state.items = [];
    state.catalog = [];
    clearError();
  }

  function renderItems() {
    var tbody = $('specEditItemsBody');
    if (!tbody) return;
    if (!state.items.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="px-3 py-6 text-center text-slate-400">أضف بنداً واحداً على الأقل</td></tr>';
      return;
    }
    tbody.innerHTML = state.items.map(function (item, idx) {
      return '<tr>' +
        '<td class="px-3 py-2 font-mono text-xs">' + item.stock_item_code + '</td>' +
        '<td class="px-3 py-2">' + item.name + '</td>' +
        '<td class="px-3 py-2"><input type="number" step="1" value="' + item.qty + '" data-edit-qty="' + idx + '" class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-center text-sm"></td>' +
        '<td class="px-3 py-2 text-center"><button type="button" data-edit-remove="' + idx + '" class="text-red-500 font-bold">✕</button></td>' +
        '</tr>';
    }).join('');

    tbody.querySelectorAll('[data-edit-qty]').forEach(function (input) {
      input.addEventListener('input', function () {
        clearError();
        var i = parseInt(input.getAttribute('data-edit-qty'), 10);
        if (!isNaN(i) && state.items[i]) {
          state.items[i].qty = parseItemQty(input.value, state.items[i].qty);
        }
      });
      input.addEventListener('change', function () {
        var i = parseInt(input.getAttribute('data-edit-qty'), 10);
        var requested = parseItemQty(input.value, state.items[i].qty);
        state.items[i].qty = requested;
        input.value = String(requested);
      });
    });
    tbody.querySelectorAll('[data-edit-remove]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-edit-remove'), 10);
        state.items.splice(i, 1);
        renderItems();
      });
    });
  }

  function renderCatalog(filter) {
    var list = $('specEditCatalogList');
    if (!list) return;
    var q = (filter || '').trim().toLowerCase();
    var items = state.catalog.filter(function (item) {
      if (!q) return true;
      return (item.code + ' ' + item.name).toLowerCase().indexOf(q) !== -1;
    });
    list.innerHTML = items.map(function (item) {
      var used = state.items.some(function (i) { return i.stock_item_code === item.code; });
      return '<button type="button" data-pick="' + item.code + '" ' + (used ? 'disabled' : '') +
        ' class="w-full text-right px-3 py-2 rounded-xl hover:bg-violet-50 border border-transparent hover:border-violet-100 mb-1 disabled:opacity-40">' +
        '<span class="font-mono text-xs text-slate-500">' + item.code + '</span> ' +
        '<span class="font-bold text-slate-800">' + item.name + '</span></button>';
    }).join('') || '<p class="text-center text-slate-400 py-4">لا توجد أصناف</p>';

    list.querySelectorAll('[data-pick]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var code = btn.getAttribute('data-pick');
        var item = state.catalog.find(function (c) { return c.code === code; });
        if (!item) return;
        state.items.push({ stock_item_code: item.code, name: item.name, qty: 1 });
        renderItems();
        $('specEditCatalogModal').classList.add('hidden');
        $('specEditCatalogModal').classList.remove('flex');
      });
    });
  }

  function loadEditContext(specId) {
    if (!window.axios) {
      showError('تعذّر الاتصال — أعد تحميل الصفحة.');
      return;
    }
    axios.get('/spec/spec/' + specId + '/edit-request')
      .then(function (res) {
        var data = res.data;
        if (!data.can_request_edit) {
          showError('لا يمكن طلب تعديل هذا التوصيف حالياً.');
          return;
        }
        state.specId = specId;
        state.catalog = data.stock_catalog || [];
        state.items = (data.items || []).map(function (i) {
          return { stock_item_code: i.stock_item_code, name: i.name, qty: parseInt(i.qty, 10) || 1 };
        });
        if ($('specEditModalTitle')) {
          $('specEditModalTitle').textContent = '✏️ طلب تعديل — ' + (data.spec?.patient_name || '');
        }
        if ($('specEditModalMeta')) {
          $('specEditModalMeta').textContent = (data.spec?.order_ref || '') + ' · ' + (data.case_stage || '');
        }
        if ($('specEditNotes')) {
          $('specEditNotes').value = data.spec?.tech_notes || '';
        }
        renderItems();
        clearError();
        openModal();
      })
      .catch(function (err) {
        showError(err.response?.data?.message || 'تعذّر تحميل بيانات التوصيف.');
      });
  }

  function submitEditRequest() {
    if (!state.specId || state.submitting) return;

    var items = readItemsFromInputs();
    if (!items.length) {
      showError('أضف بنداً واحداً على الأقل.');
      return;
    }
    for (var i = 0; i < items.length; i++) {
      if (parseItemQty(items[i].qty, 0) < 1) {
        showError('الكمية يجب أن تكون 1 على الأقل لكل بند.');
        return;
      }
    }
    state.items = items;

    state.submitting = true;
    var btn = $('specEditSubmit');
    if (btn) btn.disabled = true;
    clearError();

    axios.post('/spec/spec/' + state.specId + '/edit-request', {
      tech_notes: ($('specEditNotes')?.value || '').trim() || null,
      items: state.items.map(function (i) {
        return { stock_item_code: i.stock_item_code, name: i.name, qty: parseInt(i.qty, 10) || 1 };
      }),
    })
      .then(function () {
        if (window.DashboardToast) {
          window.DashboardToast.show('تم إرسال طلب التعديل للإدارة — بانتظار الموافقة.', { id: 'toast' });
        }
        closeModal();
        window.location.reload();
      })
      .catch(function (err) {
        showError(err.response?.data?.message || 'تعذّر إرسال الطلب.');
      })
      .finally(function () {
        state.submitting = false;
        if (btn) btn.disabled = false;
      });
  }

  document.querySelectorAll('.spec-edit-open-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      loadEditContext(btn.getAttribute('data-spec-id'));
    });
  });

  $('specEditModalClose')?.addEventListener('click', closeModal);
  $('specEditCancel')?.addEventListener('click', closeModal);
  $('specEditSubmit')?.addEventListener('click', submitEditRequest);
  $('specEditModal')?.addEventListener('click', function (e) {
    if (e.target === $('specEditModal')) closeModal();
  });

  $('specEditAddItem')?.addEventListener('click', function () {
    var modal = $('specEditCatalogModal');
    if (!modal) return;
    renderCatalog('');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  });

  $('specEditCatalogClose')?.addEventListener('click', function () {
    var modal = $('specEditCatalogModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  });
  $('specEditCatalogSearch')?.addEventListener('input', function () {
    renderCatalog($('specEditCatalogSearch').value);
  });
})();
