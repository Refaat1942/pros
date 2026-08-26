@include('partials.inventory-supply-request-desk', [
    'desk_section_id' => 'section-supply-request',
    'desk_title' => '🛒 طلب التوريد — أصناف تحتاج توريد',
    'default_filter' => 'backorder',
    'inventory_list_url' => route('technical.supply.list'),
    'supply_requests_url' => route('technical.supply.requests'),
    'supply_requests_store_url' => route('technical.supply.requests.store'),
    'supply_search_items_url' => route('technical.supply.search-items'),
    'supply_resolve_url_base' => url('/technical/supply/requests'),
    'add_catalog_item_url' => route('technical.add-catalog-item'),
    'receive_inbound_url' => route('technical.receive-inbound'),
])
