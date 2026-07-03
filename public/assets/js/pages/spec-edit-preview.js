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
    el.style.display = 'block';
  }

  function clearError() {
    var el = $('specEditError');
    if (el) el.style.display = 'none';
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

  function lockBodyScroll() {
    document.body.style.overflow = 'hidden';
  }

  function unlockBodyScrollIfNoModal() {
    if (!document.querySelector('.modal-overlay.visible')) {
      document.body.style.overflow = '';
    }
  }

  function openOverlay(id) {
    var modal = $(id);
    if (!modal) return;
    modal.classList.add('visible');
    lockBodyScroll();
  }

  function closeOverlay(id) {
    var modal = $(id);
    if (!modal) return;
    modal.classList.remove('visible');
    unlockBodyScrollIfNoModal();
  }

  function openModal() {
    openOverlay('specEditModal');
  }

  function closeModal() {
    closeOverlay('specEditModal');
    closeOverlay('specEditCatalogModal');
    state.specId = null;
    state.items = [];
    state.catalog = [];
    clearError();
  }

  function renderItems() {
    var tbody = $('specEditItemsBody');
    if (!tbody) return;
    if (!state.items.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-cell">أضف بنداً واحداً على الأقل</td></tr>';
      return;
    }
    tbody.innerHTML = state.items.map(function (item, idx) {
      return '<tr>' +
        '<td class="font-mono">' + item.stock_item_code + '</td>' +
        '<td>' + item.name + '</td>' +
        '<td><input type="number" step="1" min="1" value="' + item.qty + '" data-edit-qty="' + idx + '" class="form-control" style="width:80px;text-align:center;padding:6px 8px;"></td>' +
        '<td style="text-align:center;"><button type="button" data-edit-remove="' + idx + '" class="btn-action" style="color:#b91c1c;padding:4px 8px;">✕</button></td>' +
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
        ' style="display:block;width:100%;text-align:right;padding:10px 12px;margin-bottom:6px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-family:inherit;' + (used ? 'opacity:.45;cursor:not-allowed;' : '') + '">' +
        '<span style="font-family:monospace;font-size:12px;color:#64748b;">' + item.code + '</span> ' +
        '<span style="font-weight:700;color:#1e293b;">' + item.name + '</span></button>';
    }).join('') || '<p style="text-align:center;color:#94a3b8;padding:16px 0;">لا توجد أصناف</p>';

    list.querySelectorAll('[data-pick]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var code = btn.getAttribute('data-pick');
        var item = state.catalog.find(function (c) { return c.code === code; });
        if (!item) return;
        state.items.push({ stock_item_code: item.code, name: item.name, qty: 1 });
        renderItems();
        closeOverlay('specEditCatalogModal');
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
          var msg = data.rejected_request
            ? 'تم رفض طلب التعديل من الإدارة — لا يمكن إرسال طلب جديد على هذا التوصيف.'
            : 'لا يمكن طلب تعديل هذا التوصيف حالياً.';
          showError(msg);
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
    renderCatalog('');
    if ($('specEditCatalogSearch')) $('specEditCatalogSearch').value = '';
    openOverlay('specEditCatalogModal');
  });

  $('specEditCatalogClose')?.addEventListener('click', function () {
    closeOverlay('specEditCatalogModal');
  });
  $('specEditCatalogModal')?.addEventListener('click', function (e) {
    if (e.target === $('specEditCatalogModal')) closeOverlay('specEditCatalogModal');
  });
  $('specEditCatalogSearch')?.addEventListener('input', function () {
    renderCatalog($('specEditCatalogSearch').value);
  });

  function openSpecPreviewItemsModal(specId) {
    var source = document.getElementById('spec-preview-items-source-' + specId);
    var body = $('specPreviewItemsModalBody');
    var modal = $('specPreviewItemsModal');
    if (!source || !body || !modal) return;

    var row = document.querySelector('.spec-preview-row[data-spec-id="' + specId + '"]');
    var patientName = row && row.querySelector('td strong') ? row.querySelector('td strong').textContent : '';
    var title = $('specPreviewItemsModalTitle');
    if (title) {
      title.textContent = patientName ? '📦 بنود التوصيف — ' + patientName : '📦 بنود التوصيف';
    }

    body.innerHTML = source.innerHTML;
    openOverlay('specPreviewItemsModal');
  }

  function closeSpecPreviewItemsModal() {
    closeOverlay('specPreviewItemsModal');
    var body = $('specPreviewItemsModalBody');
    if (body) body.innerHTML = '';
  }

  function applySpecPreviewSearch() {
    var term = ($('specPreviewSearch')?.value || '').trim().toLowerCase();
    var visible = 0;
    document.querySelectorAll('.spec-preview-row').forEach(function (row) {
      var hay = row.getAttribute('data-search') || '';
      var show = !term || hay.indexOf(term) !== -1;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if ($('specPreviewVisibleCount')) {
      $('specPreviewVisibleCount').textContent = visible + ' ظاهر';
    }
    if (window.refreshPaginated) window.refreshPaginated('specPreviewTableBody');
  }

  function exportSpecPreview(type) {
    var allRows = window.__SPEC_PREVIEW_EXPORT || [];
    var term = ($('specPreviewSearch')?.value || '').trim().toLowerCase();
    var rows = allRows.filter(function (r) {
      return !term || (r.search || '').indexOf(term) !== -1;
    }).map(function (r) {
      return [r.patient, r.case_no, r.order_ref, r.submitted_at, r.items_count, r.status, r.items_summary];
    });
    var headers = ['اسم المريض', 'رقم الحالة', 'مرجع الطلب', 'تاريخ الإرسال', 'عدد البنود', 'الحالة', 'البنود'];
    if (!window.ExportKit) {
      alert('أداة التصدير غير متاحة');
      return;
    }
    if (type === 'excel') {
      ExportKit.toExcel(ExportKit.buildFilename('معاينة_التوصيفات'), headers, rows);
      return;
    }
    ExportKit.toPDF('معاينة التوصيفات المُرسَلة', headers, rows, 'لوحة التوصيف الفني');
  }

  document.querySelectorAll('.spec-preview-toggle-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openSpecPreviewItemsModal(btn.getAttribute('data-spec-id'));
    });
  });

  $('specPreviewItemsModalClose')?.addEventListener('click', closeSpecPreviewItemsModal);
  $('specPreviewItemsModalDone')?.addEventListener('click', closeSpecPreviewItemsModal);
  $('specPreviewItemsModal')?.addEventListener('click', function (e) {
    if (e.target === $('specPreviewItemsModal')) closeSpecPreviewItemsModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if ($('specEditCatalogModal')?.classList.contains('visible')) {
      closeOverlay('specEditCatalogModal');
      return;
    }
    if ($('specEditModal')?.classList.contains('visible')) {
      closeModal();
      return;
    }
    if ($('specPreviewItemsModal')?.classList.contains('visible')) {
      closeSpecPreviewItemsModal();
    }
  });

  $('specPreviewSearch')?.addEventListener('input', applySpecPreviewSearch);
  $('btnSpecPreviewExcel')?.addEventListener('click', function () { exportSpecPreview('excel'); });
  $('btnSpecPreviewPdf')?.addEventListener('click', function () { exportSpecPreview('pdf'); });
})();
