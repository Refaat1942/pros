(function () {
  'use strict';

  var active = document.body.dataset.activePage || '';
  if (active !== 'supply-request') return;

  var api = window.__SUPPLY_REQUEST_API || {};
  if (!api.store) return;

  var openLines = [];
  var resolveLineId = null;
  var searchTimer = null;

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function lineTypeRadios() {
    return document.querySelectorAll('input[name="supplyLineType"]');
  }

  function selectedLineType() {
    var checked = document.querySelector('input[name="supplyLineType"]:checked');
    return checked ? checked.value : 'catalog';
  }

  function toggleLineTypeFields() {
    var type = selectedLineType();
    var catalog = document.getElementById('supplyCatalogFields');
    var nonCatalog = document.getElementById('supplyNonCatalogFields');
    if (catalog) catalog.style.display = type === 'catalog' ? 'grid' : 'none';
    if (nonCatalog) nonCatalog.style.display = type === 'non_catalog' ? 'grid' : 'none';
  }

  function bindLineTypeRadios() {
    lineTypeRadios().forEach(function (radio) {
      radio.addEventListener('change', toggleLineTypeFields);
    });
    toggleLineTypeFields();
  }

  function renderSearchResults(container, items, onPick) {
    if (!container) return;
    if (!items || !items.length) {
      container.style.display = 'none';
      container.innerHTML = '';
      return;
    }
    container.style.display = 'block';
    container.innerHTML = items.map(function (item) {
      return '<button type="button" data-id="' + item.id + '" data-label="' + esc(item.code + ' — ' + item.name) + '">' +
        esc(item.code + ' — ' + item.name) + '</button>';
    }).join('');
    container.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        onPick(parseInt(btn.getAttribute('data-id'), 10), btn.getAttribute('data-label'));
        container.style.display = 'none';
        container.innerHTML = '';
      });
    });
  }

  function scheduleSearch(input, resultsEl, onPick) {
    if (!input) return;
    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (searchTimer) clearTimeout(searchTimer);
      if (q.length < 2) {
        if (resultsEl) {
          resultsEl.style.display = 'none';
          resultsEl.innerHTML = '';
        }
        return;
      }
      searchTimer = setTimeout(function () {
        fetch(api.search + '?q=' + encodeURIComponent(q) + '&limit=25', {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
          .then(function (payload) {
            renderSearchResults(resultsEl, payload.data || [], onPick);
          })
          .catch(function () {
            if (resultsEl) resultsEl.style.display = 'none';
          });
      }, 250);
    });
  }

  function loadOpenLines() {
    return fetch(api.list, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (payload) {
        openLines = payload.data || [];
        renderOpenLines();
      });
  }

  function renderOpenLines() {
    var body = document.getElementById('supplyOpenLinesBody');
    var badge = document.getElementById('supplyOpenLinesBadge');
    if (!body) return;
    if (badge) badge.textContent = String(openLines.length);

    if (!openLines.length) {
      body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:16px;">لا توجد طلبات مفتوحة.</td></tr>';
      return;
    }

    body.innerHTML = openLines.map(function (line) {
      var action = '';
      if (line.needs_link) {
        action = '<button type="button" class="btn-action" data-resolve-line="' + line.id + '">🔗 ربط صنف</button>';
      } else if (line.can_receive) {
        action = '<button type="button" class="btn-action success" data-receive-line="' + line.id + '">📥 استلام في «استلام الوارد»</button>';
      } else {
        action = '—';
      }
      return '<tr>' +
        '<td>' + esc(line.request_no) + '</td>' +
        '<td>' + esc(line.line_type_label) + '</td>' +
        '<td>' + esc(line.display_name) + (line.spec ? '<br><small style="color:var(--text-muted);">' + esc(line.spec) + '</small>' : '') + '</td>' +
        '<td class="col-qty">' + line.qty + '</td>' +
        '<td>' + esc(line.uom || '—') + '</td>' +
        '<td>' + esc(line.status_label) + '</td>' +
        '<td>' + action + '</td>' +
        '</tr>';
    }).join('');

    body.querySelectorAll('[data-resolve-line]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openResolvePanel(parseInt(btn.getAttribute('data-resolve-line'), 10));
      });
    });

    body.querySelectorAll('[data-receive-line]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        handOffToReceiveInbound(parseInt(btn.getAttribute('data-receive-line'), 10));
      });
    });
  }

  function openResolvePanel(lineId) {
    var line = openLines.find(function (l) { return l.id === lineId; });
    if (!line) return;
    resolveLineId = lineId;
    var panel = document.getElementById('supplyResolvePanel');
    var label = document.getElementById('supplyResolveLineLabel');
    if (label) label.textContent = line.display_name + ' — الكمية: ' + line.qty;
    if (panel) panel.style.display = 'block';
    var msg = document.getElementById('supplyResolveMessage');
    if (msg) msg.textContent = '';
    var hidden = document.getElementById('supplyResolveStockItemId');
    if (hidden) hidden.value = '';
    var search = document.getElementById('supplyResolveSearch');
    if (search) search.value = '';
  }

  function closeResolvePanel() {
    resolveLineId = null;
    var panel = document.getElementById('supplyResolvePanel');
    if (panel) panel.style.display = 'none';
  }

  function handOffToReceiveInbound(lineId) {
    var line = openLines.find(function (l) { return l.id === lineId; });
    if (!line || !line.receivable_stock_item_id || !api.receiveInbound) return;

    var params = new URLSearchParams({
      line_id: String(line.id),
      stock_item_id: String(line.receivable_stock_item_id),
      qty: String(line.qty),
    });
    window.location.href = api.receiveInbound + '?' + params.toString();
  }

  function bindCreateForm() {
    var form = document.getElementById('supplyRequestCreateForm');
    if (!form) return;

    var catalogHidden = document.getElementById('supplyCatalogItemId');
    var catalogLabel = document.getElementById('supplyCatalogPickLabel');
    scheduleSearch(
      document.getElementById('supplyCatalogSearch'),
      document.getElementById('supplyCatalogSearchResults'),
      function (id, label) {
        if (catalogHidden) catalogHidden.value = String(id);
        if (catalogLabel) catalogLabel.textContent = 'المختار: ' + label;
      }
    );

    scheduleSearch(
      document.getElementById('supplyResolveSearch'),
      document.getElementById('supplyResolveSearchResults'),
      function (id) {
        var hidden = document.getElementById('supplyResolveStockItemId');
        if (hidden) hidden.value = String(id);
      }
    );

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = document.getElementById('supplyRequestFormMessage');
      var type = selectedLineType();
      var payload = {
        line_type: type,
        qty: parseInt(document.getElementById('supplyQty').value, 10) || 0,
        uom: document.getElementById('supplyUom').value.trim() || null,
        spec: document.getElementById('supplySpec').value.trim() || null,
      };

      if (type === 'catalog') {
        payload.stock_item_id = parseInt(catalogHidden && catalogHidden.value, 10) || 0;
        if (!payload.stock_item_id) {
          if (msg) { msg.style.color = '#dc2626'; msg.textContent = 'اختر صنفاً من نتائج البحث.'; }
          return;
        }
      } else {
        payload.description = document.getElementById('supplyDescription').value.trim();
        if (!payload.description) {
          if (msg) { msg.style.color = '#dc2626'; msg.textContent = 'اسم/وصف الصنف مطلوب.'; }
          return;
        }
      }

      fetch(api.store, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function (res) {
          if (msg) { msg.style.color = '#059669'; msg.textContent = res.message || 'تم التسجيل'; }
          form.reset();
          if (catalogHidden) catalogHidden.value = '';
          if (catalogLabel) catalogLabel.textContent = 'لم يُختَر صنف بعد.';
          toggleLineTypeFields();
          loadOpenLines();
        })
        .catch(function (err) {
          if (msg) {
            msg.style.color = '#dc2626';
            msg.textContent = (err && err.message) ? err.message : 'فشل تسجيل الطلب';
          }
        });
    });
  }

  function bindResolveActions() {
    var confirmBtn = document.getElementById('btnConfirmSupplyResolve');
    var cancelBtn = document.getElementById('btnCancelSupplyResolve');
    if (cancelBtn) cancelBtn.addEventListener('click', closeResolvePanel);
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        if (!resolveLineId) return;
        var stockId = parseInt(document.getElementById('supplyResolveStockItemId').value, 10);
        var msg = document.getElementById('supplyResolveMessage');
        if (!stockId) {
          if (msg) { msg.style.color = '#dc2626'; msg.textContent = 'اختر صنفاً للربط من نتائج البحث.'; }
          return;
        }
        fetch(api.resolveBase + '/' + resolveLineId + '/resolve', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({ stock_item_id: stockId }),
        }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
          .then(function (res) {
            if (msg) { msg.style.color = '#059669'; msg.textContent = res.message || 'تم الربط'; }
            closeResolvePanel();
            loadOpenLines();
          })
          .catch(function (err) {
            if (msg) {
              msg.style.color = '#dc2626';
              msg.textContent = (err && err.message) ? err.message : 'فشل الربط';
            }
          });
      });
    }
  }

  bindLineTypeRadios();
  bindCreateForm();
  bindResolveActions();
  loadOpenLines();
})();
