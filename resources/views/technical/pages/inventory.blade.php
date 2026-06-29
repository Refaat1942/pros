@php
    $invStats = $inventory_stats ?? [
        ['icon' => '💚', 'label' => 'صحة المخزون', 'value' => '0/100', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '✅', 'label' => 'متوفر', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🛒', 'label' => 'طلبات توريد', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
        ['icon' => '🔒', 'label' => 'محجوز', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
    ];
@endphp
<div class="section-view" id="section-inventory">
      <div id="analytics-inventory-charts">@include('partials.dashboard-analytics-empty', ['stats' => $invStats, 'hide_charts' => true])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📦 توفر المخزون — الكميات المتاحة</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" class="btn-action primary" id="btnReceiveStock">📥 استلام وارد</button>
            <span class="badge" id="inventoryBadge">0 صنف</span>
          </div>
        </div>

        <div class="inventory-readonly-banner">
          <span>👁️</span>
          <span><strong>عرض فقط</strong> — تعريف الأصناف وأسعارها من <strong>لوحة الإدارة</strong>. المخزون يعرض الكميات والتوفر فقط.</span>
        </div>

        <div class="inventory-toolbar">
          <input type="text" id="inventorySearch" placeholder="بحث بالصنف أو المواصفات...">
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
                <th>#</th>
                <th>الصنف</th>
                <th>المواصفات</th>
                <th class="col-qty">الكمية المتاحة</th>
                <th class="col-reserved">محجوز</th>
                <th class="col-status">الحالة</th>
              </tr>
            </thead>
            <tbody id="inventoryTable" data-server-inventory="1"></tbody>
            <tfoot>
              <tr>
                <td colspan="6" id="inventoryFooter"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
<script>
window.__INVENTORY_ITEMS = @json($inventory_items ?? []);
window.__INVENTORY_SUPPLIERS = @json($inventory_suppliers ?? []);
</script>
