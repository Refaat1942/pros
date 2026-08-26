@include('partials.inventory-receive-desk', [
    'desk_section_id' => 'section-receive-inbound',
    'receive_url' => route('admin.receive.receive'),
    'pending_lines_url' => route('admin.receive.pending-lines'),
])
