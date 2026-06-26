@php
    $cases   = $ops_cases ?? collect();
    $summary = $ops_summary ?? ['ready' => 0, 'done' => 0];
@endphp
<div class="panel ops-overview-panel" id="operationsDeskOverview">
    <div class="panel-header">
        <h3>✅ مكتب التشغيل — <span class="ops-overview-order-count">{{ $cases->count() }}</span> جاهز للتسليم</h3>
    </div>
    <div class="ops-overview-summary">
        <div class="ops-overview-stat ops-overview-stat--wip">
            <span class="ops-overview-stat-val">{{ $summary['ready'] ?? 0 }}</span>
            <span class="ops-overview-stat-lbl">✅ جاهز للتسليم</span>
        </div>
        <div class="ops-overview-stat ops-overview-stat--done">
            <span class="ops-overview-stat-val">{{ $summary['done'] ?? 0 }}</span>
            <span class="ops-overview-stat-lbl">📁 تم التسليم</span>
        </div>
    </div>
    <div class="panel-body">
        <table data-paginate="10">
            <thead>
                <tr>
                    <th>أمر التشغيل</th>
                    <th>المريض</th>
                    <th>المسار</th>
                    <th>BOM / الشغل</th>
                    <th>البنود</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody id="opsOverviewTable" data-server-rendered="1">
                @forelse ($cases as $case)
                    @php
                        $itemsCount = $case->bom?->items?->isNotEmpty()
                            ? \App\Support\BomItemAggregator::uniqueCodeCount($case->bom->items)
                            : 0;
                        $isMil      = $case->isMilitary();
                    @endphp
                    <tr>
                        <td><span class="ops-wo">{{ $case->work_order_no ?? '—' }}</span></td>
                        <td>
                            <strong>{{ $case->patient?->name ?? '—' }}</strong>
                            <div class="ops-case-no">{{ $case->case_no }}</div>
                        </td>
                        <td>
                            <span class="patient-type-badge {{ $isMil ? 'military' : 'civilian' }}">
                                {{ $isMil ? '🪖 عسكري' : '🌐 مدني' }}
                            </span>
                        </td>
                        <td>
                            <span class="ops-badge ops-badge--done">تام — جاهز للتسليم</span>
                        </td>
                        <td style="text-align:center">
                            @if ($itemsCount > 0)
                                @php
                                    $bomItemsJson = \App\Support\BomItemAggregator::byStockCode($case->bom->items);
                                @endphp
                                <button type="button"
                                        class="btn-action ops-overview-bom-btn"
                                        data-patient="{{ $case->patient?->name ?? '—' }}"
                                        data-case-no="{{ $case->case_no }}"
                                        data-work-order="{{ $case->work_order_no ?? '—' }}"
                                        data-items='@json($bomItemsJson)'>
                                    عرض
                                </button>
                            @else
                                <span class="ops-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="ops-badge ops-badge--done">بانتظار التسليم</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:28px;color:var(--text-muted);">
                            لا توجد حالات جاهزة للتسليم — تظهر بعد إتمام التصنيع في الورشة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="opsOverviewBomModal" role="dialog" aria-modal="true" aria-labelledby="opsOverviewBomTitle">
    <div class="catalog-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="opsOverviewBomTitle">📦 بنود أمر التشغيل</h3>
                <div class="modal-code" id="opsOverviewBomSubtitle">—</div>
            </div>
            <button type="button" class="catalog-modal-close" id="opsOverviewBomClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body" style="padding-top:0;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الصنف</th>
                        <th style="text-align:center;width:72px;">الكمية</th>
                    </tr>
                </thead>
                <tbody id="opsOverviewBomBody"></tbody>
            </table>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="opsOverviewBomCloseBtn">إغلاق</button>
        </div>
    </div>
</div>
