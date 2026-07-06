@php
    $cases   = $workshop_cases ?? collect();
    $summary = $workshop_summary ?? ['wip' => 0, 'military' => 0, 'civilian' => 0];
    $mfgLabels = [
        'warehouse'  => 'المخزن',
        'issue'      => 'صرف خامات',
        'workshop'   => 'الورشة',
        'fitting'    => 'تجربة تركيب',
        'quality'    => 'مراقبة جودة',
        'generation' => 'توليد',
        'assembly'   => 'تجميع',
        'casting'    => 'صب',
        'finishing'  => 'تشطيب',
    ];
@endphp
<div class="panel ops-overview-panel workshop-overview-panel" id="workshopDeskOverview">
    <div class="panel-header">
        <h3>🏭 ورشة التصنيع — <span class="ops-overview-order-count">{{ $cases->count() }}</span> أوامر تحت التشغيل</h3>
    </div>
    <div class="ops-overview-summary">
        <div class="ops-overview-stat ops-overview-stat--wip">
            <span class="ops-overview-stat-val">{{ $summary['wip'] ?? 0 }}</span>
            <span class="ops-overview-stat-lbl">🏭 تحت التشغيل</span>
        </div>
        <div class="ops-overview-stat ops-overview-stat--total">
            <span class="ops-overview-stat-val">{{ $summary['military'] ?? 0 }}</span>
            <span class="ops-overview-stat-lbl">🪖 عسكري</span>
        </div>
        <div class="ops-overview-stat ops-overview-stat--done">
            <span class="ops-overview-stat-val">{{ $summary['civilian'] ?? 0 }}</span>
            <span class="ops-overview-stat-lbl">🌐 مدني</span>
        </div>
    </div>
    <div class="panel-body">
        <table data-paginate="10">
            <thead>
                <tr>
                    <th>أمر التشغيل</th>
                    <th>المريض</th>
                    <th>المسار</th>
                    <th>مرحلة التصنيع</th>
                    <th>عدد الأصناف</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody id="workshopOverviewTable" data-server-rendered="1">
                @forelse ($cases as $case)
                    @php
                        $itemsCount = $case->bom?->items?->isNotEmpty()
                            ? \App\Support\BomItemAggregator::uniqueCodeCount($case->bom->items)
                            : 0;
                        $isMil = $case->isMilitary();
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
                            <span class="ops-badge ops-badge--wip">{{ $mfgLabels[$case->manufacturing_stage] ?? ($case->manufacturing_stage ?? '—') }}</span>
                        </td>
                        <td style="text-align:center">
                            @if ($itemsCount > 0)
                                <button type="button"
                                        class="btn-action workshop-overview-bom-btn"
                                        data-patient="{{ $case->patient?->name ?? '—' }}"
                                        data-case-no="{{ $case->case_no }}"
                                        data-work-order="{{ $case->work_order_no ?? '—' }}"
                                        data-items='@json(\App\Support\BomItemAggregator::byStockCode($case->bom->items))'>
                                    عرض
                                </button>
                            @else
                                <span class="ops-muted">—</span>
                            @endif
                        </td>
                        <td><span class="ops-badge ops-badge--wip">قيد الإنتاج</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:28px;color:var(--text-muted);">
                            لا توجد أوامر في الورشة — تظهر بعد صرف المواد من المخزن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
