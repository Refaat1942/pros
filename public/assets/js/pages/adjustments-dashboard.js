/**
 * Adjustments Desk (المعدلات) — review spec BOM (read-only) + add items, then push to costing.
 */
(function () {
  if (document.body.dataset.dashboard !== 'adjustments') return;
  if (document.body.dataset.activePage !== 'adjustments') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/adjustments/adjustments/list';
  var SHOW_URL = function (id) { return '/adjustments/adjustments/' + id; };
  var ADD_URL = function (id) { return '/adjustments/adjustments/' + id + '/items'; };
  var COMPLETE_URL = function (id) { return '/adjustments/adjustments/' + id + '/complete'; };

  var casesCache = [];
  var catalogCache = [];
  var activeCase = null;
  var refreshInFlight = false;
  var pickerOpen = false;
  var pickerSearchQuery = '';

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function apiMessage(err, fallback) {
    var data = err && err.response && err.response.data;
    if (data && data.message) return data.message;
    if (data && data.errors) {
      var first = Object.values(data.errors)[0];
      if (first && first[0]) return first[0];
    }
    return fallback || 'حدث خطأ غير متوقع.';
  }

  function clearFormError() {
    var el = $('adjFormError');
    if (el) { el.textContent = ''; el.style.display = 'none'; }
  }

  function showError(msg) {
    clearFormError();
    var el = $('adjFormError');
    if (el) {
      el.textContent = msg;
      el.style.display = 'block';
    }
    window.alert(msg);
  }

  function toast(msg, isError) {
    if (isError) {
      showError(msg);
      return;
    }
    clearFormError();
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', prefix: '✅ ', isError: false });
      return;
    }
    var el = $('toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'toast show';
    setTimeout(function () { el.classList.remove('show'); }, 4500);
  }

  function bomItemsOf(c) {
    return (c.bom && c.bom.items) || [];
  }

  function catalogItemAvailable(item) {
    if (!item) return 0;
    if (item.available != null) return Math.max(0, parseInt(item.available, 10) || 0);
    return Math.max(0, (parseInt(item.qty, 10) || 0) - (parseInt(item.reserved, 10) || 0));
  }

  function qtyAlreadyInBom(code) {
    if (!activeCase || !code) return 0;
    return bomItemsOf(activeCase)
      .filter(function (it) { return it.stock_item_code === code; })
      .reduce(function (sum, it) { return sum + (parseInt(it.qty, 10) || 0); }, 0);
  }

  function maxAddableQty(catalogItem) {
    return Math.max(0, catalogItemAvailable(catalogItem) - qtyAlreadyInBom(catalogItem.code));
  }

  function findCatalogItem(code) {
    return catalogCache.filter(function (i) { return i.code === code; })[0] || null;
  }

  function getSelectedItemCode() {
    var el = $('adjItemValue');
    return el ? el.value : '';
  }

  function setSelectedItemCode(code) {
    var hidden = $('adjItemValue');
    var label = $('adjItemPickerLabel');
    if (hidden) hidden.value = code || '';
    var item = code ? findCatalogItem(code) : null;
    if (label) {
      label.textContent = item ? (item.code + ' — ' + item.name) : '— اختر الصنف —';
    }
    syncItemQtyLimits();
  }

  function renderItemPickerList() {
    var list = $('adjItemPickerList');
    if (!list) return;

    var q = pickerSearchQuery.trim().toLowerCase();
    var items = catalogCache.filter(function (item) {
      if (!q) return true;
      return (item.code + ' ' + item.name).toLowerCase().indexOf(q) !== -1;
    });

    if (!items.length) {
      list.innerHTML = '<li class="adj-picker-empty">لا توجد نتائج</li>';
      return;
    }

    var selectedCode = getSelectedItemCode();
    list.innerHTML = items.map(function (item) {
      var maxQty = maxAddableQty(item);
      var disabled = maxQty < 1;
      var selected = selectedCode === item.code;
      var label = item.code + ' — ' + item.name;

      if (disabled) {
        return '<li class="adj-picker-option is-disabled" aria-disabled="true">' +
          esc(label) + ' <span class="adj-picker-muted">(غير متاح)</span></li>';
      }

      return '<li class="adj-picker-option' + (selected ? ' is-selected' : '') + '" role="option"' +
        ' data-code="' + esc(item.code) + '" tabindex="0">' + esc(label) + '</li>';
    }).join('');
  }

  function refreshItemPicker() {
    var previous = getSelectedItemCode();
    if (previous && findCatalogItem(previous) && maxAddableQty(findCatalogItem(previous)) > 0) {
      setSelectedItemCode(previous);
    } else {
      setSelectedItemCode('');
    }
    renderItemPickerList();
  }

  function openItemPicker() {
    var picker = $('adjItemPicker');
    var toggle = $('adjItemPickerToggle');
    var search = $('adjItemPickerSearch');
    var modal = $('adjModal');
    if (!picker) return;

    pickerOpen = true;
    picker.classList.add('is-open');
    if (modal) modal.classList.add('adj-picker-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');

    pickerSearchQuery = '';
    if (search) search.value = '';
    renderItemPickerList();

    if (search) search.focus();
  }

  function closeItemPicker() {
    var picker = $('adjItemPicker');
    var toggle = $('adjItemPickerToggle');
    var modal = $('adjModal');
    pickerOpen = false;
    if (picker) picker.classList.remove('is-open');
    if (modal) modal.classList.remove('adj-picker-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function resetItemPicker() {
    pickerSearchQuery = '';
    var search = $('adjItemPickerSearch');
    if (search) search.value = '';
    setSelectedItemCode('');
    closeItemPicker();
  }

  function syncItemQtyLimits() {
    var qtyEl = $('adjItemQty');
    var addBtn = $('btnAddAdjItem');
    if (!qtyEl) return;

    var item = findCatalogItem(getSelectedItemCode());
    var maxQty = item ? maxAddableQty(item) : 0;
    var hasItem = !!item && maxQty > 0;

    qtyEl.disabled = !hasItem;
    qtyEl.max = hasItem ? String(maxQty) : '';
    qtyEl.min = hasItem ? '1' : '0';

    if (!hasItem) {
      qtyEl.value = '1';
      if (addBtn) addBtn.disabled = true;
      return;
    }

    var current = parseInt(qtyEl.value, 10);
    if (!current || current < 1) current = 1;
    if (current > maxQty) current = maxQty;
    qtyEl.value = String(current);

    if (addBtn) addBtn.disabled = false;
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var items = bomItemsOf(c);
    var search = [c.case_no, c.order_ref, c.patient && c.patient.name].join(' ');

    return '<tr class="adj-row" data-case-id="' + c.id + '" data-search="' + esc(search) + '">' +
      '<td><strong>' + esc(c.case_no) + '</strong><div class="text-xs text-muted">' + esc(c.order_ref) + '</div></td>' +
      '<td>' + esc(c.patient && c.patient.name) + '</td>' +
      '<td><span class="patient-type-badge ' + (isMil ? 'military' : 'civilian') + '">' +
        (isMil ? '🪖 عسكري' : '🌐 مدني') + '</span></td>' +
      '<td>' + items.length + '</td>' +
      '<td class="col-actions">' +
        (window.TechNotesModal ? window.TechNotesModal.buttonHtml(c.tech_notes, c.case_no) : '') +
        '<button type="button" class="btn-action primary btn-open-adj" data-case-id="' + c.id + '">مراجعة وإضافة</button>' +
      '</td></tr>';
  }

  function updateAnalytics(cases) {
    var total = cases.length;
    var mil = cases.filter(function (c) { return c.patient_type === 'military' || c.path === 'military'; }).length;
    var civ = total - mil;
    var avg = total ? Math.round(cases.reduce(function (s, c) { return s + bomItemsOf(c).length; }, 0) / total) : 0;

    if ($('adjBadge')) $('adjBadge').textContent = total;

    var analytics = $('analytics-adjustments');
    if (analytics) {
      var values = analytics.querySelectorAll('.ck-stat-value');
      if (values.length >= 4) {
        values[0].textContent = total;
        values[1].textContent = mil;
        values[2].textContent = civ;
        values[3].textContent = avg;
      }
    }
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-open-adj').forEach(function (btn) {
      btn.addEventListener('click', function () { openModal(btn.getAttribute('data-case-id')); });
    });
  }

  function filterSearch() {
    var q = ($('adjSearch') && $('adjSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.adj-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function setRefreshBusy(busy) {
    var btn = $('btnRefreshAdj');
    if (!btn) return;
    btn.disabled = busy;
    btn.textContent = busy ? '↻ جاري التحديث...' : '↻ تحديث';
  }

  function refreshList(ev) {
    if (ev && ev.preventDefault) ev.preventDefault();
    if (!window.axios) { toast('تعذّر التحديث — axios غير متاح', true); return; }
    if (refreshInFlight) return;

    refreshInFlight = true;
    setRefreshBusy(true);

    axios.get(LIST_URL)
      .then(function (res) {
        casesCache = res.data.data || [];
        var tbody = $('adjustmentsTable');
        if (!tbody) return;

        if (!casesCache.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="empty-cell">لا توجد حالات بالمعدلات حالياً — تظهر بعد إرسال التوصيف الفني.</td></tr>';
        } else {
          tbody.innerHTML = casesCache.map(renderRow).join('');
          bindTableEvents();
          if (window.TechNotesModal) window.TechNotesModal.bind();
        }

        updateAnalytics(casesCache);
        if (window.TablePagination) TablePagination.refreshById('adjustmentsTable');
        filterSearch();
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تحديث القائمة';
        toast(msg, true);
      })
      .finally(function () {
        refreshInFlight = false;
        setRefreshBusy(false);
      });
  }

  function renderBomItems(items) {
    var tbody = $('adjBomItems');
    if (!tbody) return;
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-cell">لا توجد بنود بعد.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(function (it) {
      var ro = it.read_only || it.source === 'spec';
      return '<tr>' +
        '<td>' + esc(it.stock_item_code) + '</td>' +
        '<td>' + esc(it.name) + '</td>' +
        '<td>' + esc(it.qty) + '</td>' +
        '<td>' + (ro
          ? '<span class="badge">🔒 الفني</span>'
          : '<span class="badge done">معدّلات</span>') + '</td>' +
        '</tr>';
    }).join('');
  }

  function renderReworkBanner(rework) {
    var banner = $('adjReworkBanner');
    if (!banner) return;
    if (!rework) {
      banner.hidden = true;
      return;
    }
    banner.hidden = false;
    if ($('adjReworkTitle')) {
      $('adjReworkTitle').textContent = '↩️ ' + (rework.target_label || 'إرجاع من مكتب التشغيل');
    }
    if ($('adjReworkMeta')) {
      $('adjReworkMeta').textContent = rework.returned_by ? ('بواسطة: ' + rework.returned_by) : '';
    }
    if ($('adjReworkReason')) {
      $('adjReworkReason').textContent = rework.reason || '';
    }
  }

  function openModal(caseId) {
    if (!window.axios) return;
    axios.get(SHOW_URL(caseId))
      .then(function (res) {
        activeCase = res.data.case;
        var modal = $('adjModal');
        if (!modal) return;

        var title = $('adjModalTitle');
        if (title) {
          title.textContent = '🧩 ' + ((activeCase.patient && activeCase.patient.name) || '—') + ' — ' + activeCase.case_no;
        }

        renderBomItems((activeCase.bom && activeCase.bom.items) || []);
        renderReworkBanner(activeCase.rework || null);

        catalogCache = res.data.stock_catalog || [];
        resetItemPicker();
        refreshItemPicker();
        clearFormError();

        modal.classList.add('visible');
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر فتح الحالة'));
      });
  }

  function closeModal() {
    var modal = $('adjModal');
    if (modal) modal.classList.remove('visible');
    activeCase = null;
    resetItemPicker();
    renderReworkBanner(null);
  }

  function addItem() {
    if (!activeCase || !window.axios) return;
    var code = getSelectedItemCode();
    var catalogItem = findCatalogItem(code);
    var qty = parseInt(($('adjItemQty') && $('adjItemQty').value) || '0', 10);
    var maxQty = catalogItem ? maxAddableQty(catalogItem) : 0;

    if (!catalogItem || !code) { showError('اختر الصنف من القائمة'); return; }
    if (!qty || qty < 1) { showError('أدخل كمية صحيحة'); return; }
    if (qty > maxQty) {
      showError('الكمية تتجاوز المتاح — الحد الأقصى: ' + maxQty);
      return;
    }

    var btn = $('btnAddAdjItem');
    if (btn) btn.disabled = true;

    axios.post(ADD_URL(activeCase.id), {
      items: [{ stock_item_code: code, name: catalogItem.name, qty: qty }],
    })
      .then(function (res) {
        clearFormError();
        toast('تمت إضافة البند');
        if (activeCase.bom) {
          activeCase.bom.items = (res.data.bom && res.data.bom.items) || [];
        }
        renderBomItems((res.data.bom && res.data.bom.items) || []);
        refreshItemPicker();
        resetItemPicker();
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر إضافة البند'));
      })
      .finally(function () { syncItemQtyLimits(); });
  }

  function completeAdjustments() {
    if (!activeCase || !window.axios) return;
    var btn = $('btnCompleteAdj');
    if (btn) btn.disabled = true;

    axios.post(COMPLETE_URL(activeCase.id), {})
      .then(function () {
        toast('✅ تم إغلاق المعدلات ودفع الحالة للتكاليف');
        closeModal();
        refreshList();
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر إغلاق المعدلات'));
      })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var refresh = $('btnRefreshAdj');
    if (refresh) refresh.addEventListener('click', refreshList);

    var search = $('adjSearch');
    if (search) search.addEventListener('input', filterSearch);

    var closeBtn = $('closeAdjModal');
    var cancelBtn = $('btnCancelAdj');
    var addBtn = $('btnAddAdjItem');
    var completeBtn = $('btnCompleteAdj');
    var modal = $('adjModal');
    var qtyInput = $('adjItemQty');
    var picker = $('adjItemPicker');
    var pickerToggle = $('adjItemPickerToggle');
    var pickerSearch = $('adjItemPickerSearch');
    var pickerList = $('adjItemPickerList');

    if (pickerToggle) {
      pickerToggle.addEventListener('click', function () {
        if (pickerOpen) closeItemPicker();
        else openItemPicker();
      });
    }

    if (pickerSearch) {
      pickerSearch.addEventListener('input', function () {
        pickerSearchQuery = pickerSearch.value || '';
        renderItemPickerList();
      });
      pickerSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          closeItemPicker();
        }
      });
    }

    if (pickerList) {
      pickerList.addEventListener('click', function (e) {
        var opt = e.target.closest('.adj-picker-option');
        if (!opt || opt.classList.contains('is-disabled')) return;
        setSelectedItemCode(opt.getAttribute('data-code'));
        closeItemPicker();
      });
    }

    document.addEventListener('click', function (e) {
      if (!pickerOpen || !picker) return;
      if (!picker.contains(e.target)) closeItemPicker();
    });

    if (qtyInput) {
      qtyInput.addEventListener('input', function () {
        var item = findCatalogItem(getSelectedItemCode());
        if (!item) return;
        var maxQty = maxAddableQty(item);
        var val = parseInt(qtyInput.value, 10);
        if (val > maxQty) qtyInput.value = String(maxQty);
        if (val < 1 && qtyInput.value !== '') qtyInput.value = '1';
      });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (addBtn) addBtn.addEventListener('click', addItem);
    if (completeBtn) completeBtn.addEventListener('click', completeAdjustments);
    if (modal) {
      modal.addEventListener('click', function (ev) { if (ev.target === modal) closeModal(); });
    }

    refreshList();
  });
})();
