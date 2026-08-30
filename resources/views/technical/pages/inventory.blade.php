@php
    $invStats = $inventory_stats ?? [
        ['icon' => '📦', 'label' => 'إجمالي الأصناف', 'value' => '0', 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
        ['icon' => '✅', 'label' => 'متوفر', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🛒', 'label' => 'طلبات توريد', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
        ['icon' => '⚠️', 'label' => 'كمية منخفضة', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
    ];
    $inventoryListColumns = $inventory_list_columns ?? ['code', 'name', 'available', 'status'];
    $inventoryListColumnLabels = $inventory_list_column_labels ?? [];
@endphp
<div class="section-view" id="section-inventory">
      <div id="analytics-inventory-charts">@include('partials.dashboard-analytics-empty', ['stats' => $invStats, 'hide_charts' => true])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📦 توفر المخزون — الكميات المتاحة</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="badge" id="inventoryBadge">{{ ($inventory_items_total ?? count($inventory_items ?? [])) }} صنف</span>
          </div>
        </div>

        <p class="catalog-table-hint" style="margin:12px 16px 0;padding:10px 14px;background:rgba(14,116,144,0.08);border:1px solid rgba(14,116,144,0.2);border-radius:8px;font-size:13px;line-height:1.5;">
          <strong>الرصيد المتاح ≠ رصيد المخزن:</strong>
          <strong>رصيد المخزن</strong> = الكمية الفعلية في المخزن.
          <strong>محجوز</strong> = مربوط بطلبات إنتاج قيد التنفيذ.
          <strong>الرصيد المتاح</strong> = رصيد المخزن − المحجوز (مثال: 10 − 2 = 8).
        </p>

        <div class="inventory-toolbar">
          <input type="text" id="inventorySearch" placeholder="بحث بالكود، الاسم، أو الباركود (امسح و Enter)...">
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
          @unless ($inventory_list_enabled ?? true)
            <p style="text-align:center;color:var(--text-muted);padding:24px;">
              قائمة المخزن غير مفعّلة لدورك — راجع «عرض قوائم الأصناف» في الإعدادات.
            </p>
          @else
          <table data-paginate="10" class="stock-table">
            <thead>
              <tr id="inventoryTableHead">
                @foreach ($inventoryListColumns as $colKey)
                  <th class="{{ in_array($colKey, ['available', 'status', 'qty', 'reserved'], true) ? 'col-qty' : '' }}">
                    {{ $inventoryListColumnLabels[$colKey]['label'] ?? $colKey }}
                  </th>
                @endforeach
              </tr>
            </thead>
            <tbody id="inventoryTable" data-server-inventory="1"></tbody>
            <tfoot>
              <tr>
                <td colspan="{{ max(1, count($inventoryListColumns)) }}" id="inventoryFooter"></td>
              </tr>
            </tfoot>
          </table>
          @endunless
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
window.__INVENTORY_LIST_COLUMNS = @json($inventoryListColumns);
window.__INVENTORY_RECEIVE_URL = @json(route('technical.inventory.receive'));
window.__INVENTORY_LIST_URL = @json(route('technical.inventory.list'));
</script>
@include('partials.inventory-receive-form-script')
