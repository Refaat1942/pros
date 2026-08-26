@include('partials.inventory-supply-request-desk', [
    'desk_section_id' => 'section-supply-request',
    'desk_title' => '🛒 طلب التوريد — أصناف تحتاج توريد',
    'default_filter' => 'backorder',
    'receive_url' => route('admin.supply.receive'),
    'inventory_list_url' => route('admin.supply.list'),
])
