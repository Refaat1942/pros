@php
    $deskSectionId = $desk_section_id ?? 'section-supply-request';
    $deskTitle = $desk_title ?? '🛒 طلب التوريد — أصناف تحتاج توريد';
    $defaultFilter = $default_filter ?? 'backorder';
    $invStats = $inventory_stats ?? [
        ['icon' => '📦', 'label' => 'إجمالي الأصناف', 'value' => '0', 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
        ['icon' => '✅', 'label' => 'متوفر', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🛒', 'label' => 'طلبات توريد', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
        ['icon' => '⚠️', 'label' => 'كمية منخفضة', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
    ];
    $inventoryListColumns = $inventory_list_columns ?? ['code', 'name', 'available', 'status'];
    $inventoryListColumnLabels = $inventory_list_column_labels ?? [];
    $listUrl = $inventory_list_url ?? '/technical/inventory/list';
@endphp
<div class="section-view" id="{{ $deskSectionId }}">
    <div id="analytics-inventory-charts">@include('partials.dashboard-analytics-empty', ['stats' => $invStats, 'hide_charts' => true])</div>
    <div class="panel inventory-wrap">
        <div class="panel-header">
            <h3>{{ $deskTitle }}</h3>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="badge" id="inventoryBadge">{{ ($inventory_items_total ?? count($inventory_items ?? [])) }} صنف</span>
            </div>
        </div>

        <p style="padding:0 16px 8px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.6;">
            الأصناف ذات المتاح السالب أو المحجوز أكثر من الرصيد تظهر هنا كـ «طلب توريد». سجّل الاستلام من صفحة «استلام الوارد» عند وصول الفاتورة.
        </p>

        <div class="inventory-toolbar">
            <input type="text" id="inventorySearch" placeholder="بحث بالكود، الاسم، أو الباركود (امسح و Enter)...">
            <div class="filter-pills" id="inventoryFilters">
                <button class="filter-pill{{ $defaultFilter === 'all' ? ' active' : '' }}" data-filter="all">الكل</button>
                <button class="filter-pill{{ $defaultFilter === 'ok' ? ' active' : '' }}" data-filter="ok">✓ متوفر</button>
                <button class="filter-pill{{ $defaultFilter === 'low' ? ' active' : '' }}" data-filter="low">⚠ منخفض</button>
                <button class="filter-pill{{ $defaultFilter === 'backorder' ? ' active' : '' }}" data-filter="backorder">🛒 طلب توريد</button>
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
</div>
<script>
window.__INVENTORY_ITEMS = @json($inventory_items ?? []);
window.__INVENTORY_LIST_COLUMNS = @json($inventoryListColumns);
window.__INVENTORY_DEFAULT_FILTER = @json($defaultFilter);
window.__INVENTORY_LIST_URL = @json($listUrl);
</script>
