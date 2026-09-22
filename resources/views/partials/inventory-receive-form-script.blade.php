<script>
(function () {
  var active = document.body.dataset.activePage || '';
  if (active !== 'inventory' && active !== 'receive-inbound') return;
  var form = document.getElementById('inventoryReceiveForm');
  if (!form) return;
  var csrf = document.querySelector('meta[name="csrf-token"]');
  var receiveUrl = window.__INVENTORY_RECEIVE_URL || '/technical/inventory/receive';
  var meta = window.__RECEIVE_STOCK_META || {};

  function currentMeta() {
    var id = document.getElementById('receiveStockItemId')?.value;
    return id && meta[id] ? meta[id] : null;
  }

  function refreshReceiveLabels() {
    var m = currentMeta();
    var qtyLabel = document.querySelector('label[for="receiveQty"]') || document.getElementById('receiveQty')?.closest('.form-group')?.querySelector('label');
    var priceLabel = document.getElementById('receiveUnitPrice')?.closest('.form-group')?.querySelector('label');
    var hint = document.getElementById('receiveUomHint');
    if (!m || !m.receive_in_supply_uom) {
      if (qtyLabel) qtyLabel.textContent = 'الكمية (' + (m && m.uom ? m.uom : 'وحدة المخزن') + ')';
      if (priceLabel) priceLabel.textContent = 'سعر الوحدة (' + (m && m.uom ? m.uom : 'وحدة المخزن') + ')';
      if (hint) { hint.style.display = 'none'; hint.textContent = ''; }
      return;
    }
    var supply = m.supply_uom || 'توريد';
    if (qtyLabel) qtyLabel.textContent = 'الكمية (' + supply + ')';
    if (priceLabel) priceLabel.textContent = 'سعر الوحدة (' + supply + ')';
    if (hint) {
      hint.style.display = 'block';
      hint.textContent = m.hint || '';
    }
  }

  var itemSel = document.getElementById('receiveStockItemId');
  if (itemSel) itemSel.addEventListener('change', refreshReceiveLabels);
  refreshReceiveLabels();

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData();
    var m = currentMeta();
    fd.append('stock_item_id', document.getElementById('receiveStockItemId').value);
    fd.append('qty', document.getElementById('receiveQty').value);
    fd.append('unit_price', document.getElementById('receiveUnitPrice').value);
    if (m && m.receive_in_supply_uom) {
      fd.append('quantity_basis', 'supply');
    } else {
      fd.append('quantity_basis', 'base');
    }
    fd.append('supplier_id', document.getElementById('receiveSupplierId').value);
    fd.append('invoice_no', document.getElementById('receiveInvoiceNo').value);
    fd.append('moved_at', document.getElementById('receiveMovedAt').value);
    var lineIdEl = document.getElementById('receiveSupplyRequestLineId');
    if (lineIdEl && lineIdEl.value) fd.append('supply_request_line_id', lineIdEl.value);
    var doc = document.getElementById('receiveDocument');
    if (doc && doc.files && doc.files[0]) fd.append('document', doc.files[0]);
    fetch(receiveUrl, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: fd,
    }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function (res) {
        var el = document.getElementById('receiveFormMessage');
        el.style.display = 'block';
        el.style.color = '#059669';
        el.textContent = res.message || 'تم الاستلام';
        form.reset();
        document.getElementById('receiveMovedAt').value = new Date().toISOString().slice(0, 10);
        var lineHidden = document.getElementById('receiveSupplyRequestLineId');
        if (lineHidden) lineHidden.value = '';
        refreshReceiveLabels();
        window.location.reload();
      })
      .catch(function (err) {
        var el = document.getElementById('receiveFormMessage');
        el.style.display = 'block';
        el.style.color = '#dc2626';
        el.textContent = (err && err.message) ? err.message : 'فشل الاستلام';
      });
  });
})();
</script>
