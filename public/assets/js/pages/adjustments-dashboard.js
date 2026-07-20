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
  var REMOVE_URL = function (caseId, itemId) { return '/adjustments/adjustments/' + caseId + '/items/' + itemId; };
  var UPDATE_QTY_URL = function (caseId, itemId) { return '/adjustments/adjustments/' + caseId + '/items/' + itemId; };
  var COMPLETE_URL = function (id) { return '/adjustments/adjustments/' + id + '/complete'; };
  var EDIT_REQUEST_URL = function (id) { return '/adjustments/adjustments/' + id + '/edit-request'; };

  var casesCache = [];
  var catalogCache = [];
  var activeCase = null;
  var modalMode = 'direct';
  var editRequestItems = [];
  var refreshInFlight = false;
  var pickerOpen = false;
  var pickerSearchQuery = '';
  var pickerSelectedCodes = {};

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
    // يُسمح بالمتاح السالب (backorder) — لا نُقيّده بالصفر.
    if (item.available != null) return parseInt(item.available, 10) || 0;
    return (parseInt(item.qty, 10) || 0) - (parseInt(item.reserved, 10) || 0);
  }

  function qtyAlreadyInBom(code) {
    if (!activeCase || !code) return 0;
    if (modalMode === 'edit_request') {
      return editRequestItems
        .filter(function (it) { return it.stock_item_code === code; })
        .reduce(function (sum, it) { return sum + (parseInt(it.qty, 10) || 0); }, 0);
    }
    return bomItemsOf(activeCase)
      .filter(function (it) { return it.stock_item_code === code; })
      .reduce(function (sum, it) { return sum + (parseInt(it.qty, 10) || 0); }, 0);
  }

  // المتبقّي قبل الدخول في السالب — قد يكون سالباً (يُسمح بالبيع بالسالب/طلب توريد).
  function maxAddableQty(catalogItem) {
    return catalogItemAvailable(catalogItem) - qtyAlreadyInBom(catalogItem.code);
  }

  function findCatalogItem(code) {
    return catalogCache.filter(function (i) { return i.code === code; })[0] || null;
  }

  function getSelectedCodes() {
    // يُسمح باختيار أي صنف حتى لو كان المتاح صفراً/سالباً (بيع بالسالب).
    return Object.keys(pickerSelectedCodes).filter(function (code) {
      return pickerSelectedCodes[code] && findCatalogItem(code);
    });
  }

  function clearPickerSelection() {
    pickerSelectedCodes = {};
    updatePickerLabel();
    updateCatalogFooter();
  }

  function updatePickerLabel() {
    var label = $('adjItemPickerLabel');
    if (!label) return;

    var codes = getSelectedCodes();
    if (!codes.length) {
      label.textContent = '— اختر صنف/أصناف —';
      return;
    }

    if (codes.length === 1) {
      var item = findCatalogItem(codes[0]);
      label.textContent = item ? (item.code + ' — ' + item.name) : codes[0];
      return;
    }

    label.textContent = codes.length + ' أصناف محدّدة';
  }

  function updateCatalogFooter() {
    var count = getSelectedCodes().length;
    var hint = $('adjCatalogSelectedHint');
    var btn = $('btnAdjCatalogAdd');
    if (hint) {
      hint.textContent = count
        ? ('محدّد: ' + count + ' صنف — الكمية تُطبَّق على كل صنف')
        : 'حدّد الأصناف بالـ checkbox';
    }
    if (btn) {
      btn.disabled = count < 1;
      btn.textContent = count ? ('إضافة ' + count + ' صنف') : 'إضافة المحدّد';
    }
  }

  function setPickerCodeSelected(code, selected) {
    if (!code) return;
    if (selected) pickerSelectedCodes[code] = true;
    else delete pickerSelectedCodes[code];
    updatePickerLabel();
    syncItemQtyLimits();
    updateCatalogFooter();
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
      updateCatalogFooter();
      return;
    }

    list.innerHTML = items.map(function (item) {
      // يُسمح باختيار كل الأصناف؛ الصنف غير المتاح يظهر بوسم «طلب توريد» بلا حظر.
      var isBackorder = maxAddableQty(item) < 1;
      var checked = !!pickerSelectedCodes[item.code];

      return '<li class="adj-picker-option' + (checked ? ' is-selected' : '') + (isBackorder ? ' is-backorder' : '') + '">' +
        '<label class="adj-picker-check-label">' +
        '<input type="checkbox" class="adj-picker-checkbox" value="' + esc(item.code) + '"' +
        (checked ? ' checked' : '') + '>' +
        '<span class="adj-picker-check-body">' +
        '<span class="adj-picker-code">' + esc(item.code) + '</span>' +
        '<span class="adj-picker-name">' + esc(item.name) +
        (isBackorder ? ' <span class="adj-picker-muted">(طلب توريد)</span>' : '') + '</span>' +
        '</span></label></li>';
    }).join('');

    updateCatalogFooter();
  }

  function refreshItemPicker() {
    Object.keys(pickerSelectedCodes).forEach(function (code) {
      var item = findCatalogItem(code);
      if (!item) delete pickerSelectedCodes[code];
    });
    updatePickerLabel();
    renderItemPickerList();
    syncItemQtyLimits();
  }

  function openItemPicker() {
    var picker = $('adjItemPicker');
    var toggle = $('adjItemPickerToggle');
    var search = $('adjItemPickerSearch');
    var overlay = $('adjCatalogModal');
    if (!overlay) return;

    pickerOpen = true;
    if (picker) picker.classList.add('is-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');

    pickerSearchQuery = '';
    if (search) search.value = '';
    renderItemPickerList();
    updateCatalogFooter();

    overlay.hidden = false;
    overlay.classList.add('is-open');
    document.body.classList.add('adj-catalog-open');

    if (search) {
      setTimeout(function () { search.focus(); }, 50);
    }
  }

  function closeItemPicker() {
    var picker = $('adjItemPicker');
    var toggle = $('adjItemPickerToggle');
    var overlay = $('adjCatalogModal');
    pickerOpen = false;
    if (picker) picker.classList.remove('is-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    if (overlay) {
      overlay.classList.remove('is-open');
      overlay.hidden = true;
    }
    document.body.classList.remove('adj-catalog-open');
  }

  function resetItemPicker() {
    pickerSearchQuery = '';
    var search = $('adjItemPickerSearch');
    if (search) search.value = '';
    clearPickerSelection();
    closeItemPicker();
  }

  function syncItemQtyLimits() {
    var qtyEl = $('adjItemQty');
    var addBtn = $('btnAddAdjItem');
    if (!qtyEl) return;

    var codes = getSelectedCodes();
    var hasItems = codes.length > 0;

    // لا سقف على الكمية — يُسمح بتجاوز المتاح (بيع بالسالب/طلب توريد).
    qtyEl.disabled = !hasItems;
    qtyEl.removeAttribute('max');
    qtyEl.min = hasItems ? '1' : '0';

    if (!hasItems) {
      qtyEl.value = '1';
      if (addBtn) addBtn.disabled = true;
      return;
    }

    var current = parseInt(qtyEl.value, 10);
    if (!current || current < 1) current = 1;
    qtyEl.value = String(current);

    if (addBtn) addBtn.disabled = false;
  }

  function specBomItems() {
    return bomItemsOf(activeCase).filter(function (it) {
      return it.read_only || it.source === 'spec';
    });
  }

  function applyModalMode() {
    var isDirect = modalMode === 'direct';
    var hasPending = !!(activeCase && activeCase.has_pending_edit_request);
    var canEdit = isDirect || (activeCase && activeCase.can_request_adjustment_edit);
    var directSection = $('adjDirectModifySection');
    var completeBtn = $('btnCompleteAdj');
    var submitEditBtn = $('btnSubmitAdjEditRequest');
    var pendingBanner = $('adjPendingBanner');

    if (directSection) directSection.style.display = canEdit && !hasPending ? '' : 'none';
    if (completeBtn) completeBtn.hidden = !isDirect;
    if (submitEditBtn) {
      submitEditBtn.hidden = isDirect;
      submitEditBtn.disabled = hasPending || !activeCase || !activeCase.can_request_adjustment_edit;
    }
    if (pendingBanner) {
      pendingBanner.hidden = isDirect || !hasPending;
    }
  }

  function renderWrittenItemsBlock() {
    var block = $('adjWrittenItemsBlock');
    var body = $('adjWrittenItemsText');
    if (!block || !body) return;
    var text = activeCase && String(activeCase.written_items || '').trim();
    if (!text) {
      block.hidden = true;
      body.textContent = '';
      return;
    }
    block.hidden = false;
    body.textContent = text;
  }

  function renderSpecBlock() {
    var tbody = $('adjSpecItems');
    if (!tbody) return;
    var specs = specBomItems();
    if (!specs.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-cell">لا توجد بنود توصيف فني.</td></tr>';
      return;
    }
    tbody.innerHTML = specs.map(function (it) {
      return '<tr>' +
        '<td>' + esc(it.stock_item_code) + '</td>' +
        '<td>' + esc(it.name) + '</td>' +
        '<td>' + esc(it.qty) + '</td>' +
        '<td>' + esc(it.uom || 'قطعة') + '</td></tr>';
    }).join('');
  }

  function renderEditModeBom() {
    renderSpecBlock();

    var tbody = $('adjBomItems');
    if (!tbody) return;

    var rows = [];

    if (!editRequestItems.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty-cell">لا توجد بنود معدّلات — أضف بنداً من الكاتلوج.</td></tr>';
      return;
    }

    editRequestItems.forEach(function (it, idx) {
      rows.push('<tr>' +
        '<td>' + esc(it.stock_item_code) + '</td>' +
        '<td>' + esc(it.name) + '</td>' +
        '<td><input type="number" class="form-control adj-edit-qty" data-idx="' + idx + '" min="1" value="' + esc(it.qty) + '"' +
        (activeCase && activeCase.has_pending_edit_request ? ' disabled' : '') + ' style="width:72px;padding:4px 8px;"></td>' +
        '<td>' + esc(it.uom || findCatalogItem(it.stock_item_code)?.uom || 'قطعة') + '</td>' +
        '<td><span class="badge done">معدّلات</span></td>' +
        '<td class="adj-col-action">' +
        (activeCase && activeCase.has_pending_edit_request ? '' :
          '<button type="button" class="adj-remove-btn btn-remove-adj-edit-item" data-idx="' + idx + '" title="حذف البند" aria-label="حذف البند">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
          '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>') +
        '</td></tr>');
    });

    tbody.innerHTML = rows.join('') || '<tr><td colspan="6" class="empty-cell">لا توجد بنود معدّلات.</td></tr>';
  }

  function syncEditRequestItemsFromInputs() {
    document.querySelectorAll('.adj-edit-qty').forEach(function (input) {
      var idx = parseInt(input.getAttribute('data-idx'), 10);
      if (isNaN(idx) || !editRequestItems[idx]) return;
      var qty = parseInt(input.value, 10);
      editRequestItems[idx].qty = qty > 0 ? qty : 1;
    });
  }

  function addToEditRequestItems(catalogItem, qty) {
    var existing = editRequestItems.filter(function (i) { return i.stock_item_code === catalogItem.code; })[0];
    if (existing) {
      existing.qty = (parseInt(existing.qty, 10) || 0) + qty;
    } else {
      editRequestItems.push({
        stock_item_code: catalogItem.code,
        name: catalogItem.name,
        qty: qty,
      });
    }
    renderEditModeBom();
  }

  function submitAdjustmentEditRequest() {
    if (!activeCase || !window.axios || activeCase.has_pending_edit_request) return;

    syncEditRequestItemsFromInputs();

    var btn = $('btnSubmitAdjEditRequest');
    if (btn) btn.disabled = true;

    axios.post(EDIT_REQUEST_URL(activeCase.id), {
      items: editRequestItems.map(function (i) {
        return { stock_item_code: i.stock_item_code, name: i.name, qty: parseInt(i.qty, 10) || 1 };
      }),
    })
      .then(function (res) {
        clearFormError();
        toast(res.data.message || 'تم إرسال طلب التعديل');
        closeModal();
        refreshList();
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر إرسال طلب التعديل'));
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var items = bomItemsOf(c);
    var search = [c.case_no, c.order_ref, c.patient && c.patient.name, c.stage_label].join(' ');
    var btnLabel = c.can_modify_directly ? 'فتح' : 'طلب تعديل';

    return '<tr class="adj-row" data-case-id="' + c.id + '" data-search="' + esc(search) + '">' +
      '<td><strong>' + esc(c.case_no) + '</strong><div class="text-xs text-muted">' + esc(c.order_ref) + '</div>' +
      (c.stage_label ? '<div class="text-xs" style="margin-top:4px;"><span class="badge">' + esc(c.stage_label) + '</span></div>' : '') +
      '</td>' +
      '<td>' + esc(c.patient && c.patient.name) + '</td>' +
      '<td><span class="patient-type-badge ' + (isMil ? 'military' : 'civilian') + '">' +
        (isMil ? '🪖 عسكري' : '🌐 مدني') + '</span></td>' +
      '<td>' + items.length + '</td>' +
      '<td class="col-actions">' +
        (window.TechNotesModal ? window.TechNotesModal.buttonHtml(c.tech_notes, c.case_no) : '') +
        (window.TechNotesModal && c.written_items ? window.TechNotesModal.writtenItemsButtonHtml(c.written_items, c.case_no) : '') +
        '<button type="button" class="btn-action primary btn-open-adj" data-case-id="' + c.id + '">' + btnLabel + '</button>' +
      '</td></tr>';
  }

  function updateAnalytics(cases) {
    var total = cases.length;

    if ($('adjBadge')) $('adjBadge').textContent = total;

    var analytics = $('analytics-adjustments');
    if (analytics) {
      var values = analytics.querySelectorAll('.ck-stat-value');
      if (values[0]) values[0].textContent = total;
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
    renderSpecBlock();

    var tbody = $('adjBomItems');
    if (!tbody) return;

    var adjItems = (items || []).filter(function (it) {
      return !(it.read_only || it.source === 'spec');
    });

    if (!adjItems.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty-cell">لا توجد بنود معدّلات — أضف بنداً من الكاتلوج.</td></tr>';
      return;
    }

    tbody.innerHTML = adjItems.map(function (it) {
      var removeBtn = '<td class="adj-col-action">' +
        '<button type="button" class="adj-remove-btn btn-remove-adj-item" data-item-id="' + esc(it.id) + '"' +
        ' title="حذف البند" aria-label="حذف البند">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"' +
        ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>' +
        '<path d="M10 11v6"/><path d="M14 11v6"/></svg></button></td>';
      return '<tr data-item-id="' + esc(it.id) + '">' +
        '<td>' + esc(it.stock_item_code) + '</td>' +
        '<td>' + esc(it.name) + '</td>' +
        '<td><input type="number" class="form-control adj-item-qty-input" data-item-id="' + esc(it.id) + '"' +
        ' data-qty="' + esc(it.qty) + '" min="1" value="' + esc(it.qty) + '" style="width:82px;padding:4px 8px;"></td>' +
        '<td>' + esc(it.uom || 'قطعة') + '</td>' +
        '<td><span class="badge done">معدّلات</span></td>' +
        removeBtn +
        '</tr>';
    }).join('');
  }

  function updateAdjItemQty(itemId, newQty, inputEl) {
    if (!activeCase || !window.axios || !itemId) return;

    if (inputEl) inputEl.disabled = true;

    axios.patch(UPDATE_QTY_URL(activeCase.id, itemId), { qty: newQty })
      .then(function (res) {
        clearFormError();
        toast('تم تحديث كمية البند');
        if (activeCase.bom) {
          activeCase.bom.items = (res.data.bom && res.data.bom.items) || [];
        }
        renderBomItems((res.data.bom && res.data.bom.items) || []);
        refreshItemPicker();
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر تحديث الكمية'));
        // استرجاع القيمة السابقة عند الفشل.
        if (inputEl) inputEl.value = inputEl.getAttribute('data-qty');
      })
      .finally(function () {
        if (inputEl) inputEl.disabled = false;
      });
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
        catalogCache = res.data.stock_catalog || [];
        modalMode = activeCase.can_modify_directly ? 'direct' : 'edit_request';
        editRequestItems = [];

        function showModal() {
          var modal = $('adjModal');
          if (!modal) return;

          var title = $('adjModalTitle');
          if (title) {
            title.textContent = '🧩 ' + ((activeCase.patient && activeCase.patient.name) || '—') + ' — ' + activeCase.case_no;
          }

          if (modalMode === 'direct') {
            renderBomItems((activeCase.bom && activeCase.bom.items) || []);
          } else {
            renderEditModeBom();
          }

          renderWrittenItemsBlock();

          renderReworkBanner(activeCase.rework || null);
          resetItemPicker();
          refreshItemPicker();
          applyModalMode();
          clearFormError();
          modal.classList.add('visible');
        }

        if (modalMode === 'edit_request') {
          axios.get(EDIT_REQUEST_URL(caseId))
            .then(function (editRes) {
              editRequestItems = (editRes.data.items || []).map(function (i) {
                return { stock_item_code: i.stock_item_code, name: i.name, qty: parseInt(i.qty, 10) || 1 };
              });
              if (editRes.data.pending_request) {
                activeCase.has_pending_edit_request = true;
                activeCase.can_request_adjustment_edit = false;
              }
              if (editRes.data.stock_catalog && editRes.data.stock_catalog.length) {
                catalogCache = editRes.data.stock_catalog;
              }
              showModal();
            })
            .catch(function (err) {
              showError(apiMessage(err, 'تعذّر تحميل بيانات طلب التعديل'));
            });
        } else {
          showModal();
        }
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر فتح الحالة'));
      });
  }

  function closeModal() {
    var modal = $('adjModal');
    if (modal) modal.classList.remove('visible');
    activeCase = null;
    modalMode = 'direct';
    editRequestItems = [];
    resetItemPicker();
    renderReworkBanner(null);
    applyModalMode();
  }

  function addSelectedItems(closePopup) {
    if (!activeCase || !window.axios) return;

    var codes = getSelectedCodes();
    var qty = parseInt(($('adjItemQty') && $('adjItemQty').value) || '0', 10);

    if (!codes.length) {
      showError('اختر صنفاً واحداً على الأقل من القائمة');
      return;
    }
    if (!qty || qty < 1) {
      showError('أدخل كمية صحيحة');
      return;
    }

    if (modalMode === 'edit_request') {
      for (var j = 0; j < codes.length; j++) {
        var catItem = findCatalogItem(codes[j]);
        if (!catItem) continue;
        addToEditRequestItems(catItem, qty);
      }
      clearPickerSelection();
      refreshItemPicker();
      if (closePopup) closeItemPicker();
      return;
    }

    var items = [];
    var backorder = false;
    for (var i = 0; i < codes.length; i++) {
      var catalogItem = findCatalogItem(codes[i]);
      if (!catalogItem) continue;
      // يُسمح بتجاوز المتاح (backorder) — لا حظر، فقط تنبيه.
      if (qty > maxAddableQty(catalogItem)) backorder = true;
      items.push({
        stock_item_code: catalogItem.code,
        name: catalogItem.name,
        qty: qty,
      });
    }

    if (backorder) {
      toast('⚠️ الكمية تتجاوز المتاح — سيُسجَّل رصيد سالب (طلب توريد).');
    }

    var btn = $('btnAddAdjItem');
    var popupBtn = $('btnAdjCatalogAdd');
    if (btn) btn.disabled = true;
    if (popupBtn) popupBtn.disabled = true;

    axios.post(ADD_URL(activeCase.id), { items: items })
      .then(function (res) {
        clearFormError();
        toast(items.length > 1 ? ('تمت إضافة ' + items.length + ' بنود') : 'تمت إضافة البند');
        if (activeCase.bom) {
          activeCase.bom.items = (res.data.bom && res.data.bom.items) || [];
        }
        renderBomItems((res.data.bom && res.data.bom.items) || []);
        clearPickerSelection();
        refreshItemPicker();
        if (closePopup) closeItemPicker();
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر إضافة البنود'));
      })
      .finally(function () {
        syncItemQtyLimits();
        updateCatalogFooter();
      });
  }

  function addItem() {
    addSelectedItems(false);
  }

  function removeItem(itemId, triggerBtn) {
    if (!activeCase || !window.axios || !itemId) return;
    if (!window.confirm('حذف هذا البند من قائمة المعدلات؟')) return;

    if (triggerBtn) triggerBtn.disabled = true;

    axios.delete(REMOVE_URL(activeCase.id, itemId))
      .then(function (res) {
        clearFormError();
        toast('تم حذف البند');
        if (activeCase.bom) {
          activeCase.bom.items = (res.data.bom && res.data.bom.items) || [];
        }
        renderBomItems((res.data.bom && res.data.bom.items) || []);
        refreshItemPicker();
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر حذف البند'));
      })
      .finally(function () {
        if (triggerBtn) triggerBtn.disabled = false;
      });
  }

  function getPendingAddItem() {
    var codes = getSelectedCodes();
    if (!codes.length) return null;

    var qty = parseInt(($('adjItemQty') && $('adjItemQty').value) || '0', 10);
    if (!qty || qty < 1) qty = 1;

    return {
      count: codes.length,
      qty: qty,
      codes: codes,
    };
  }

  function completeAdjustments() {
    if (!activeCase || !window.axios) return;

    var pending = getPendingAddItem();
    if (pending) {
      window.alert(
        pending.count > 1
          ? ('⚠️ حدّدت ' + pending.count + ' أصناف (كمية ' + pending.qty + ' لكل صنف) ولم تؤكّد إضافتها.\n\n' +
            'اضغط «إضافة» أولاً، أو أزل الاختيار ثم أعد «إرسال للتكاليف».')
          : ('⚠️ اخترت صنفاً (كمية ' + pending.qty + ') ولم تؤكّد إضافته.\n\n' +
            'اضغط «إضافة» أولاً، أو أزل الاختيار ثم أعد «إرسال للتكاليف».')
      );
      var addBtn = $('btnAddAdjItem');
      if (addBtn) {
        addBtn.focus();
        addBtn.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
      return;
    }

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
    var submitEditBtn = $('btnSubmitAdjEditRequest');
    var modal = $('adjModal');
    var qtyInput = $('adjItemQty');
    var picker = $('adjItemPicker');
    var pickerToggle = $('adjItemPickerToggle');
    var pickerSearch = $('adjItemPickerSearch');
    var pickerList = $('adjItemPickerList');
    var catalogOverlay = $('adjCatalogModal');
    var catalogClose = $('adjCatalogClose');
    var catalogCancel = $('btnAdjCatalogCancel');
    var catalogAdd = $('btnAdjCatalogAdd');
    var bomItemsBody = $('adjBomItems');

    if (bomItemsBody) {
      bomItemsBody.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.btn-remove-adj-edit-item');
        if (editBtn) {
          var idx = parseInt(editBtn.getAttribute('data-idx'), 10);
          if (!isNaN(idx)) {
            editRequestItems.splice(idx, 1);
            renderEditModeBom();
            refreshItemPicker();
          }
          return;
        }
        var btn = e.target.closest('.btn-remove-adj-item');
        if (!btn) return;
        removeItem(btn.getAttribute('data-item-id'), btn);
      });

      bomItemsBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('adj-edit-qty')) {
          syncEditRequestItemsFromInputs();
          return;
        }
        if (e.target.classList.contains('adj-item-qty-input')) {
          var input = e.target;
          var itemId = input.getAttribute('data-item-id');
          var prev = parseInt(input.getAttribute('data-qty'), 10) || 1;
          var next = parseInt(input.value, 10);
          if (!next || next < 1) {
            input.value = String(prev);
            return;
          }
          if (next === prev) return;
          updateAdjItemQty(itemId, next, input);
        }
      });
    }

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
      pickerList.addEventListener('change', function (e) {
        var cb = e.target.closest('.adj-picker-checkbox');
        if (!cb || cb.disabled) return;
        setPickerCodeSelected(cb.value, cb.checked);
        var row = cb.closest('.adj-picker-option');
        if (row) row.classList.toggle('is-selected', cb.checked);
      });
    }

    if (catalogClose) {
      catalogClose.addEventListener('click', closeItemPicker);
    }

    if (catalogCancel) {
      catalogCancel.addEventListener('click', closeItemPicker);
    }

    if (catalogAdd) {
      catalogAdd.addEventListener('click', function () {
        addSelectedItems(true);
      });
    }

    if (catalogOverlay) {
      catalogOverlay.addEventListener('click', function (e) {
        if (e.target === catalogOverlay) closeItemPicker();
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && pickerOpen) {
        e.preventDefault();
        closeItemPicker();
      }
    });

    if (qtyInput) {
      qtyInput.addEventListener('input', function () {
        // لا سقف على الكمية (بيع بالسالب مسموح) — فقط منع القيم أقل من 1.
        var val = parseInt(qtyInput.value, 10);
        if (val < 1 && qtyInput.value !== '') qtyInput.value = '1';
      });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (addBtn) addBtn.addEventListener('click', addItem);
    if (completeBtn) completeBtn.addEventListener('click', completeAdjustments);
    if (submitEditBtn) submitEditBtn.addEventListener('click', submitAdjustmentEditRequest);
    if (modal) {
      modal.addEventListener('click', function (ev) { if (ev.target === modal) closeModal(); });
    }

    refreshList();
  });
})();
