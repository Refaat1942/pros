(function () {
  'use strict';

  var active = document.body.dataset.activePage || '';
  if (active !== 'receive-inbound') return;

  var pendingUrl = window.__RECEIVE_PENDING_LINES_URL;
  if (!pendingUrl) return;

  var pendingLines = [];

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function applyLineToReceiveForm(line) {
    if (!line || !line.receivable_stock_item_id) return;

    var stockSelect = document.getElementById('receiveStockItemId');
    var qtyInput = document.getElementById('receiveQty');
    var lineHidden = document.getElementById('receiveSupplyRequestLineId');
    if (stockSelect) stockSelect.value = String(line.receivable_stock_item_id);
    if (qtyInput) qtyInput.value = String(line.qty);
    if (lineHidden) lineHidden.value = String(line.id);

    var form = document.getElementById('inventoryReceiveForm');
    if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function applyQueryParams() {
    var params = new URLSearchParams(window.location.search);
    var lineId = parseInt(params.get('line_id') || '', 10);
    var stockItemId = parseInt(params.get('stock_item_id') || '', 10);
    var qty = parseInt(params.get('qty') || '', 10);

    if (lineId && stockItemId) {
      applyLineToReceiveForm({
        id: lineId,
        receivable_stock_item_id: stockItemId,
        qty: qty || 1,
      });
      return;
    }

    if (lineId && pendingLines.length) {
      var match = pendingLines.find(function (l) { return l.id === lineId; });
      if (match) applyLineToReceiveForm(match);
    }
  }

  function renderPendingLines() {
    var body = document.getElementById('receivePendingLinesBody');
    var badge = document.getElementById('receivePendingLinesBadge');
    if (!body) return;

    var receivable = pendingLines.filter(function (l) { return l.can_receive; });
    if (badge) badge.textContent = String(receivable.length);

    if (!receivable.length) {
      body.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:16px;">لا توجد طلبات جاهزة للاستلام.</td></tr>';
      return;
    }

    body.innerHTML = receivable.map(function (line) {
      return '<tr>' +
        '<td>' + esc(line.request_no) + '</td>' +
        '<td>' + esc(line.display_name) + (line.spec ? '<br><small style="color:var(--text-muted);">' + esc(line.spec) + '</small>' : '') + '</td>' +
        '<td class="col-qty">' + line.qty + '</td>' +
        '<td>' + esc(line.status_label) + '</td>' +
        '<td><button type="button" class="btn-action success" data-use-line="' + line.id + '">استخدام</button></td>' +
        '</tr>';
    }).join('');

    body.querySelectorAll('[data-use-line]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var lineId = parseInt(btn.getAttribute('data-use-line'), 10);
        var line = pendingLines.find(function (l) { return l.id === lineId; });
        if (line) applyLineToReceiveForm(line);
      });
    });
  }

  function loadPendingLines() {
    return fetch(pendingUrl, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (payload) {
        pendingLines = payload.data || [];
        renderPendingLines();
        applyQueryParams();
      })
      .catch(function () {
        applyQueryParams();
      });
  }

  loadPendingLines();
})();
