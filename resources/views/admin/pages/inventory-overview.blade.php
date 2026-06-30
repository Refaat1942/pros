@php
    /** @var \Illuminate\Support\Collection $inventory_items */
    $items = $inventory_items ?? collect();
    $stats = $inventory_overview_stats ?? [];
    $soon = now()->addDays(60);
@endphp
<div class="panel">
    <div class="panel-header">
        <h3>🔬 متابعة حركة الأصناف</h3>
        <span style="font-size:13px;color:var(--text-muted)">أرصدة مطلقة، السعر، WAC، تاريخ الأسعار، والصلاحية — المتاح السالب = طلب توريد.</span>
    </div>

    @if (!empty($stats))
        <div style="display:flex;gap:12px;flex-wrap:wrap;padding:14px 16px;">
            @foreach ($stats as $stat)
                <div style="flex:1;min-width:160px;background:{{ $stat['bg'] ?? 'rgba(0,0,0,0.04)' }};border-radius:12px;padding:14px;">
                    <div style="font-size:22px;">{{ $stat['icon'] }}</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">{{ $stat['label'] }}</div>
                    <div style="font-size:20px;font-weight:800;color:{{ $stat['color'] ?? 'var(--text)' }};">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="data-toolbar">
        <input type="text" id="invOverviewSearch" placeholder="🔍 بحث بالكود أو الاسم..."
               onkeyup="filterInventoryOverview(this.value)">
        <span class="toolbar-count">{{ $items->count() }} صنف</span>
    </div>

    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--surface-2,#f8fafc);">
                    <th style="padding:10px;text-align:right;">الكود / الباركود</th>
                    <th style="padding:10px;text-align:right;">الصنف</th>
                    <th style="padding:10px;text-align:right;">القسم</th>
                    <th style="padding:10px;text-align:center;">الرصيد</th>
                    <th style="padding:10px;text-align:center;">محجوز</th>
                    <th style="padding:10px;text-align:center;">متاح</th>
                    <th style="padding:10px;text-align:center;">طلب توريد</th>
                    <th style="padding:10px;text-align:center;">السعر</th>
                    <th style="padding:10px;text-align:center;">WAC</th>
                    <th style="padding:10px;text-align:center;">الصلاحية</th>
                    <th style="padding:10px;text-align:center;">آخر الأسعار</th>
                    <th style="padding:10px;text-align:center;">طباعة</th>
                </tr>
            </thead>
            <tbody id="invOverviewTable">
                @forelse ($items as $item)
                    @php
                        $available = $item->availableQty();
                        $backorder = $item->backorderQty();
                        $expirySoon = $item->expiry_date && $item->expiry_date->lte($soon);
                        $displayPrice = (float) $item->price > 0
                            ? (float) $item->price
                            : max((float) $item->wac, (float) ($item->prices->max('amount') ?? 0));
                        $history = $item->prices->take(3)->map(fn ($p) => number_format((float) $p->amount, 2)
                            . ' (' . ($p->received_at?->format('Y-m-d') ?? '—') . ')')->implode(' • ');
                        $availColor = $available < 0 ? '#dc2626' : ($available > 0 ? '#059669' : '#d97706');
                        $schema = app(\App\Services\StockCategorySchemaService::class);
                        $attrSummary = collect($schema->formatItemAttributes($item))
                            ->map(fn ($a) => $a['label'] . ': ' . $a['display_value'])
                            ->implode(' · ');
                    @endphp
                    <tr class="inv-overview-row" data-search="{{ strtolower($item->code . ' ' . $item->name) }}"
                        style="border-top:1px solid var(--border);">
                        <td style="padding:8px;direction:ltr;text-align:right;">
                            <strong>{{ $item->code }}</strong>
                            <div style="font-size:11px;color:var(--text-muted);">{{ $item->barcode }}</div>
                        </td>
                        <td style="padding:8px;">{{ $item->name }}</td>
                        <td style="padding:8px;font-size:12px;color:var(--text-muted);">
                            <div>{{ $item->category?->name ?? '—' }}</div>
                            @if ($attrSummary)
                                <div style="font-size:11px;margin-top:4px;">{{ $attrSummary }}</div>
                            @endif
                        </td>
                        <td style="padding:8px;text-align:center;font-weight:700;">{{ (int) $item->qty }}</td>
                        <td style="padding:8px;text-align:center;color:#d97706;">{{ (int) $item->reserved }}</td>
                        <td style="padding:8px;text-align:center;font-weight:700;color:{{ $availColor }};">{{ $available }}</td>
                        <td style="padding:8px;text-align:center;font-weight:700;color:{{ $backorder > 0 ? '#dc2626' : 'var(--text-muted)' }};">
                            {{ $backorder > 0 ? $backorder : '—' }}
                        </td>
                        <td style="padding:8px;text-align:center;font-weight:700;">{{ number_format($displayPrice, 2) }}</td>
                        <td style="padding:8px;text-align:center;color:var(--text-muted);">{{ number_format((float) $item->wac, 2) }}</td>
                        <td style="padding:8px;text-align:center;{{ $expirySoon ? 'color:#dc2626;font-weight:700;' : '' }}">
                            {{ $item->expiry_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td style="padding:8px;text-align:center;font-size:11px;color:var(--text-muted);">{{ $history ?: '—' }}</td>
                        <td style="padding:8px;text-align:center;">
                            <a href="{{ route('admin.catalog.labels', $item) }}" target="_blank"
                               class="btn-action" style="font-size:12px;">🏷️ باركود</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد أصناف.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function filterInventoryOverview(term) {
    term = (term || '').toLowerCase().trim();
    document.querySelectorAll('#invOverviewTable .inv-overview-row').forEach(function (row) {
        var hay = row.getAttribute('data-search') || '';
        row.style.display = (!term || hay.indexOf(term) !== -1) ? '' : 'none';
    });
}
</script>
