@php
    $meta = $section_meta ?? [];
    $report = $report_data ?? [];
    $headers = $report['headers'] ?? [];
    $rows = $report['rows'] ?? [];
    $rowActions = $report['row_actions'] ?? [];
    $section = $report_section ?? '';
    $showCatalogActions = $section === 'catalog' && $rowActions !== [];
    $showReturnsActions = $section === 'returns' && $rowActions !== [];
    $showRowActions = $showCatalogActions || $showReturnsActions;
    $exportUrl = route('admin.reports.export', [
        'section' => $report_section,
        'from'    => $date_from,
        'to'      => $date_to,
    ]);
    $summary = $report['summary'] ?? [];
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

    @if ($summary !== [])
        <div class="reports-summary-grid">
            @foreach ($summary as $item)
                <div class="reports-summary-card">
                    <span class="reports-summary-label">{{ $item['label'] ?? '' }}</span>
                    <strong class="reports-summary-value">{{ $item['value'] ?? '—' }}</strong>
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
                        @if ($showCatalogActions)
                            <th>إجراء</th>
                        @elseif ($showReturnsActions)
                            <th>عرض الأصناف</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                            @if ($showCatalogActions)
                                <td>
                                    @php $itemId = $rowActions[$index]['stock_item_id'] ?? null; @endphp
                                    @if ($itemId)
                                        <a href="{{ route('admin.catalog', ['item' => $itemId]) }}" class="btn-action">👁️ عرض</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            @elseif ($showReturnsActions)
                                <td>
                                    @php $canViewItems = $rowActions[$index]['can_view_items'] ?? false; @endphp
                                    @if ($canViewItems)
                                        <button type="button"
                                                class="btn-action"
                                                onclick="openReportsReturnItems({{ $index }})">
                                            عرض الأصناف
                                        </button>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(count($headers) + ($showRowActions ? 1 : 0), 1) }}" class="empty-cell">
                                لا توجد بيانات في هذه الفترة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($showReturnsActions)
    @include('admin.partials.reports-return-items-modal', ['return_row_actions' => $rowActions])
@endif
