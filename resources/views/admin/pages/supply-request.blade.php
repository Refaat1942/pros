@include('partials.inventory-supply-request-desk', [
    'desk_section_id' => 'section-supply-request',
    'desk_title' => '🛒 طلب التوريد — أصناف تحتاج توريد',
    'default_filter' => 'backorder',
    'inventory_list_url' => route('admin.supply.list'),
    'supply_requests_url' => route('admin.supply.requests'),
    'supply_requests_store_url' => route('admin.supply.requests.store'),
    'supply_search_items_url' => route('admin.supply.search-items'),
    'supply_resolve_url_base' => url('/admin/supply/requests'),
    'add_catalog_item_url' => route('admin.add-catalog-item'),
    'receive_inbound_url' => route('admin.receive-inbound'),
])
