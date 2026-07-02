@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: { fontFamily: { sans: ['Tajawal', 'sans-serif'] } } } }
</script>
@endpush

@php
    $payments = $cashier_payments ?? collect();
    $byMethod = $cashier_by_method ?? [];
@endphp

<div id="analytics-cashier-stats">
    @include('partials.dashboard-analytics-empty', ['stats' => $cashier_stats_totals ?? [], 'hide_charts' => true])
</div>

<div class="grid gap-6 lg:grid-cols-3 mt-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
            <h3 class="font-bold text-slate-800">📊 التحصيل حسب وسيلة الدفع (هذا الشهر)</h3>
        </div>
        <div class="p-4 space-y-3">
            @forelse ($byMethod as $row)
                <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                    <span class="font-semibold text-slate-700">{{ $row['method'] }}</span>
                    <span class="text-xs text-slate-400">{{ $row['count'] }} دفعة</span>
                    <span class="font-bold text-emerald-700">{{ number_format($row['total'], 0) }} ج.م</span>
                </div>
            @empty
                <p class="text-center text-slate-400 py-6">لا توجد دفعات بعد.</p>
            @endforelse
        </div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
            <h3 class="font-bold text-slate-800">🧾 آخر الدفعات المحصّلة</h3>
        </div>
        <div class="overflow-x-auto">
            <table data-paginate="10" class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">رقم الدفعة</th>
                        <th class="px-4 py-3 text-right font-bold">المريض</th>
                        <th class="px-4 py-3 text-right font-bold">الحالة</th>
                        <th class="px-4 py-3 text-right font-bold">الوسيلة</th>
                        <th class="px-4 py-3 text-right font-bold">المبلغ</th>
                        <th class="px-4 py-3 text-right font-bold">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-bold text-cyan-700">{{ $payment->payment_no }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $payment->patient_name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $payment->caseRecord?->case_no ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-cyan-100 text-cyan-800">{{ $payment->methodLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 font-bold text-emerald-700">{{ number_format((float) $payment->amount, 0) }} ج.م</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ \App\Support\ClinicTime::format($payment->received_at, 'd/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد دفعات محصّلة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
