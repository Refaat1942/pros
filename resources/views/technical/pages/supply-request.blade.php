@include('partials.inventory-supply-request-desk', [
    'desk_section_id' => 'section-supply-request',
    'desk_title' => '🛒 طلب التوريد — أصناف تحتاج توريد',
    'default_filter' => 'backorder',
    'inventory_list_url' => route('technical.supply.list'),
])
