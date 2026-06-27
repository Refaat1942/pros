@php
    $sections = collect($report_sections ?? [])->groupBy('group');
@endphp
<div class="section-view" id="section-reports">
    <div class="reports-hub-intro">
        <h3>📋 التقارير</h3>
        <p>اختر قسمًا لعرض بياناته ضمن فترة زمنية وتصديرها Excel.</p>
    </div>

    @foreach ($sections as $group => $cards)
        <div class="reports-hub-group">
            <h4 class="reports-hub-group-title">{{ $group }}</h4>
            <div class="reports-hub-grid">
                @foreach ($cards as $card)
                    <a href="{{ route('admin.reports.section', ['section' => $card['id']]) }}"
                       class="reports-hub-card">
                        <span class="reports-hub-card-icon">{{ $card['icon'] }}</span>
                        <strong class="reports-hub-card-label">{{ $card['label'] }}</strong>
                        <span class="reports-hub-card-desc">{{ $card['description'] }}</span>
                        <span class="reports-hub-card-action">عرض التقرير ←</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
