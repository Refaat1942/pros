@php
    $b1 = $board1 ?? [];
    $b2 = $board2 ?? [];
    $b3 = $board3 ?? [];
    $b4 = $board4 ?? [];
    $b5 = $board5 ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0);
    $fmtMoney = fn ($n) => number_format((float) $n, 2);
@endphp
<div class="bi-grid" data-server-rendered="1">

    {{-- ── 1. إدارة المرضى ─────────────────────────────────────────────── --}}
    <div class="bi-card">
        <div class="bi-card-head"><span>👥</span><h4>1. إدارة المرضى</h4></div>
        <div class="bi-card-body">
            <div class="bi-kpi-grid bi-kpi-grid--4">
                <div class="bi-kpi">
                    <div class="bi-kpi-label">إجمالي الحالات</div>
                    <div class="bi-kpi-value">{{ $b1['total_cases'] ?? 0 }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🌐 مدني</div>
                    <div class="bi-kpi-value bi-tone-cyan">{{ $b1['civilian_count'] ?? 0 }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🪖 عسكري</div>
                    <div class="bi-kpi-value bi-tone-amber">{{ $b1['military_count'] ?? 0 }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">حالات مفتوحة</div>
                    <div class="bi-kpi-value">{{ $b1['open_count'] ?? 0 }}</div>
                </div>
            </div>
            <div class="bi-highlight-row">
                <span>⏱️ متوسط زمن التنفيذ (Turnaround)</span>
                <strong>
                    @if(isset($b1['avg_turnaround']) && $b1['avg_turnaround'] !== null)
                        {{ $b1['avg_turnaround'] }} يوم
                    @else
                        —
                    @endif
                </strong>
            </div>
            <div class="bi-section-title">⏱️ حالات متأخرة عن الـ SLA ({{ $b1['sla_days'] ?? 21 }} يوم)</div>
            <div class="bi-alert-box {{ empty($b1['sla_breached_cases']) ? 'bi-alert-box--ok' : 'bi-alert-box--warn' }}">
                <ul class="bi-list">
                    @forelse($b1['sla_breached_cases'] ?? [] as $case)
                        <li>
                            <span class="bi-case-ref">{{ $case['case_no'] }}</span>
                            — {{ $case['patient'] }}
                            <span class="bi-badge bi-badge--danger">{{ $case['days_open'] ?? '?' }} يوم</span>
                        </li>
                    @empty
                        <li>لا توجد حالات متأخرة عن الـ SLA ✅</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- ── 2. المخازن ──────────────────────────────────────────────────── --}}
    <div class="bi-card">
        <div class="bi-card-head"><span>📦</span><h4>2. المخازن وسلاسل الإمداد</h4></div>
        <div class="bi-card-body">
            <div class="bi-kpi-grid">
                <div class="bi-kpi bi-kpi--wide">
                    <div class="bi-kpi-label">القيمة المالية الإجمالية (WAC)</div>
                    <div class="bi-kpi-value bi-tone-cyan">{{ $fmtMoney($b2['total_value'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">عدد الأصناف</div>
                    <div class="bi-kpi-value">{{ $b2['item_count'] ?? 0 }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🚨 أصناف ناقصة</div>
                    <div class="bi-kpi-value bi-tone-red">{{ $b2['low_stock'] ?? 0 }}</div>
                </div>
            </div>
            <div class="bi-section-title">🐌 أصناف راكدة (&gt;180 يوم)</div>
            <div class="bi-table-wrap">
                <table class="bi-table" data-paginate="8">
                    <thead>
                        <tr>
                            <th class="text">الكود</th>
                            <th class="text">الصنف</th>
                            <th class="num">الكمية</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($b2['stagnant_items'] ?? [] as $item)
                            <tr>
                                <td class="text"><span class="bi-code">{{ $item['code'] }}</span></td>
                                <td class="text">{{ $item['name'] }}</td>
                                <td class="num">{{ $item['qty'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="bi-empty-cell">لا توجد أصناف راكدة ✅</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── 3. العمليات ─────────────────────────────────────────────────── --}}
    <div class="bi-card">
        <div class="bi-card-head"><span>🏭</span><h4>3. العمليات والتشغيل</h4></div>
        <div class="bi-card-body">
            <div class="bi-ops-grid">
                <div class="bi-op-stat bi-op-stat--purple">
                    <div class="val">{{ $b3['open_work_orders'] ?? 0 }}</div>
                    <div class="lbl">أوامر تشغيل مفتوحة</div>
                </div>
                <div class="bi-op-stat">
                    <div class="val">{{ $b3['awaiting_dispense'] ?? 0 }}</div>
                    <div class="lbl">بانتظار الصرف</div>
                </div>
                <div class="bi-op-stat">
                    <div class="val">{{ $b3['in_workshop'] ?? 0 }}</div>
                    <div class="lbl">داخل الورش</div>
                </div>
                <div class="bi-op-stat bi-op-stat--green">
                    <div class="val">{{ $b3['ready_for_delivery'] ?? 0 }}</div>
                    <div class="lbl">جاهز للتسليم</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 4. الجهات والتكاليف ─────────────────────────────────────────── --}}
    <div class="bi-card bi-card--wide">
        <div class="bi-card-head"><span>🏢</span><h4>4. الجهات والتكاليف</h4></div>
        <div class="bi-card-body">
            <div class="bi-kpi-grid bi-kpi-grid--4">
                <div class="bi-kpi">
                    <div class="bi-kpi-label">التكلفة التراكمية — مدني</div>
                    <div class="bi-kpi-value bi-tone-cyan">{{ $fmtMoney($b4['civilian_cumulative_cost'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">التكلفة المجمعة — عسكري</div>
                    <div class="bi-kpi-value bi-tone-amber">{{ $fmtMoney($b4['military_aggregated_cost'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🪖 مديونيات — بانتظار التحصيل</div>
                    <div class="bi-kpi-value bi-tone-purple">{{ $fmtMoney($b4['military_debt_pending'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🪖 مديونيات — محصّلة</div>
                    <div class="bi-kpi-value bi-tone-green">{{ $fmtMoney($b4['military_debt_collected'] ?? 0) }} <small>ج.م</small></div>
                </div>
            </div>

            @if(!empty($b4['company_debts']))
                <div class="bi-section-title">📋 تفصيل جهات التعاقد المدنية</div>
                <div class="bi-table-wrap">
                    <table class="bi-table" data-paginate="10">
                        <thead>
                            <tr>
                                <th class="text">الجهة</th>
                                <th class="num">مستحق (ج.م)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($b4['company_debts'] as $debt)
                                <tr>
                                    <td class="text">{{ $debt['company_name'] ?? '—' }}</td>
                                    <td class="num">{{ $fmtMoney($debt['due'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ── 5. المشتريات ─────────────────────────────────────────────────── --}}
    <div class="bi-card bi-card--wide">
        <div class="bi-card-head"><span>🛒</span><h4>5. المشتريات والموردين</h4></div>
        <div class="bi-card-body">
            <div class="bi-kpi bi-kpi--inline">
                <div class="bi-kpi-label">عدد الموردين المعتمدين</div>
                <div class="bi-kpi-value">{{ $b5['supplier_count'] ?? 0 }}</div>
            </div>
            <div class="bi-section-title">⚖️ مقارنة WAC ↔ أعلى سعر شراء</div>
            <div class="bi-table-wrap">
                <table class="bi-table" data-paginate="10">
                    <thead>
                        <tr>
                            <th class="text">الصنف</th>
                            <th class="num">WAC</th>
                            <th class="num">أعلى سعر</th>
                            <th class="num">الفرق</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($b5['price_comparison'] ?? [] as $row)
                            <tr @if($row['margin_erosion'] ?? false) class="bi-row-warn" @endif>
                                <td class="text">
                                    <span class="bi-code">{{ $row['code'] }}</span>
                                    @if(!empty($row['name']))
                                        <span class="bi-item-name">{{ $row['name'] }}</span>
                                    @endif
                                </td>
                                <td class="num">{{ number_format($row['wac'], 2) }}</td>
                                <td class="num">{{ number_format($row['highest_purchase_price'], 2) }}</td>
                                <td class="num">
                                    @if($row['margin_erosion'] ?? false)
                                        <span class="bi-badge bi-badge--danger">+{{ number_format($row['diff'], 2) }}</span>
                                    @else
                                        {{ number_format($row['diff'], 2) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="bi-empty-cell">لا توجد أصناف للمقارنة</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
