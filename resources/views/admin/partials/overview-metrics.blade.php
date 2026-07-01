@php
    $reports = $admin_reports ?? [];
    $financial = $reports['financial'] ?? [];
    $inventory = $reports['inventory'] ?? [];
    $topItems = collect($financial['top_items'] ?? []);
    $maxTopQty = max(1, (int) $topItems->max('qty'));
@endphp

<section class="overview-section" aria-labelledby="overviewFinancialHeading">
    <header class="overview-section-head">
        <h2 id="overviewFinancialHeading">💰 المالية والإيرادات</h2>
        <p>{{ $period_label ?? ($financial['month_label'] ?? '') }}</p>
    </header>
    <div class="report-cards report-cards--financial overview-metrics-row">
        <div class="report-card overview-metric-card">
            <h4>الإيرادات الشهرية</h4>
            <div class="overview-metric-value overview-metric-value--success">
                {{ number_format((float) ($financial['monthly_revenue'] ?? 0), 2) }} <small>ج.م</small>
            </div>
        </div>

        <div class="report-card overview-metric-card">
            <h4>الأصناف الأكثر طلباً</h4>
            @forelse ($topItems->take(5) as $item)
                <div class="report-bar">
                    <span class="report-bar-code">{{ $item['code'] }}</span>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:{{ min(100, round(($item['qty'] / $maxTopQty) * 100)) }}%"></div>
                    </div>
                    <strong>{{ $item['qty'] }}</strong>
                </div>
                <div class="report-bar-name">{{ $item['name'] }}</div>
            @empty
                <p class="overview-empty-hint">لا توجد بنود BOM مسجّلة بعد.</p>
            @endforelse
        </div>

        <div class="report-card overview-metric-card">
            <h4>أوامر التشغيل — الشهر</h4>
            <div class="overview-metric-value overview-metric-value--info">
                {{ (int) ($financial['work_orders_count'] ?? 0) }} <small>أمر</small>
            </div>
            @forelse (array_slice($financial['work_orders'] ?? [], 0, 4) as $wo)
                <div class="stagnant-item">
                    <span><strong>{{ $wo['work_order_no'] }}</strong> — {{ $wo['patient'] }}</span>
                </div>
            @empty
                <p class="overview-empty-hint">لا توجد أوامر هذا الشهر.</p>
            @endforelse
        </div>
    </div>
</section>

<section class="overview-section" aria-labelledby="overviewInventoryHeading">
    <header class="overview-section-head">
        <h2 id="overviewInventoryHeading">📦 المخزون والتشغيل</h2>
        <p>صحة المخزون وحركة الصرف — {{ $period_label ?? '' }}</p>
    </header>
    <div class="report-cards report-cards--inventory overview-metrics-row">
        <div class="report-card report-card--health overview-metric-card">
            <h4>صحة المخزون</h4>
            <div class="health-score-wrap">
                @php $health = (int) ($inventory['health_pct'] ?? 0); @endphp
                <div class="overview-metric-value" style="color:{{ $health >= 70 ? '#059669' : '#d97706' }};">
                    {{ $health }}<small>%</small>
                </div>
                <div class="overview-metric-hint">
                    {{ (int) ($inventory['item_count'] ?? 0) }} صنف ·
                    {{ (int) ($inventory['low_stock'] ?? 0) }} منخفض ·
                    WAC: <strong>{{ number_format((float) ($inventory['total_value'] ?? 0), 2) }} ج.م</strong>
                </div>
            </div>
        </div>

        <div class="report-card overview-metric-card">
            <h4>تحت الحد الأدنى</h4>
            @forelse (array_slice($inventory['low_stock_items'] ?? [], 0, 5) as $item)
                <div class="stagnant-item">
                    <span>{{ $item['code'] }}</span>
                    <strong style="color:#dc2626;">{{ $item['qty'] }}</strong>
                </div>
            @empty
                <p class="overview-empty-hint">كل الأصناف فوق الحد.</p>
            @endforelse
        </div>

        <div class="report-card overview-metric-card">
            <h4>أصناف راكدة (180+ يوم)</h4>
            @forelse (array_slice($inventory['stagnant_items'] ?? [], 0, 5) as $item)
                <div class="stagnant-item">
                    <span>{{ $item['code'] }}</span>
                    <span class="overview-muted">{{ $item['qty'] }} · {{ $item['last_moved_at'] ?? '—' }}</span>
                </div>
            @empty
                <p class="overview-empty-hint">لا توجد أصناف راكدة.</p>
            @endforelse
        </div>

        <div class="report-card report-card--issues overview-metric-card">
            <h4>صرف المخزن — الشهر</h4>
            <div class="overview-metric-value overview-metric-value--info">
                {{ number_format((int) ($inventory['issues_this_month'] ?? 0)) }} <small>وحدة</small>
            </div>
        </div>
    </div>
</section>
