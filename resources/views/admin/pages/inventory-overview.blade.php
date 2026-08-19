@php
    use App\Support\CatalogListCells;
    use App\Services\CatalogListVisibilityService;
    use App\Services\StockCategorySchemaService;

    /** @var \Illuminate\Support\Collection $inventory_items */
    $items = $inventory_items ?? collect();
    $stats = $inventory_overview_stats ?? [];
    $soon = now()->addDays(60);
    $schema = app(StockCategorySchemaService::class);
    $visibility = app(CatalogListVisibilityService::class);
    $overviewUser = auth()->user();
    $overviewColumns = $visibility->tableOrderForUser($overviewUser, 'inventory_overview');
    $overviewColumnDefs = $visibility->columnDefinitions('inventory_overview');
    $overviewColspan = max(1, count($overviewColumns));
    $overviewEnabled = $overviewUser
        ? $visibility->isListEnabledForUser($overviewUser, 'inventory_overview')
        : true;
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

    @unless ($overviewEnabled)
        <p style="text-align:center;color:var(--text-muted);padding:24px;">
            قائمة متابعة حركة الأصناف غير مفعّلة لدورك — راجع «عرض قوائم الأصناف» في المخزون والتوريد.
        </p>
    @else
    <div class="data-toolbar">
        <input type="text" id="invOverviewSearch" placeholder="🔍 بحث بالكود أو الاسم..."
               onkeyup="filterInventoryOverview(this.value)">
        <span class="toolbar-count">{{ $inventory_items_total ?? $items->count() }} صنف</span>
    </div>

    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--surface-2,#f8fafc);">
                    @foreach ($overviewColumns as $colKey)
                        @php
                            $align = in_array($colKey, ['qty', 'reserved', 'available', 'backorder', 'price', 'wac', 'expiry', 'price_history', 'print'], true)
                                ? 'center' : 'right';
                        @endphp
                        <th style="padding:10px;text-align:{{ $align }};">{{ $overviewColumnDefs[$colKey]['label'] ?? $colKey }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody id="invOverviewTable">
                @forelse ($items as $item)
                    <tr class="inv-overview-row" data-search="{{ strtolower($item->code . ' ' . $item->name . ' ' . ($item->brand ?? '')) }}"
                        style="border-top:1px solid var(--border);">
                        @foreach ($overviewColumns as $colKey)
                            @php $cell = CatalogListCells::inventoryOverviewCell($item, $colKey, $schema); @endphp
                            <td style="padding:8px;{{ $cell['class'] ?? '' }}">{!! $cell['html'] !!}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ $overviewColspan }}" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد أصناف.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endunless
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
