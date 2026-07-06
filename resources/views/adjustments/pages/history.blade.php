@php
    $rows = collect($history_rows ?? []);
    $stats = $history_stats ?? ['total' => 0, 'military' => 0, 'civilian' => 0];
    $dateFrom = $date_from ?? now()->startOfMonth()->toDateString();
    $dateTo = $date_to ?? now()->toDateString();
    $searchQuery = $search_query ?? '';
    $exportUrl = route('adjustments.history.export', array_filter([
        'from'   => $dateFrom,
        'to'     => $dateTo,
        'search' => $searchQuery ?: null,
    ]));
@endphp
<div class="section-view" id="section-history" data-server-rendered="1">
    <div id="analytics-history">@include('partials.dashboard-analytics-empty', [
        'hide_charts' => true,
        'stats' => [
            ['icon' => '📤', 'label' => 'إجمالي المحوّلين', 'value' => (string) ($stats['total'] ?? 0), 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
            ['icon' => '🪖', 'label' => 'عسكري', 'value' => (string) ($stats['military'] ?? 0), 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
            ['icon' => '🌐', 'label' => 'مدني', 'value' => (string) ($stats['civilian'] ?? 0), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ],
    ])</div>

    <div class="panel inventory-wrap">
        <div class="panel-header">
            <h3>📤 سجل المحوّلين من المعدلات للتكاليف</h3>
            <span class="badge" id="adjHistoryCount">{{ $rows->count() }} حالة</span>
        </div>

        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
            الحالات التي أُغلقت من المعدلات وأُرسلت لمحرك التكاليف — مع تاريخ التحويل والموظف المنفّذ.
        </p>

        <form method="GET" action="{{ route('adjustments.history') }}" class="reports-date-filter" id="adjHistoryFilter" style="margin:0 24px 12px;">
            <label>
                <span>من</span>
                <input type="date" name="from" id="adjHistoryDateFrom" value="{{ $dateFrom }}">
            </label>
            <label>
                <span>إلى</span>
                <input type="date" name="to" id="adjHistoryDateTo" value="{{ $dateTo }}">
            </label>
            <label style="flex:1;min-width:200px;">
                <span>بحث بالاسم</span>
                <input type="search" name="search" id="adjHistorySearchServer" value="{{ $searchQuery }}" placeholder="اسم المريض أو رقم الحالة...">
            </label>
            <button type="submit" class="btn-action primary">تطبيق الفلتر</button>
            @if ($dateFrom || $dateTo || $searchQuery)
                <a href="{{ route('adjustments.history') }}" class="btn-action">مسح الفلتر</a>
            @endif
            <a href="{{ $exportUrl }}" class="btn-export excel" download>📊 تصدير Excel</a>
        </form>

        <div class="data-toolbar" style="padding:0 24px 12px;">
            <input type="search" id="adjHistorySearch" placeholder="🔍 بحث سريع في الجدول..." value="">
            <span class="toolbar-count" id="adjHistoryVisibleCount">{{ $rows->count() }} حالة</span>
        </div>

        <div class="bom-table-wrap">
            <table data-paginate="15" class="bom-table" id="adjHistoryTable">
                <thead>
                    <tr>
                        <th>تاريخ التحويل</th>
                        <th>الحالة / الطلب</th>
                        <th>المريض</th>
                        <th>الجهة</th>
                        <th>النوع</th>
                        <th>عدد الأصناف</th>
                        <th>طلب التسعير</th>
                        <th>حوّل بواسطة</th>
                        <th>المرحلة الحالية</th>
                    </tr>
                </thead>
                <tbody id="adjHistoryBody">
                    @forelse ($rows as $row)
                        <tr data-search="{{ $row['search_blob'] ?? '' }} {{ $row['case_no'] ?? '' }} {{ $row['order_ref'] ?? '' }} {{ $row['pricing_request_no'] ?? '' }}">
                            <td>{{ $row['transferred_at_label'] ?? '—' }}</td>
                            <td>
                                <strong>{{ $row['case_no'] ?? '—' }}</strong>
                                <div class="text-xs text-muted">{{ $row['order_ref'] ?? '—' }}</div>
                            </td>
                            <td>{{ $row['patient_name'] ?? '—' }}</td>
                            <td>{{ $row['display_entity'] ?? '—' }}</td>
                            <td>
                                <span class="patient-type-badge {{ ($row['patient_type'] ?? '') === 'military' ? 'military' : 'civilian' }}">
                                    {{ $row['pathway_label'] ?? '—' }}
                                </span>
                            </td>
                            <td>{{ $row['items_count'] ?? 0 }}</td>
                            <td class="font-mono text-xs">{{ $row['pricing_request_no'] ?? '—' }}</td>
                            <td>{{ $row['transferred_by'] ?? '—' }}</td>
                            <td><span class="badge">{{ $row['current_stage_label'] ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr class="adj-history-empty-row">
                            <td colspan="9" class="empty-cell">لا توجد حالات محوّلة في هذه الفترة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
