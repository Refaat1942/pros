@php
    $catalog_prefix = 'adjCatalog';
    $catalog_title = '📦 قائمة الأصناف — بدون أسعار';
    $catalog_items = $catalog_items ?? [];
    $catalog_items_total = $catalog_items_total ?? count($catalog_items);
    $catalog_list_columns = $catalog_list_columns ?? ['code', 'name', 'brand', 'uom', 'available'];
    $catalog_list_column_labels = $catalog_list_column_labels ?? [];
    $catalog_list_enabled = $catalog_list_enabled ?? true;
    $catalog_list_url = route('adjustments.catalog.list');
@endphp
@include('partials.operational-catalog-browse')
