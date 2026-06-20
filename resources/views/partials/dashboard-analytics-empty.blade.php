@php
    $stats = $stats ?? [
        ['icon' => '📊', 'label' => '—', 'value' => '0'],
        ['icon' => '📊', 'label' => '—', 'value' => '0'],
        ['icon' => '📊', 'label' => '—', 'value' => '0'],
        ['icon' => '📊', 'label' => '—', 'value' => '0'],
    ];
@endphp
<div class="ck-analytics" data-static-ui="1">
    <div class="ck-stats">
        @foreach ($stats as $stat)
            <div class="ck-stat">
                <div class="ck-stat-icon" style="background:{{ $stat['bg'] ?? 'rgba(100,116,139,0.1)' }}">{{ $stat['icon'] }}</div>
                <div>
                    <div class="ck-stat-label">{{ $stat['label'] }}</div>
                    <div class="ck-stat-value" @if(!empty($stat['color'])) style="color:{{ $stat['color'] }}" @endif>{{ $stat['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
    @if (empty($hide_charts))
    <div class="ck-charts">
        <div class="ck-chart-card">
            <h4>📈 التحليلات</h4>
            <div class="ck-empty-chart">لا توجد بيانات بعد — سيتم ربطها من الخادم لاحقاً</div>
        </div>
    </div>
    @endif
</div>
