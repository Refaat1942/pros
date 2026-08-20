@php
    $prefix = $catalog_prefix ?? 'catalog';
    $title = $catalog_title ?? '📦 قائمة الأصناف';
    $columns = $catalog_list_columns ?? ['code', 'name', 'available', 'status'];
    $columnLabels = $catalog_list_column_labels ?? [];
    $enabled = $catalog_list_enabled ?? true;
    $total = $catalog_items_total ?? count($catalog_items ?? []);
@endphp
<div class="panel inventory-wrap">
    <div class="panel-header">
        <h3>{{ $title }}</h3>
        <span class="badge" id="{{ $prefix }}Badge">{{ $total }} صنف</span>
    </div>
    <div class="inventory-toolbar">
        <input type="text" id="{{ $prefix }}Search" placeholder="بحث بالكود أو اسم الصنف...">
        <div class="filter-pills" id="{{ $prefix }}Filters">
            <button type="button" class="filter-pill active" data-filter="all">الكل</button>
            <button type="button" class="filter-pill" data-filter="ok">✓ متوفر</button>
            <button type="button" class="filter-pill" data-filter="low">⚠ منخفض</button>
            <button type="button" class="filter-pill" data-filter="backorder">🛒 طلب توريد</button>
        </div>
        <div class="export-btns">
            <button type="button" class="btn-export excel" data-catalog-export="excel">📊 Excel</button>
            <button type="button" class="btn-export pdf" data-catalog-export="pdf">📄 PDF</button>
        </div>
    </div>
    <div class="stock-table-wrap">
        @unless ($enabled)
            <p style="text-align:center;color:var(--text-muted);padding:24px;">
                قائمة الأصناف غير مفعّلة لدورك — راجع الإدارة.
            </p>
        @else
            <table data-paginate="10" class="stock-table">
                <thead>
                    <tr id="{{ $prefix }}TableHead">
                        @foreach ($columns as $colKey)
                            <th class="{{ in_array($colKey, ['available', 'status', 'qty', 'reserved'], true) ? 'col-qty' : '' }}">
                                {{ $columnLabels[$colKey]['label'] ?? $colKey }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="{{ $prefix }}Table" data-server-catalog="1"></tbody>
            </table>
        @endunless
    </div>
</div>
@php
    $catalogBrowseConfig = [
        'prefix' => $prefix,
        'title' => $title,
        'listUrl' => $catalog_list_url ?? null,
        'items' => $catalog_items ?? [],
        'columns' => $columns,
        'enabled' => $enabled,
    ];
@endphp
<script>
window.__CATALOG_BROWSE = @json($catalogBrowseConfig);
</script>
