@php
    $b1 = $board1 ?? [];
    $b2 = $board2 ?? [];
    $b3 = $board3 ?? [];
    $b4 = $board4 ?? [];
    $b5 = $board5 ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0);
    $fmtMoney = fn ($n) => number_format((float) $n, 2);

    $totalCases = max(0, (int) ($b1['total_cases'] ?? 0));
    $civCount = (int) ($b1['civilian_count'] ?? 0);
    $milCount = (int) ($b1['military_count'] ?? 0);
    $civPct = $totalCases > 0 ? round(($civCount / $totalCases) * 100) : 0;
    $milPct = $totalCases > 0 ? round(($milCount / $totalCases) * 100) : 0;

    $itemCount = (int) ($b2['item_count'] ?? 0);
    $lowStock = (int) ($b2['low_stock'] ?? 0);
    $healthPct = $itemCount > 0 ? (int) round((($itemCount - $lowStock) / $itemCount) * 100) : 100;
    $healthTone = $healthPct >= 70 ? 'ok' : ($healthPct >= 40 ? 'mid' : 'low');

    $slaCases = $b1['sla_breached_cases'] ?? [];
    $slaCount = (int) ($b1['sla_breached'] ?? count($slaCases));

    $opsTotal = max(1, (int) ($b3['open_work_orders'] ?? 0) + (int) ($b3['ready_for_delivery'] ?? 0));
    $opsSteps = [
        ['key' => 'dispense', 'label' => 'بانتظار الصرف', 'val' => (int) ($b3['awaiting_dispense'] ?? 0), 'tone' => 'amber'],
        ['key' => 'workshop', 'label' => 'داخل الورش', 'val' => (int) ($b3['in_workshop'] ?? 0), 'tone' => 'purple'],
        ['key' => 'open', 'label' => 'أوامر تشغيل', 'val' => (int) ($b3['open_work_orders'] ?? 0), 'tone' => 'indigo'],
        ['key' => 'ready', 'label' => 'جاهز للتسليم', 'val' => (int) ($b3['ready_for_delivery'] ?? 0), 'tone' => 'green'],
    ];
