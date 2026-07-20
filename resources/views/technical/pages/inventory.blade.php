@php
    $invStats = $inventory_stats ?? [
        ['icon' => '📦', 'label' => 'إجمالي الأصناف', 'value' => '0', 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
        ['icon' => '✅', 'label' => 'متوفر', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🛒', 'label' => 'طلبات توريد', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
        ['icon' => '⚠️', 'label' => 'كمية منخفضة', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
    ];
@endphp
<div class="section-view" id="section-inventory">
      <div id="analytics-inventory-charts">@include('partials.dashboard-analytics-empty', ['stats' => $invStats, 'hide_charts' => true])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📦 توفر المخزون — الكميات المتاحة</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="badge" id="inventoryBadge">0 صنف</span>
          </div>
        </div>

        <div class="inventory-toolbar">
          <input type="text" id="inventorySearch" placeholder="بحث بالكود أو اسم الصنف...">
          <div class="filter-pills" id="inventoryFilters">
            <button class="filter-pill active" data-filter="all">الكل</button>
            <button class="filter-pill" data-filter="ok">✓ متوفر</button>
            <button class="filter-pill" data-filter="low">⚠ منخفض</button>
            <button class="filter-pill" data-filter="backorder">🛒 طلب توريد</button>
          </div>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportInventory('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportInventory('pdf')">📄 PDF</button>
          </div>
        </div>

        <div class="stock-table-wrap">
          <table data-paginate="10" class="stock-table">
            <thead>
              <tr>
                <th>كود الصنف</th>
                <th>اسم الصنف</th>
                <th class="col-qty">الرصيد المتاح</th>
                <th class="col-status">الحالة</th>
              </tr>
            </thead>
            <tbody id="inventoryTable" data-server-inventory="1"></tbody>
            <tfoot>
              <tr>
                <td colspan="4" id="inventoryFooter"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="panel inventory-wrap" style="margin-top:16px;">
        <div class="panel-header">
          <h3>📥 استلام وارد — تسجيل فاتورة</h3>
        </div>
        <form id="inventoryReceiveForm" class="panel-body" style="padding:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
          <div class="form-group">
            <label>الصنف</label>
            <select id="receiveStockItemId" class="form-control" required>
              <option value="">— اختر —</option>
              @foreach ($inventory_items ?? [] as $item)
                <option value="{{ $item['id'] ?? $item->id }}">{{ ($item['code'] ?? $item->code) }} — {{ ($item['name'] ?? $item->name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>الكمية</label>
            <input type="number" min="1" id="receiveQty" class="form-control" required>
          </div>
          <div class="form-group">
            <label>سعر الوحدة</label>
            <input type="number" min="0.01" step="0.01" id="receiveUnitPrice" class="form-control" required>
          </div>
          <div class="form-group">
            <label>المورد</label>
            <select id="receiveSupplierId" class="form-control" required>
              <option value="">— اختر —</option>
              @foreach ($inventory_suppliers ?? [] as $sup)
                <option value="{{ $sup->id }}">{{ $sup->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>رقم الفاتورة</label>
            <input type="text" id="receiveInvoiceNo" class="form-control" required maxlength="100">
          </div>
          <div class="form-group">
            <label>تاريخ الاستلام</label>
            <input type="date" id="receiveMovedAt" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
          @if ($inbound_document_upload ?? true)
          <div class="form-group">
            <label>مرفق الفاتورة (PDF/صورة)</label>
            <input type="file" id="receiveDocument" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
          </div>
          @endif
          <div class="form-group" style="align-self:end;">
            <button type="submit" class="btn-action success" id="btnSubmitReceive">💾 تسجيل الاستلام</button>
          </div>
        </form>
        <div id="receiveFormMessage" style="padding:0 16px 16px;display:none;"></div>
      </div>
    </div>
<script>
window.__INVENTORY_ITEMS = @json($inventory_items ?? []);
window.__INBOUND_RECEIVE_ENABLED = @json($inbound_document_upload ?? true);
</script>
<script>
(function () {
  if (document.body.dataset.dashboard !== 'technical') return;
  if (document.body.dataset.activePage !== 'inventory') return;
  var form = document.getElementById('inventoryReceiveForm');
  if (!form) return;
  var csrf = document.querySelector('meta[name="csrf-token"]');
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
    fetch('/technical/inventory/receive', {
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
