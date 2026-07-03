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
    </div>
<script>
window.__INVENTORY_ITEMS = @json($inventory_items ?? []);
</script>
