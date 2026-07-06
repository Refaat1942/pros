@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          workshop: { DEFAULT: '#7c3aed', dark: '#6d28d9', light: '#f5f3ff' }
        }
      }
    }
  }
</script>
@endpush

@php
    $cases = $workshop_cases ?? collect();
@endphp

<div id="analytics-workshop">
    @include('partials.dashboard-analytics-empty', ['stats' => $workshop_stats ?? [], 'hide_charts' => true])
</div>

<div class="space-y-6" id="workshopDeskRoot" data-cases-count="{{ $cases->count() }}">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
            <h3 class="font-bold text-slate-800">🏭 طابور ورشة التصنيع</h3>
            <button type="button" id="btnRefreshWorkshop"
                    class="rounded-xl bg-workshop text-white px-4 py-2 text-sm font-bold hover:bg-workshop-dark transition-colors">
                ↻ تحديث
            </button>
        </div>

        <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center">
            <input type="search" id="workshopSearch" placeholder="🔍 بحث WO / مريض / حالة..."
                   class="flex-1 min-w-[280px] rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-workshop/40">
            <div class="flex flex-wrap gap-2" id="workshopFilters">
                <button type="button" class="workshop-filter active rounded-full px-4 py-1.5 text-xs font-bold bg-slate-800 text-white" data-filter="all">الكل</button>
                <button type="button" class="workshop-filter rounded-full px-4 py-1.5 text-xs font-bold bg-indigo-100 text-indigo-700" data-filter="military">🪖 عسكري</button>
                <button type="button" class="workshop-filter rounded-full px-4 py-1.5 text-xs font-bold bg-emerald-100 text-emerald-700" data-filter="civilian">🌐 مدني</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table data-paginate="10" class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">أمر التشغيل</th>
                        <th class="px-4 py-3 text-right font-bold">المريض</th>
                        <th class="px-4 py-3 text-right font-bold">المسار</th>
                        <th class="px-4 py-3 text-right font-bold">مرحلة التصنيع</th>
                        <th class="px-4 py-3 text-right font-bold">عدد الأصناف</th>
                        <th class="px-4 py-3 text-right font-bold">إجراء</th>
                    </tr>
                </thead>
                <tbody id="workshopTableBody" class="divide-y divide-slate-100">
                    @forelse ($cases as $case)
                        @php
                            $itemsCount = $case->bom?->items?->isNotEmpty()
                                ? \App\Support\BomItemAggregator::uniqueCodeCount($case->bom->items)
                                : 0;
                            $isMil = $case->isMilitary();
                            $mfgLabel = \App\Enums\ManufacturingStage::workshopDeskLabelFor($case->manufacturing_stage);
                        @endphp
                        <tr class="workshop-row hover:bg-slate-50" data-case-id="{{ $case->id }}"
                            data-search="{{ $case->work_order_no }} {{ $case->case_no }} {{ $case->patient?->name }}"
                            data-path="{{ $isMil ? 'military' : 'civilian' }}"
                            data-filter-hidden="0">
                            <td class="px-4 py-3 font-mono font-bold text-workshop">{{ $case->work_order_no ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $case->patient?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $case->case_no }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold px-2 py-1 rounded-lg {{ $isMil ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $isMil ? '🪖 عسكري' : '🌐 مدني' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-cyan-100 text-cyan-800">{{ $mfgLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($itemsCount > 0)
                                    <button type="button"
                                            class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50"
                                            data-case-id="{{ $case->id }}"
                                            data-patient="{{ $case->patient?->name ?? '—' }}"
                                            data-case-no="{{ $case->case_no }}"
                                            data-work-order="{{ $case->work_order_no ?? '—' }}"
                                            data-items='@json(\App\Support\BomItemAggregator::byStockCode($case->bom->items))'>
                                        عرض
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('workshop.work-order.print', $case) }}" target="_blank" rel="noopener"
                                   class="text-xs font-bold rounded-lg border border-violet-700 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block mb-1">
                                    🖨️ طباعة إذن شغل
                                </a>
                                <button type="button" class="btn-complete-manufacturing text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700"
                                        data-case-id="{{ $case->id }}">
                                    ✓ تم التصنيع
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر في الورشة حالياً — تظهر بعد صرف المواد من المخزن.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="workshopBomItemsModal" class="hidden fixed inset-0 z-[200] bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800">📦 بنود أمر التشغيل</h3>
                <p class="text-xs text-slate-500 mt-1" id="workshopBomItemsSubtitle">—</p>
            </div>
            <button type="button" id="closeWorkshopBomItemsModal" class="text-2xl text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <div class="overflow-y-auto flex-1 p-4">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-right font-bold">الكود</th>
                        <th class="px-3 py-2 text-right font-bold">الصنف</th>
                        <th class="px-3 py-2 text-right font-bold w-20">الكمية</th>
                    </tr>
                </thead>
                <tbody id="workshopBomItemsBody" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>
