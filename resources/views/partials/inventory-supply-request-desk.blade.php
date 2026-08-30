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
    $supplyRequestsUrl = $supply_requests_url ?? '/technical/supply/requests';
    $supplyRequestsStoreUrl = $supply_requests_store_url ?? '/technical/supply/requests';
    $supplySearchItemsUrl = $supply_search_items_url ?? '/technical/supply/search-items';
    $supplyResolveUrlBase = $supply_resolve_url_base ?? '/technical/supply/requests';
    $supplyPrintUrl = $supply_print_url ?? null;
    $addCatalogItemUrl = $add_catalog_item_url ?? null;
    $receiveInboundUrl = $receive_inbound_url ?? null;
@endphp
<div class="section-view" id="{{ $deskSectionId }}">
    <div id="analytics-inventory-charts">@include('partials.dashboard-analytics-empty', ['stats' => $invStats, 'hide_charts' => true])</div>

    <div class="panel inventory-wrap" style="margin-bottom:16px;">
        <div class="panel-header">
            <h3>➕ إنشاء طلب توريد</h3>
        </div>
        <form id="supplyRequestCreateForm" class="panel-body" style="padding:16px;">
            <div style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:16px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                    <input type="radio" name="supplyLineType" value="catalog" checked> صنف موجود بالكتالوج
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                    <input type="radio" name="supplyLineType" value="non_catalog"> صنف غير مكود
                </label>
            </div>

            <div id="supplyCatalogFields" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label for="supplyCatalogSearch">بحث صنف كتالوج</label>
                    <input type="text" id="supplyCatalogSearch" class="form-control" placeholder="اكتب الكود أو الاسم (بدون تحميل الكتالوج الكامل)..." autocomplete="off">
                    <input type="hidden" id="supplyCatalogItemId" name="stock_item_id">
                    <div id="supplyCatalogSearchResults" class="supply-search-results" style="display:none;"></div>
                    <p id="supplyCatalogPickLabel" style="margin:6px 0 0;font-size:12px;color:var(--text-muted);">لم يُختَر صنف بعد.</p>
                </div>
            </div>

            <div id="supplyNonCatalogFields" style="display:none;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label for="supplyDescription">اسم / وصف الصنف</label>
                    <input type="text" id="supplyDescription" class="form-control" maxlength="500" placeholder="مثال: كرسي متحرك موديل X">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:12px;">
                <div class="form-group">
                    <label for="supplyQty">الكمية</label>
                    <input type="number" min="1" id="supplyQty" class="form-control" required value="1">
                </div>
                <div class="form-group">
                    <label for="supplyUom">الوحدة</label>
                    <input type="text" id="supplyUom" class="form-control" maxlength="50" placeholder="قطعة / زوج / …">
                </div>
                <div class="form-group">
                    <label for="supplySpec">المواصفات / الملاحظات</label>
                    <input type="text" id="supplySpec" class="form-control" maxlength="2000" placeholder="تفاصيل إضافية للمورد">
                </div>
            </div>

            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="btn-action success" id="btnSubmitSupplyRequest">💾 تسجيل طلب التوريد</button>
                <span id="supplyRequestFormMessage" style="font-size:13px;"></span>
            </div>
        </form>
    </div>

    <div class="panel inventory-wrap" style="margin-bottom:16px;">
        <div class="panel-header">
            <h3>📋 طلبات توريد مسجّلة</h3>
            <div style="display:flex;align-items:center;gap:10px;">
                <button type="button" class="btn-action primary" id="btnPrintSupplyRequests">🖨️ طباعة الطلبات</button>
                <span class="badge" id="supplyOpenLinesBadge">0</span>
            </div>
        </div>
        <div class="stock-table-wrap" style="padding:0 16px 16px;">
            <table class="stock-table" id="supplyOpenLinesTable">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>النوع</th>
                        <th>الصنف / الوصف</th>
                        <th class="col-qty">الكمية</th>
                        <th>الوحدة</th>
                        <th>الحالة</th>
                        <th>تاريخ الطلب</th>
                        <th>تاريخ الاستلام</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="supplyOpenLinesBody">
                    <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:16px;">لا توجد طلبات مفتوحة.</td></tr>
                </tbody>
            </table>
        </div>
        <div id="supplyResolvePanel" class="panel-body" style="padding:16px;border-top:1px solid var(--border);display:none;">
            <h4 style="margin:0 0 8px;">🔗 ربط / ترميز صنف غير مكود</h4>
            <p id="supplyResolveLineLabel" style="font-size:13px;color:var(--text-muted);margin:0 0 12px;"></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
                <div class="form-group">
                    <label>بحث صنف كتالوج للربط</label>
                    <input type="text" id="supplyResolveSearch" class="form-control" placeholder="كود أو اسم..." autocomplete="off">
                    <input type="hidden" id="supplyResolveStockItemId">
                    <div id="supplyResolveSearchResults" class="supply-search-results" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <button type="button" class="btn-action primary" id="btnConfirmSupplyResolve">تأكيد الربط</button>
                    <button type="button" class="btn-view" id="btnCancelSupplyResolve">إلغاء</button>
                </div>
            </div>
            @if ($addCatalogItemUrl)
            <p style="margin:12px 0 0;font-size:12px;color:var(--text-muted);">
                أو <a href="{{ $addCatalogItemUrl }}" target="_blank" rel="noopener">إضافة صنف جديد</a> ثم ابحث عنه هنا للربط.
            </p>
            @endif
            <div id="supplyResolveMessage" style="margin-top:8px;font-size:13px;"></div>
        </div>
    </div>

    <div class="panel inventory-wrap">
        <div class="panel-header">
            <h3>{{ $deskTitle }}</h3>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="badge" id="inventoryBadge">{{ ($inventory_items_total ?? count($inventory_items ?? [])) }} صنف</span>
            </div>
        </div>

        <p style="padding:0 16px 8px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.6;">
            الأصناف ذات المتاح السالب أو المحجوز أكثر من الرصيد تظهر هنا كـ «طلب توريد» من حجز المخزن. الطلبات اليدوية (مكودة أو غير مكودة) تُسجَّل أعلاه. سجّل الاستلام من صفحة «استلام الوارد» عند وصول الفاتورة.
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
<style>
.supply-search-results {
    margin-top:6px;
    border:1px solid var(--border);
    border-radius:8px;
    max-height:220px;
    overflow:auto;
    background:var(--surface, #fff);
}
.supply-search-results button {
    display:block;
    width:100%;
    text-align:left;
    padding:8px 10px;
    border:none;
    background:transparent;
    cursor:pointer;
    font-size:13px;
}
.supply-search-results button:hover { background:rgba(37,99,235,0.08); }
</style>
<script>
window.__INVENTORY_ITEMS = @json($inventory_items ?? []);
window.__INVENTORY_LIST_COLUMNS = @json($inventoryListColumns);
window.__INVENTORY_DEFAULT_FILTER = @json($defaultFilter);
window.__INVENTORY_LIST_URL = @json($listUrl);
window.__SUPPLY_REQUEST_API = {
    list: @json($supplyRequestsUrl),
    store: @json($supplyRequestsStoreUrl),
    search: @json($supplySearchItemsUrl),
    resolveBase: @json($supplyResolveUrlBase),
    print: @json($supplyPrintUrl),
    receiveInbound: @json($receiveInboundUrl),
};
</script>