@endphp
<div class="bi-grid" data-server-rendered="1">

    {{-- ── 1. إدارة المرضى ─────────────────────────────────────────────── --}}
    <article class="bi-card bi-card--patients" id="bi-board-1">
        <header class="bi-card-head">
            <span class="bi-card-icon bi-card-icon--patients" aria-hidden="true">👥</span>
            <div class="bi-card-head__text">
                <span class="bi-card-index">اللوحة 1</span>
                <h4>إدارة المرضى والمواعيد المتفقة للتسليم</h4>
            </div>
            @if ($slaCount > 0)
                <span class="bi-card-chip bi-card-chip--danger">{{ $slaCount }} متأخر</span>
            @else
                <span class="bi-card-chip bi-card-chip--ok">ضمن الموعد المتفق</span>
            @endif
        </header>
        <div class="bi-card-body">
            <div class="bi-kpi-grid bi-kpi-grid--4">
                <div class="bi-kpi bi-kpi--accent">
                    <div class="bi-kpi-label">إجمالي الحالات</div>
                    <div class="bi-kpi-value">{{ $b1['total_cases'] ?? 0 }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🌐 مدني</div>
                    <div class="bi-kpi-value bi-tone-cyan">{{ $civCount }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🪖 عسكري</div>
                    <div class="bi-kpi-value bi-tone-amber">{{ $milCount }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">حالات قيد المسار</div>
                    <div class="bi-kpi-value">{{ $b1['open_count'] ?? 0 }}</div>
                </div>
            </div>

            <div class="bi-split-block">
                <div class="bi-split-bar" role="img" aria-label="توزيع مدني {{ $civPct }}% وعسكري {{ $milPct }}%">
                    @if ($civPct > 0)
                        <div class="bi-split-bar__civ" style="width:{{ $civPct }}%"></div>
                    @endif
                    @if ($milPct > 0)
                        <div class="bi-split-bar__mil" style="width:{{ $milPct }}%"></div>
                    @endif
                </div>
                <div class="bi-split-legend">
                    <span><i class="bi-dot bi-dot--cyan"></i> مدني {{ $civPct }}%</span>
                    <span><i class="bi-dot bi-dot--amber"></i> عسكري {{ $milPct }}%</span>
                </div>
            </div>

            <div class="bi-metric-pill">
                <span>⏱️ متوسط مدة إنجاز الحالة</span>
                <strong>
                    @if(isset($b1['avg_turnaround']) && $b1['avg_turnaround'] !== null)
                        {{ $b1['avg_turnaround'] }} يوم
                    @else
                        —
                    @endif
                </strong>
            </div>

            <div class="bi-section-title">⏱️ حالات متأخرة عن الموعد المتفق ({{ $b1['sla_days'] ?? 21 }} يوم)</div>
            <div class="bi-alert-box {{ empty($slaCases) ? 'bi-alert-box--ok' : 'bi-alert-box--warn' }}">
                @if (empty($slaCases))
                    <p class="bi-alert-empty">لا توجد حالات متأخرة عن الموعد المتفق ✅</p>
                @else
                    <ul class="bi-sla-list">
                        @foreach($slaCases as $case)
                            <li>
                                <span class="bi-case-ref">{{ $case['case_no'] }}</span>
                                <span class="bi-sla-patient">{{ $case['patient'] }}</span>
                                <span class="bi-badge bi-badge--danger">{{ $case['days_open'] ?? '?' }} يوم</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </article>

    {{-- ── 2. المخازن ──────────────────────────────────────────────────── --}}
    <article class="bi-card bi-card--inventory" id="bi-board-2">
        <header class="bi-card-head">
            <span class="bi-card-icon bi-card-icon--inventory" aria-hidden="true">📦</span>
            <div class="bi-card-head__text">
                <span class="bi-card-index">اللوحة 2</span>
                <h4>المخازن وسلاسل الإمداد</h4>
            </div>
            <span class="bi-card-chip bi-card-chip--{{ $healthTone }}">{{ $healthPct }}% صحة</span>
        </header>
        <div class="bi-card-body">
            <div class="bi-inventory-hero">
                <div class="bi-health-ring bi-health-ring--{{ $healthTone }}" style="--health-pct: {{ $healthPct }}">
                    <span class="bi-health-ring__value">{{ $healthPct }}%</span>
                </div>
                <div class="bi-inventory-hero__meta">
                    <div class="bi-inventory-hero__value bi-tone-cyan">
                        {{ $fmtMoney($b2['total_value'] ?? 0) }} <small>ج.م</small>
                    </div>
                    <p>القيمة المالية الإجمالية — متوسط التكلفة المرجح</p>
                    <div class="bi-inventory-tags">
                        <span>{{ $itemCount }} صنف</span>
                    </div>
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
    </article>

    {{-- ── 3. العمليات ─────────────────────────────────────────────────── --}}
    <article class="bi-card bi-card--operations" id="bi-board-3">
        <header class="bi-card-head">
            <span class="bi-card-icon bi-card-icon--operations" aria-hidden="true">🏭</span>
            <div class="bi-card-head__text">
                <span class="bi-card-index">اللوحة 3</span>
                <h4>العمليات والتشغيل</h4>
            </div>
            <span class="bi-card-chip">{{ $b3['open_work_orders'] ?? 0 }} أمر نشط</span>
        </header>
        <div class="bi-card-body">
            <div class="bi-pipeline">
                @foreach ($opsSteps as $i => $step)
                    @if ($i > 0)
                        <div class="bi-pipeline__connector" aria-hidden="true"></div>
                    @endif
                    <div class="bi-pipeline__step bi-pipeline__step--{{ $step['tone'] }}">
                        <span class="bi-pipeline__val">{{ $step['val'] }}</span>
                        <span class="bi-pipeline__lbl">{{ $step['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="bi-pipeline-hint">مسار الإنتاج من الصرف حتى الجاهزية للتسليم — أرقام لحظية من أوامر الشغل.</p>
        </div>
    </article>

    {{-- ── 4. الجهات والتكاليف ─────────────────────────────────────────── --}}
    <article class="bi-card bi-card--wide bi-card--entities" id="bi-board-4">
        <header class="bi-card-head">
            <span class="bi-card-icon bi-card-icon--entities" aria-hidden="true">🏢</span>
            <div class="bi-card-head__text">
                <span class="bi-card-index">اللوحة 4</span>
                <h4>الجهات والتكاليف</h4>
            </div>
        </header>
        <div class="bi-card-body">
            <div class="bi-kpi-grid bi-kpi-grid--4">
                <div class="bi-kpi">
                    <div class="bi-kpi-label">التكلفة التراكمية — مدني</div>
                    <div class="bi-kpi-value bi-tone-cyan">{{ $fmtMoney($b4['civilian_cumulative_cost'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">💵 محصّل نقدي — الخزنة</div>
                    <div class="bi-kpi-value bi-tone-green">{{ $fmtMoney($b4['cash_collected_total'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">💵 بانتظار الدفع — الخزنة</div>
                    <div class="bi-kpi-value bi-tone-cyan">{{ $b4['cash_awaiting_payment'] ?? 0 }}</div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">التكلفة المجمعة — عسكري</div>
                    <div class="bi-kpi-value bi-tone-amber">{{ $fmtMoney($b4['military_aggregated_cost'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🪖 مديونيات بانتظار التحصيل</div>
                    <div class="bi-kpi-value bi-tone-purple">{{ $fmtMoney($b4['military_debt_pending'] ?? 0) }} <small>ج.م</small></div>
                </div>
                <div class="bi-kpi">
                    <div class="bi-kpi-label">🪖 مديونيات محصّلة</div>
                    <div class="bi-kpi-value bi-tone-green">{{ $fmtMoney($b4['military_debt_collected'] ?? 0) }} <small>ج.م</small></div>
                </div>
            </div>
        </div>
    </article>

    {{-- ── 5. المشتريات ─────────────────────────────────────────────────── --}}
    <article class="bi-card bi-card--wide bi-card--purchasing" id="bi-board-5">
        <header class="bi-card-head">
            <span class="bi-card-icon bi-card-icon--purchasing" aria-hidden="true">🛒</span>
            <div class="bi-card-head__text">
                <span class="bi-card-index">اللوحة 5</span>
                <h4>المشتريات والموردين</h4>
            </div>
            <span class="bi-card-chip">{{ $b5['supplier_count'] ?? 0 }} مورد معتمد</span>
        </header>
        <div class="bi-card-body">
            <div class="bi-section-title">⚖️ مقارنة متوسط التكلفة ↔ أعلى سعر شراء</div>
            <div class="bi-table-wrap">
                <table class="bi-table bi-table--purchasing" data-paginate="10">
                    <thead>
                        <tr>
                            <th class="text">الصنف</th>
                            <th class="num">متوسط التكلفة</th>
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
    </article>
</div>
