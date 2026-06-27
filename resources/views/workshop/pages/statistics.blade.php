@php
    $analytics = $workshop_analytics ?? [];
    $meta = $analytics['meta'] ?? [];
    $reports = $analytics['reports'] ?? [];
    $completedRows = $reports['completed_rows'] ?? [];
    $perPage = (int) config('dashboards.table_per_page', 10);
@endphp

<div class="workshop-stats-page" id="workshopStatsPage" data-server-rendered="1">
    <div class="workshop-stats-hero">
        <div class="workshop-stats-hero__text">
            <h2>📊 لوحة إحصائيات ورشة التصنيع</h2>
            <p>
                <span class="workshop-stats-meta">آخر تحديث: {{ $meta['generated_at'] ?? '—' }}</span>
            </p>
        </div>
        <div class="workshop-stats-hero__chips">
            <span class="workshop-stats-chip">📅 اليوم: {{ $meta['today'] ?? '—' }}</span>
            <span class="workshop-stats-chip workshop-stats-chip--accent">🗓️ {{ $meta['month_label'] ?? '—' }}</span>
        </div>
    </div>

    <div id="workshopStatsRoot"></div>

    <div class="workshop-reports-section">
        <div class="workshop-reports-section-title">📋 تقارير الإنتاج والتشغيل</div>

        <div class="workshop-report-cards">
            <div class="workshop-report-card">
                <h4>✅ إنتاج الشهر — {{ $reports['month_label'] ?? '' }}</h4>
                <div class="workshop-report-card__value workshop-report-card__value--green">
                    {{ (int) ($reports['finished_this_month'] ?? 0) }} قطعة
                </div>
                <p class="workshop-report-card__sub">قوائم مواد مُغلقة (تام)</p>
            </div>

            <div class="workshop-report-card">
                <h4>↩️ ارتجاعات — الشهر</h4>
                <div class="workshop-report-card__value workshop-report-card__value--teal">
                    {{ (int) ($reports['returns_this_month'] ?? 0) }} طلب
                </div>
                <p class="workshop-report-card__sub">من الورشة إلى المخزن</p>
            </div>
        </div>

        <div class="workshop-report-cards workshop-report-cards--secondary">
            <div class="workshop-report-card">
                <h4>🔥 أكثر مواد — تحت التشغيل</h4>
                @forelse ($reports['top_wip_items'] ?? [] as $item)
                    <div class="workshop-report-bar">
                        <span class="workshop-report-bar__label">{{ Str::limit($item['label'] ?? '—', 28) }}</span>
                        <div class="workshop-report-bar__track">
                            @php $maxQty = max(1, collect($reports['top_wip_items'] ?? [])->max('value') ?: 1); @endphp
                            <div class="workshop-report-bar__fill" style="width:{{ min(100, round((($item['value'] ?? 0) / $maxQty) * 100)) }}%;background:{{ $item['color'] ?? '#7c3aed' }}"></div>
                        </div>
                        <strong>{{ $item['value'] ?? 0 }}</strong>
                    </div>
                @empty
                    <p class="workshop-report-empty">لا بنود تحت التشغيل حالياً.</p>
                @endforelse
            </div>

            <div class="workshop-report-card">
                <h4>⏳ أطول أوامر تحت التشغيل</h4>
                @forelse ($reports['longest_wip'] ?? [] as $row)
                    <div class="workshop-report-list-item">
                        <span><strong>{{ $row['work_order_no'] ?? '—' }}</strong> — {{ $row['patient'] ?? '—' }}</span>
                        <span class="workshop-report-list-item__meta">{{ $row['stage'] ?? '—' }} · {{ $row['days'] ?? 0 }} ي</span>
                    </div>
                @empty
                    <p class="workshop-report-empty">لا أوامر تحت التشغيل.</p>
                @endforelse
            </div>
        </div>

        <div class="workshop-report-table-panel">
            <div class="workshop-report-table-header">
                <h4>📑 سجل الإنتاج المُكتمل</h4>
                <div class="workshop-report-table-actions">
                    <input type="search" id="workshopCompletedSearch" placeholder="🔍 بحث WO / مريض / حالة..."
                           class="workshop-report-search">
                    <button type="button" id="workshopCompletedExportExcel" class="workshop-export-btn">
                        📥 تصدير Excel
                    </button>
                </div>
            </div>

            <div class="workshop-report-table-wrap">
                <table id="workshopCompletedTable" data-paginate="{{ $perPage }}" class="workshop-report-table">
                    <thead>
                        <tr>
                            <th>أمر التشغيل</th>
                            <th>المريض</th>
                            <th>رقم الحالة</th>
                            <th>المسار</th>
                            <th>تاريخ الإغلاق</th>
                            <th>مدة التصنيع</th>
                            <th>قائمة المواد</th>
                        </tr>
                    </thead>
                    <tbody id="workshopCompletedTableBody">
                        @forelse ($completedRows as $row)
                            <tr data-search="{{ $row['work_order_no'] }} {{ $row['patient'] }} {{ $row['case_no'] }} {{ $row['path'] }}">
                                <td><span class="workshop-wo-badge">{{ $row['work_order_no'] }}</span></td>
                                <td><strong>{{ $row['patient'] }}</strong></td>
                                <td class="font-mono">{{ $row['case_no'] }}</td>
                                <td>
                                    <span class="workshop-path-badge {{ $row['path'] === 'عسكري' ? 'workshop-path-badge--mil' : 'workshop-path-badge--civ' }}">
                                        {{ $row['path'] === 'عسكري' ? '🪖' : '🌐' }} {{ $row['path'] }}
                                    </span>
                                </td>
                                <td>{{ $row['finished_at'] }}</td>
                                <td>{{ $row['duration_days'] }}</td>
                                <td class="font-mono text-sm">{{ $row['bom_no'] }}</td>
                            </tr>
                        @empty
                            <tr class="pagination-empty-row">
                                <td colspan="7" class="workshop-report-empty-cell">لا توجد قطع مُنجزة مسجّلة بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script type="application/json" id="workshopStatsData">@json($analytics)</script>
</div>
