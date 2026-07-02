@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          cashier: { DEFAULT: '#0e7490', dark: '#155e75', light: '#ecfeff' }
        }
      }
    }
  }
</script>
@endpush

@php
    $cases = $cashier_cases ?? collect();
    $methods = $payment_methods ?? [];
@endphp

<div id="analytics-cashier">
    @include('partials.dashboard-analytics-empty', ['stats' => $cashier_stats ?? [], 'hide_charts' => true])
</div>

<div class="space-y-6" id="cashierDeskRoot"
     data-cases-count="{{ $cases->count() }}"
     data-methods='@json($methods)'>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
            <h3 class="font-bold text-slate-800">💵 طابور تحصيل الدفع النقدي</h3>
            <button type="button" id="btnRefreshCashier"
                    class="rounded-xl bg-cashier text-white px-4 py-2 text-sm font-bold hover:bg-cashier-dark transition-colors">
                ↻ تحديث
            </button>
        </div>

        <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center">
            <input type="search" id="cashierSearch" placeholder="🔍 بحث بالحالة / عرض السعر / المريض..."
                   class="flex-1 min-w-[280px] rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cashier/40">
        </div>

        <div class="overflow-x-auto">
            <table data-paginate="10" class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">الحالة</th>
                        <th class="px-4 py-3 text-right font-bold">المريض</th>
                        <th class="px-4 py-3 text-right font-bold">عرض السعر</th>
                        <th class="px-4 py-3 text-right font-bold">المبلغ المطلوب</th>
                        <th class="px-4 py-3 text-right font-bold">إجراء</th>
                    </tr>
                </thead>
                <tbody id="cashierTableBody" class="divide-y divide-slate-100">
                    @forelse ($cases as $case)
                        @php
                            $quote = $case->quotes?->sortByDesc('id')->first();
                            $amount = (float) ($quote?->total ?? $case->quote_total ?? 0);
                        @endphp
                        <tr class="cashier-row hover:bg-slate-50" data-case-id="{{ $case->id }}"
                            data-search="{{ $case->case_no }} {{ $case->quote_no }} {{ $case->patient?->name }}"
                            data-filter-hidden="0">
                            <td class="px-4 py-3">
                                <div class="font-mono font-bold text-cashier">{{ $case->case_no }}</div>
                                <div class="text-xs text-slate-400">{{ $case->order_ref }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $case->patient?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $case->patient?->phone ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $quote?->quote_no ?? $case->quote_no ?? '—' }}</td>
                            <td class="px-4 py-3 font-bold text-emerald-700">{{ number_format($amount, 0) }} ج.م</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($quote)
                                    <a href="{{ route('cashier.quote.print', $quote) }}" target="_blank" rel="noopener"
                                       class="text-xs font-bold rounded-lg border border-cyan-700 text-cyan-800 px-3 py-1.5 hover:bg-cyan-50 inline-block mb-1">
                                        🖨️ طباعة عرض السعر
                                    </a>
                                @endif
                                <button type="button" class="btn-confirm-payment text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700"
                                        data-case-id="{{ $case->id }}"
                                        data-case-no="{{ $case->case_no }}"
                                        data-patient="{{ $case->patient?->name ?? '—' }}"
                                        data-amount="{{ $amount }}">
                                    ✓ تأكيد استلام المبلغ
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">لا توجد حالات بانتظار الدفع حالياً — تظهر بعد إصدار عرض سعر نقدي من مكتب التشغيل.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
