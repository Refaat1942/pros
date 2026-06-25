@php
    $cases   = $ops_cases ?? collect();
    $summary = $ops_summary ?? ['raw' => 0, 'wip' => 0, 'done' => 0];
    $mfgLabels = [
        'warehouse'  => 'المخزن',
        'issue'      => 'صرف خامات',
        'generation' => 'توليد',
        'assembly'   => 'تجميع',
        'casting'    => 'صب',
        'finishing'  => 'تشطيب',
    ];
    $bomLabels = [
        'raw'      => ['label' => 'خام', 'class' => 'ops-badge ops-badge--raw'],
        'wip'      => ['label' => 'تحت التشغيل', 'class' => 'ops-badge ops-badge--wip'],
        'finished' => ['label' => 'تام', 'class' => 'ops-badge ops-badge--done'],
    ];
@endphp
<div class="panel ops-overview-panel" id="operationsDeskOverview">
    <div class="panel-header">
        <h3>🎯 مكتب التشغيل — أوامر نشطة</h3>
        <span class="badge">{{ $cases->count() }} أمر</span>
    </div>
    <div class="ops-overview-summary">
        <div class="ops-overview-stat ops-overview-stat--wip">
            <span class="ops-overview-stat-val">{{ $summary['wip'] ?? 0 }}</span>
            <span class="ops-overview-stat-lbl">🏭 تحت التشغيل</span>
        </div>
        <div class="ops-overview-stat ops-overview-stat--done">
            <span class="ops-overview-stat-val">{{ $summary['done'] ?? 0 }}</span>
            <span class="ops-overview-stat-lbl">✅ تم التسليم</span>
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
                        $bomStage   = $case->bom?->stage;
                        $bomMeta    = $bomStage ? ($bomLabels[$bomStage] ?? ['label' => $bomStage, 'class' => 'ops-badge']) : null;
                        $itemsCount = $case->bom?->items?->count() ?? 0;
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
                            @if ($bomMeta)
                                <span class="{{ $bomMeta['class'] }}">{{ $bomMeta['label'] }}</span>
                            @else
                                <span class="ops-muted">بدون BOM</span>
                            @endif
                            <div class="ops-case-no">{{ $mfgLabels[$case->manufacturing_stage] ?? ($case->manufacturing_stage ?? '—') }}</div>
                        </td>
                        <td style="text-align:center;font-weight:700">{{ $itemsCount }}</td>
                        <td>
                            @if ($bomStage === 'finished')
                                <span class="ops-badge ops-badge--done">جاهز للتسليم</span>
                            @elseif ($bomStage === 'raw')
                                <span class="ops-badge ops-badge--raw">بانتظار صرف</span>
                            @else
                                <span class="ops-badge ops-badge--wip">قيد الإنتاج</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:28px;color:var(--text-muted);">
                            لا توجد أوامر تشغيل نشطة — تظهر هنا بعد صرف BOM ودخول مرحلة التصنيع.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
