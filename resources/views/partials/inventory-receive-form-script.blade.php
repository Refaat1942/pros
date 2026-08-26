<script>
(function () {
  var active = document.body.dataset.activePage || '';
  if (active !== 'inventory' && active !== 'receive-inbound') return;
  var form = document.getElementById('inventoryReceiveForm');
  if (!form) return;
  var csrf = document.querySelector('meta[name="csrf-token"]');
  var receiveUrl = window.__INVENTORY_RECEIVE_URL || '/technical/inventory/receive';
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData();
    fd.append('stock_item_id', document.getElementById('receiveStockItemId').value);
    fd.append('qty', document.getElementById('receiveQty').value);
    fd.append('unit_price', document.getElementById('receiveUnitPrice').value);
    fd.append('supplier_id', document.getElementById('receiveSupplierId').value);
    fd.append('invoice_no', document.getElementById('receiveInvoiceNo').value);
    fd.append('moved_at', document.getElementById('receiveMovedAt').value);
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
