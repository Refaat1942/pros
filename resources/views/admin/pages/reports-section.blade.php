@php
    $meta = $section_meta ?? [];
    $report = $report_data ?? [];
    $summary = $report['summary'] ?? [];
    $headers = $report['headers'] ?? [];
    $rows = $report['rows'] ?? [];
    $exportUrl = route('admin.reports.export', [
        'section' => $report_section,
        'from'    => $date_from,
        'to'      => $date_to,
    ]);
@endphp
<div class="section-view" id="section-reports-section" data-server-rendered="1">
    <div class="reports-section-toolbar">
        <a href="{{ route('admin.reports') }}" class="btn-action">← العودة للتقارير</a>
        <div class="reports-section-heading">
            <h3>{{ ($meta['icon'] ?? '📄') . ' ' . ($report['title'] ?? $meta['label'] ?? 'تقرير') }}</h3>
            <p class="reports-section-period">{{ $report['period_label'] ?? '' }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reports.section', $report_section) }}" class="reports-date-filter">
        <label>
            <span>من</span>
            <input type="date" name="from" value="{{ $date_from }}" required>
        </label>
        <label>
            <span>إلى</span>
            <input type="date" name="to" value="{{ $date_to }}" required>
        </label>
        <button type="submit" class="btn-action primary">تطبيق الفترة</button>
        <a href="{{ $exportUrl }}" class="btn-export excel" download>📊 تصدير Excel</a>
    </form>

    @if ($summary)
        <div class="reports-summary-grid">
            @foreach ($summary as $item)
                <div class="reports-summary-card">
                    <span class="reports-summary-label">{{ $item['label'] }}</span>
                    <strong class="reports-summary-value">{{ $item['value'] }}</strong>
                </div>
            @endforeach
        </div>
    @endif

    <div class="panel">
        <div class="panel-body">
            <table class="data-table" data-paginate="15" data-export-table>
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(count($headers), 1) }}" class="empty-cell">
                                لا توجد بيانات في هذه الفترة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
