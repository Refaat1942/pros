@include('partials.inventory-receive-desk', [
    'desk_section_id' => 'section-receive-inbound',
    'receive_url' => route('technical.receive.receive'),
    'pending_lines_url' => route('technical.receive.pending-lines'),
])
