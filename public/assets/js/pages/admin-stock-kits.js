(function () {
  if (document.body.dataset.dashboard !== 'admin' || document.body.dataset.activePage !== 'stock-kits') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
  }

  var kits = [];
  var selectedComponents = [];
  var searchTimer = null;
  var lastSearchResults = [];
  var searchRequestId = 0;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function itemDisplayCode(item) {
    return item.code || item.alt_codes || item.catalog_number || '—';
  }

  function load() {
    axios.get('/admin/stock-kits/list').then(function (res) {
      kits = res.data.data || [];
      renderTable();
    }).catch(function () { alert('تعذّر تحميل الأطقم'); });
  }

  function renderTable() {
    var q = ($('stockKitSearch') && $('stockKitSearch').value || '').trim().toLowerCase();
    var tbody = $('stockKitsTable');
    if (!tbody) return;
    var rows = kits.filter(function (k) {
      if (!q) return true;
      return (k.name + ' ' + k.code).toLowerCase().indexOf(q) !== -1;
    });
    $('stockKitCount').textContent = rows.length + ' طقم';
    tbody.innerHTML = rows.map(function (k) {
      return '<tr data-id="' + k.id + '">' +
        '<td><strong>' + esc(k.name) + '</strong></td>' +
        '<td dir="ltr">' + esc(k.code) + '</td>' +
        '<td>' + esc(k.type_label) + '</td>' +
        '<td>' + (k.items || []).map(function (i) { return esc(i.name) + ' ×' + i.qty; }).join('<br>') + '</td>' +
        '<td>' + (k.is_active ? '✅ نشط' : '⏸️') + '</td>' +
        '<td><button type="button" class="btn-action kit-edit">✏️</button> ' +
        '<button type="button" class="btn-action danger kit-del">🗑️</button></td></tr>';
    }).join('') || '<tr><td colspan="6" class="empty-cell">لا توجد أطقم — أضف طقماً جديداً.</td></tr>';

    tbody.querySelectorAll('.kit-edit').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var kit = kits.find(function (k) { return k.id === id; });
        if (kit) openModal(kit);
      });
    });
    tbody.querySelectorAll('.kit-del').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        if (!confirm('حذف هذا الطقم؟')) return;
        axios.delete('/admin/stock-kits/' + id).then(load);
      });
    });
  }

  function isComponentAdded(stockItemId) {
    return selectedComponents.some(function (c) { return c.stock_item_id === stockItemId; });
  }

  function addComponent(item) {
    if (!item || !item.id) return;
    if (isComponentAdded(item.id)) return;

    selectedComponents.push({
      stock_item_id: item.id,
      code: itemDisplayCode(item),
      name: item.name || '',
      uom: item.uom || 'قطعة',
      page_number: item.page_number || '',
      qty: 1,
    });
    renderComponentsTable();
    renderSearchResults(lastSearchResults, $('stockKitItemSearch') ? $('stockKitItemSearch').value : '');
  }

  function removeComponent(stockItemId) {
    selectedComponents = selectedComponents.filter(function (c) { return c.stock_item_id !== stockItemId; });
    renderComponentsTable();
    renderSearchResults(lastSearchResults, $('stockKitItemSearch') ? $('stockKitItemSearch').value : '');
  }

  function renderComponentsTable() {
    var tbody = $('stockKitComponentsBody');
    var countEl = $('stockKitComponentsCount');
    if (!tbody) return;

    if (countEl) {
      countEl.textContent = selectedComponents.length + ' صنف';
    }

    if (!selectedComponents.length) {
      tbody.innerHTML = '<tr id="stockKitComponentsEmpty"><td colspan="6" class="stock-kit-empty-cell">ابحث أعلاه وأضف الأصناف — يمكنك إضافة أكثر من صنف</td></tr>';
      return;
    }

    tbody.innerHTML = selectedComponents.map(function (c) {
      return '<tr data-item-id="' + c.stock_item_id + '">' +
        '<td dir="ltr" style="font-family:monospace;font-size:13px;">' + esc(c.code) + '</td>' +
        '<td><strong>' + esc(c.name) + '</strong></td>' +
        '<td style="color:#64748b;">' + esc(c.page_number || '—') + '</td>' +
        '<td style="text-align:center;color:#64748b;">' + esc(c.uom) + '</td>' +
        '<td><input type="number" class="stock-kit-qty-input kit-comp-qty" min="1" value="' + (c.qty || 1) + '" data-id="' + c.stock_item_id + '"></td>' +
        '<td><button type="button" class="btn-action danger kit-comp-remove" data-id="' + c.stock_item_id + '" title="إزالة">✕</button></td>' +
        '</tr>';
    }).join('');

    tbody.querySelectorAll('.kit-comp-qty').forEach(function (input) {
      input.addEventListener('change', function () {
        var id = parseInt(input.getAttribute('data-id'), 10);
        var comp = selectedComponents.find(function (c) { return c.stock_item_id === id; });
        if (comp) comp.qty = Math.max(1, parseInt(input.value, 10) || 1);
      });
    });

    tbody.querySelectorAll('.kit-comp-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        removeComponent(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
  }

  function showSearchLoading() {
    var wrap = $('stockKitItemResults');
    if (wrap) {
      wrap.innerHTML = '<div class="stock-kit-results-loading">⏳ جاري البحث في الكتالوج...</div>';
    }
  }

  function showSearchError(message) {
    var wrap = $('stockKitItemResults');
    if (wrap) {
      wrap.innerHTML = '<div class="stock-kit-results-error">' + esc(message) + '</div>';
    }
  }

  function renderSearchResults(items, query) {
    var wrap = $('stockKitItemResults');
    if (!wrap) return;

    lastSearchResults = items || [];
    var q = String(query || '').trim();

    if (!q) {
      if (!items.length) {
        wrap.innerHTML = '<div class="stock-kit-results-empty">اكتب حرفاً أو أكثر — مثال: <strong>رك</strong> لإظهار كل الأصناف التي تحتوي «رك» في الاسم</div>';
        return;
      }
    }

    if (q && !items.length) {
      wrap.innerHTML = '<div class="stock-kit-results-empty">لا توجد أصناف مطابقة لـ «' + esc(q) + '» — جرّب جزءاً من الاسم أو الكود</div>';
      return;
    }

    wrap.innerHTML = items.map(function (item) {
      var added = isComponentAdded(item.id);
      return '<button type="button" class="stock-kit-item-result' + (added ? ' is-added' : '') + '" data-id="' + item.id + '">' +
        '<span class="stock-kit-item-result__meta">' +
          '<span class="stock-kit-item-result__code">' + esc(itemDisplayCode(item)) + '</span>' +
          '<span class="stock-kit-item-result__name">' + esc(item.name) + '</span>' +
          (item.page_number ? '<span class="stock-kit-item-result__page">صفحة ' + esc(item.page_number) + '</span>' : '') +
        '</span>' +
        '<span class="stock-kit-item-result__add">' + (added ? '✓ مضاف' : '+ إضافة') + '</span>' +
        '</button>';
    }).join('');

    wrap.querySelectorAll('.stock-kit-item-result').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var item = items.find(function (i) { return i.id === id; });
        if (item && !isComponentAdded(id)) addComponent(item);
      });
    });
  }

  function searchItems(query, options) {
    options = options || {};
    var q = (query || '').trim();
    var requestId = ++searchRequestId;

    if (!options.silent) {
      showSearchLoading();
    }

    axios.get('/admin/stock-kits/search-items', { params: { q: q, limit: 50 } })
      .then(function (res) {
        if (requestId !== searchRequestId) return;
        renderSearchResults(res.data.data || [], q);
      })
      .catch(function (err) {
        if (requestId !== searchRequestId) return;
        var msg = 'تعذّر البحث في الأصناف';
        if (err.response) {
          if (err.response.status === 403) msg = 'لا تملك صلاحية البحث في الكتالوج';
          else if (err.response.status === 404) msg = 'مسار البحث غير موجود — حدّث النظام (git pull)';
          else if (err.response.data && err.response.data.message) msg = err.response.data.message;
        }
        showSearchError(msg);
        lastSearchResults = [];
      });
  }

  function scheduleSearch(query) {
    clearTimeout(searchTimer);
    var q = (query || '').trim();
    if (!q) {
      searchItems('', { silent: true });
      return;
    }
    searchTimer = setTimeout(function () { searchItems(q); }, 180);
  }

  function openModal(kit) {
    $('stockKitId').value = kit ? kit.id : '';
    $('stockKitModalTitle').textContent = kit ? '✏️ تعديل طقم' : '➕ طقم جديد';
    $('stockKitModalSubtitle').textContent = kit
      ? ('كود الطقم: ' + (kit.code || '—'))
      : 'ابحث في المربع أدناه (مثال: رك) — ليس في اسم الطقم';
    $('stockKitName').value = kit ? kit.name : '';
    $('stockKitType').value = kit ? kit.type : 'assembly';
    $('stockKitDescription').value = kit ? (kit.description || '') : '';
    $('stockKitActive').checked = kit ? !!kit.is_active : true;

    selectedComponents = (kit && kit.items ? kit.items : []).map(function (item) {
      return {
        stock_item_id: item.stock_item_id,
        code: item.code || item.stock_item_code || '—',
        name: item.name || '',
        uom: item.uom || 'قطعة',
        page_number: item.page_number || '',
        qty: parseInt(item.qty, 10) || 1,
      };
    });

    if ($('stockKitItemSearch')) $('stockKitItemSearch').value = '';
    renderComponentsTable();
    renderSearchResults([], '');
    $('stockKitError').style.display = 'none';
    $('stockKitModal').classList.add('open');
    $('stockKitModal').removeAttribute('hidden');

    setTimeout(function () {
      var searchEl = $('stockKitItemSearch');
      if (searchEl) {
        searchEl.focus();
        searchItems('', { silent: true });
      }
    }, 80);
  }

  function closeModal() {
    $('stockKitModal').classList.remove('open');
    $('stockKitModal').setAttribute('hidden', '');
    selectedComponents = [];
    clearTimeout(searchTimer);
    searchRequestId++;
  }

  function collectComponents() {
    return selectedComponents.map(function (c) {
      return { stock_item_id: c.stock_item_id, qty: c.qty || 1 };
    }).filter(function (r) { return r.stock_item_id > 0; });
  }

  function saveKit() {
    var id = $('stockKitId').value;
    var items = collectComponents();
    var err = $('stockKitError');
    if (!$('stockKitName').value.trim()) {
      err.textContent = 'اسم الطقم مطلوب.';
      err.style.display = 'block';
      $('stockKitName').focus();
      return;
    }
    if (!items.length) {
      err.textContent = 'أضف مكوّناً واحداً على الأقل من البحث أعلاه.';
      err.style.display = 'block';
      $('stockKitItemSearch').focus();
      return;
    }
    var payload = {
      name: $('stockKitName').value.trim(),
      type: $('stockKitType').value,
      description: $('stockKitDescription').value.trim() || null,
      is_active: $('stockKitActive').checked,
      items: items,
    };
    var req = id
      ? axios.put('/admin/stock-kits/' + id, payload)
      : axios.post('/admin/stock-kits', payload);
    req.then(function () { closeModal(); load(); })
      .catch(function (e) {
        err.textContent = (e.response && e.response.data && e.response.data.message) || 'تعذّر الحفظ';
        err.style.display = 'block';
      });
  }

  $('btnAddStockKit') && $('btnAddStockKit').addEventListener('click', function () { openModal(null); });
  $('saveStockKitBtn') && $('saveStockKitBtn').addEventListener('click', saveKit);
  $('cancelStockKitModal') && $('cancelStockKitModal').addEventListener('click', closeModal);
  $('closeStockKitModal') && $('closeStockKitModal').addEventListener('click', closeModal);
  $('stockKitModal') && $('stockKitModal').addEventListener('click', function (e) {
    if (e.target === $('stockKitModal')) closeModal();
  });
  $('stockKitSearch') && $('stockKitSearch').addEventListener('input', renderTable);

  $('stockKitItemSearch') && $('stockKitItemSearch').addEventListener('input', function () {
    scheduleSearch(this.value);
  });

  $('stockKitItemSearch') && $('stockKitItemSearch').addEventListener('focus', function () {
    if (!lastSearchResults.length && !this.value.trim()) {
      searchItems('', { silent: true });
    }
  });

  $('stockKitItemSearch') && $('stockKitItemSearch').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      var first = $('stockKitItemResults') && $('stockKitItemResults').querySelector('.stock-kit-item-result:not(.is-added)');
      if (first) first.click();
    }
  });

  load();
})();
