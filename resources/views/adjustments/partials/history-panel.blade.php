@php
    $rows = collect($history_rows ?? []);
    $dateFrom = $date_from ?? now()->startOfMonth()->toDateString();
    $dateTo = $date_to ?? now()->toDateString();
    $searchQuery = $search_query ?? '';
    $exportUrl = route('adjustments.history.export', array_filter([
        'from'   => $dateFrom,
        'to'     => $dateTo,
        'search' => $searchQuery ?: null,
    ]));
@endphp

<div class="panel inventory-wrap adj-history-panel" id="adj-history-section">
    <div class="panel-header">
        <h3>📤 سجل المحوّلين للتكاليف</h3>
        <span class="badge" id="adjHistoryCount">{{ $rows->count() }} حالة</span>
    </div>

    <div class="adj-history-filter">
        <form method="GET" action="{{ route('adjustments.adjustments') }}" class="adj-history-filter__form" id="adjHistoryFilter">
            <div class="adj-history-filter__fields">
                <label class="adj-history-filter__field adj-history-filter__field--date">
                    <span class="adj-history-filter__label">من</span>
                    <input type="date" name="from" id="adjHistoryDateFrom" value="{{ $dateFrom }}" class="form-control">
                </label>
                <label class="adj-history-filter__field adj-history-filter__field--date">
                    <span class="adj-history-filter__label">إلى</span>
                    <input type="date" name="to" id="adjHistoryDateTo" value="{{ $dateTo }}" class="form-control">
                </label>
                <label class="adj-history-filter__field adj-history-filter__field--search">
                    <span class="adj-history-filter__label">بحث</span>
                    <input type="search"
                           name="search"
                           id="adjHistorySearch"
                           value="{{ $searchQuery }}"
                           class="form-control"
                           placeholder="🔍 اسم المريض أو رقم الحالة..."
                           autocomplete="off">
                </label>
            </div>
            <div class="adj-history-filter__actions">
                <button type="submit" class="btn-action primary">تطبيق الفلتر</button>
                @if ($dateFrom || $dateTo || $searchQuery)
                    <a href="{{ route('adjustments.adjustments') }}#adj-history-section" class="btn-action adj-history-filter__clear">مسح</a>
                @endif
                <a href="{{ $exportUrl }}" class="btn-export excel" download>📊 Excel</a>
                <span class="adj-history-filter__count" id="adjHistoryVisibleCount">{{ $rows->count() }} حالة</span>
            </div>
        </form>
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
