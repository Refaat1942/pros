@foreach (($items ?? []) as $item)
    @if (($item['change'] ?? '') === 'removed')
        <tr class="spec-edit-item--removed">
            <td style="font-family:monospace;font-size:12px;">{{ $item['stock_item_code'] ?? '—' }}</td>
            <td>🗑️ تم حذف البند: <strong>{{ $item['name'] ?? ($item['stock_item_code'] ?? '—') }}</strong></td>
            <td>{{ $item['qty'] ?? 0 }}</td>
        </tr>
    @else
        <tr>
            <td style="font-family:monospace;font-size:12px;">{{ $item['stock_item_code'] ?? '—' }}</td>
            <td>{{ $item['name'] ?? ($item['stock_item_code'] ?? '—') }}</td>
            <td>
                <strong>{{ $item['qty'] ?? 0 }}</strong>
                @if (($item['change'] ?? '') === 'updated' && isset($item['previous_qty']))
                    <span class="spec-edit-qty-was">(كان {{ $item['previous_qty'] }})</span>
                @endif
            </td>
        </tr>
    @endif
@endforeach
