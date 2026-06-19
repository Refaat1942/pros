@php
    $b1 = $board1 ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0);
    $fmtMoney = fn ($n) => number_format((float) $n, 2) . ' ج.م';
@endphp
<div class="bi-grid" data-server-rendered="1">
    <div class="bi-card">
        <div class="bi-card-head"><span>👥</span><h4>1. إدارة المرضى</h4></div>
        <div class="bi-card-body">
            <div class="bi-row"><span>إجمالي الحالات</span><strong>{{ $b1['total_cases'] ?? 0 }}</strong></div>
            <div class="bi-row"><span>🌐 مدني</span><strong style="color:#0e7490">{{ $b1['civilian_count'] ?? 0 }}</strong></div>
            <div class="bi-row"><span>🪖 عسكري</span><strong style="color:#b45309">{{ $b1['military_count'] ?? 0 }}</strong></div>
            <div class="bi-row">
                <span>متوسط زمن التنفيذ (Turnaround)</span>
                <strong>
                    @if(isset($b1['avg_turnaround']) && $b1['avg_turnaround'] !== null)
                        {{ $b1['avg_turnaround'] }} يوم
                    @else
                        —
                    @endif
                </strong>
            </div>
            <div class="bi-row"><span>حالات مفتوحة</span><strong>{{ $b1['open_count'] ?? 0 }}</strong></div>
            <div class="bi-sub">⏱️ حالات متأخرة عن الـ SLA ({{ $b1['sla_days'] ?? 21 }} يوم):
                <ul class="bi-list">
                    @forelse($b1['sla_breached_cases'] ?? [] as $case)
                        <li style="color:#b91c1c">
                            {{ $case['case_no'] }} — {{ $case['patient'] }}
                            ({{ $case['days_open'] ?? '?' }} يوم)
                        </li>
                    @empty
                        <li style="color:var(--accent,#059669)">لا توجد حالات متأخرة عن الـ SLA ✅</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @php $b2 = $board2 ?? []; @endphp
    <div class="bi-card">
        <div class="bi-card-head"><span>📦</span><h4>2. المخازن وسلاسل الإمداد</h4></div>
        <div class="bi-card-body">
            <div class="bi-row"><span>القيمة المالية الإجمالية (WAC)</span><strong style="color:#0e7490">{{ $fmtMoney($b2['total_value'] ?? 0) }}</strong></div>
            <div class="bi-row"><span>عدد الأصناف</span><strong>{{ $b2['item_count'] ?? 0 }}</strong></div>
            <div class="bi-row"><span>🚨 أصناف ناقصة (قرب حد الأمان)</span><strong style="color:#b91c1c">{{ $b2['low_stock'] ?? 0 }}</strong></div>
            <div class="bi-sub">🐌 أصناف راكدة (&gt;180 يوم):
                <ul class="bi-list">
                    @forelse($b2['stagnant_items'] ?? [] as $item)
                        <li>{{ $item['code'] }} — {{ $item['name'] }} ({{ $item['qty'] }})</li>
                    @empty
                        <li>لا يوجد ✅</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @php $b3 = $board3 ?? []; @endphp
    <div class="bi-card">
        <div class="bi-card-head"><span>🏭</span><h4>3. العمليات والتشغيل</h4></div>
        <div class="bi-card-body">
            <div class="bi-row"><span>أوامر التشغيل المفتوحة</span><strong style="color:#7c3aed">{{ $b3['open_work_orders'] ?? 0 }}</strong></div>
            <div class="bi-row"><span>بانتظار الصرف</span><strong>{{ $b3['awaiting_dispense'] ?? 0 }}</strong></div>
            <div class="bi-row"><span>داخل الورش حالياً</span><strong>{{ $b3['in_workshop'] ?? 0 }}</strong></div>
            <div class="bi-row"><span>جاهز للتسليم</span><strong style="color:#059669">{{ $b3['ready_for_delivery'] ?? 0 }}</strong></div>
        </div>
    </div>

    @php $b4 = $board4 ?? []; @endphp
    <div class="bi-card">
        <div class="bi-card-head"><span>🏢</span><h4>4. الجهات والتكاليف</h4></div>
        <div class="bi-card-body">
            <div class="bi-row"><span>التكلفة التراكمية — الجهات المدنية</span><strong style="color:#0e7490">{{ $fmtMoney($b4['civilian_cumulative_cost'] ?? 0) }}</strong></div>
            <div class="bi-row"><span>التكلفة المجمعة — العسكرية</span><strong style="color:#b45309">{{ $fmtMoney($b4['military_aggregated_cost'] ?? 0) }}</strong></div>
            <div class="bi-row"><span>مديونيات الجهات (صافي)</span><strong style="color:#b91c1c">{{ $fmtMoney($b4['net_debts'] ?? 0) }}</strong></div>
            @if(!empty($b4['company_debts']))
                <div class="bi-sub">تفصيل الجهات:
                    <table data-paginate="10" class="bi-table">
                        <thead>
                            <tr><th>الجهة</th><th>مستحق</th><th>محصَّل</th><th>متبقٍ</th></tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($b4['company_debts'], 0, 8) as $debt)
                                <tr>
                                    <td>{{ $debt['company_name'] ?? '—' }}</td>
                                    <td>{{ $fmt($debt['due'] ?? 0) }}</td>
                                    <td>{{ $fmt($debt['collected'] ?? 0) }}</td>
                                    <td>{{ $fmt($debt['remaining'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="bi-sub">🪖 التكلفة العسكرية تُرحَّل لحساب الديون السيادية (دون مطالبة دفع).</div>
        </div>
    </div>

    @php $b5 = $board5 ?? []; @endphp
    <div class="bi-card">
        <div class="bi-card-head"><span>🏭</span><h4>5. المشتريات والموردين</h4></div>
        <div class="bi-card-body">
            <div class="bi-row"><span>عدد الموردين المعتمدين</span><strong>{{ $b5['supplier_count'] ?? 0 }}</strong></div>
            <div class="bi-sub">⚖️ مقارنة المتوسط المرجح (WAC) ↔ أعلى سعر شراء:
                <table data-paginate="10" class="bi-table">
                    <thead>
                        <tr><th>الصنف</th><th>WAC</th><th>أعلى سعر</th><th>الفرق</th></tr>
                    </thead>
                    <tbody>
                        @forelse($b5['price_comparison'] ?? [] as $row)
                            <tr @if($row['margin_erosion'] ?? false) style="background:rgba(220,38,38,0.06)" @endif>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ number_format($row['wac'], 2) }}</td>
                                <td>{{ number_format($row['highest_purchase_price'], 2) }}</td>
                                <td @if($row['margin_erosion'] ?? false) style="color:#b91c1c;font-weight:700" @endif>
                                    {{ number_format($row['diff'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="color:var(--text-muted)">لا توجد أصناف</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
