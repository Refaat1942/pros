@php
    $analytics = $reception_analytics ?? [];
    $meta = $analytics['meta'] ?? [];
@endphp

<div class="reception-stats-page" id="receptionStatsPage" data-server-rendered="1">
    <div class="reception-stats-hero">
        <div class="reception-stats-hero__text">
            <h2>📊 لوحة إحصائيات الاستقبال</h2>
            <p>
                <span class="reception-stats-meta">آخر تحديث: {{ $meta['generated_at'] ?? '—' }}</span>
            </p>
        </div>
        <div class="reception-stats-hero__chips">
            <span class="reception-stats-chip">📅 اليوم: {{ $meta['today'] ?? '—' }}</span>
            <span class="reception-stats-chip reception-stats-chip--accent">🗓️ {{ $meta['month_label'] ?? '—' }}</span>
        </div>
    </div>

    <div id="receptionStatsRoot"></div>

    <script type="application/json" id="receptionStatsData">@json($analytics)</script>
</div>
