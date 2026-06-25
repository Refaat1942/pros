@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          ops: { DEFAULT: '#0e7490', dark: '#155e75', light: '#ecfeff' }
        }
      }
    }
  }
</script>
@endpush

@php
    $cases   = $ops_cases ?? collect();
    $summary = $ops_summary ?? ['raw' => 0, 'wip' => 0, 'done' => 0];
    $bomLabels = [
        'raw'      => ['label' => 'خام', 'cls' => 'bg-amber-100 text-amber-800'],
        'wip'      => ['label' => 'تحت التشغيل', 'cls' => 'bg-cyan-100 text-cyan-800'],
        'finished' => ['label' => 'تام', 'cls' => 'bg-emerald-100 text-emerald-800'],
    ];
@endphp

<div id="analytics-operations">
    @include('partials.dashboard-analytics-empty', ['stats' => $ops_stats ?? [], 'hide_charts' => true])
</div>

<div class="space-y-6" id="opsDeskRoot" data-cases-count="{{ $cases->count() }}">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-600 leading-relaxed">
            تظهر هنا فقط الحالات التي <strong>صُرفت موادها من المخزن</strong> (تحويل للورشة).
            يمكن طباعة <strong>إذن شغل الورشة</strong> من عمود الإجراء لكل أمر نشط.
            قبل الصرف تبقى الحالة في <strong>لوحة المخزون</strong> فقط.
            يلتقي هنا المساران: <strong class="text-indigo-700">عسكري</strong> و
            <strong class="text-emerald-700">مدني</strong> — كل حالة لها
            <strong>رقم أمر تشغيل مركزي WO-*</strong>.
        </p>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-sm">
            <div class="rounded-xl bg-amber-50 border border-amber-100 py-3">
                <div class="text-2xl font-bold text-amber-700" id="sumRaw">{{ $summary['raw'] }}</div>
                <div class="text-amber-600 mt-1">📦 بانتظار الصرف</div>
            </div>
            <div class="rounded-xl bg-cyan-50 border border-cyan-100 py-3">
                <div class="text-2xl font-bold text-cyan-700" id="sumWip">{{ $summary['wip'] }}</div>
                <div class="text-cyan-600 mt-1">🏭 تحت التشغيل</div>
            </div>
            <div class="rounded-xl bg-emerald-50 border border-emerald-100 py-3">
                <div class="text-2xl font-bold text-emerald-700" id="sumDone">{{ $summary['done'] }}</div>
                <div class="text-emerald-600 mt-1">✅ تم التسليم</div>
            </div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 py-3">
                <div class="text-2xl font-bold text-slate-800" id="sumTotal">{{ $cases->count() }}</div>
                <div class="text-slate-500 mt-1">🎯 إجمالي الأوامر</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
            <h3 class="font-bold text-slate-800">🎯 أوامر التشغيل النشطة</h3>
            <div class="flex items-center gap-2">
                <input type="search" id="opsSearch" placeholder="🔍 بحث WO / مريض / حالة..."
                       class="rounded-xl border border-slate-200 px-4 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-ops/40">
                <button type="button" id="btnRefreshOps"
                        class="rounded-xl bg-ops text-white px-4 py-2 text-sm font-bold hover:bg-ops-dark transition-colors">
                    ↻ تحديث
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table data-paginate="10" class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">أمر التشغيل</th>
                        <th class="px-4 py-3 text-right font-bold">المريض</th>
                        <th class="px-4 py-3 text-right font-bold">المسار</th>
                        <th class="px-4 py-3 text-right font-bold">BOM / الشغل</th>
                        <th class="px-4 py-3 text-right font-bold">البنود</th>
                        <th class="px-4 py-3 text-right font-bold">إجراء</th>
                    </tr>
                </thead>
                <tbody id="opsTableBody" class="divide-y divide-slate-100">
                    @forelse ($cases as $case)
                        @php
                            $bomStage = $case->bom?->stage;
                            $bomMeta  = $bomStage ? ($bomLabels[$bomStage] ?? ['label' => $bomStage, 'cls' => 'bg-slate-100']) : null;
                            $itemsCount = $case->bom?->items?->count() ?? 0;
                            $isMil = $case->isMilitary();
                        @endphp
                        <tr class="ops-row hover:bg-slate-50" data-case-id="{{ $case->id }}"
                            data-search="{{ $case->work_order_no }} {{ $case->case_no }} {{ $case->patient?->name }}">
                            <td class="px-4 py-3 font-mono font-bold text-ops">{{ $case->work_order_no ?? '—' }}</td>
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
                                @if ($bomMeta)
                                    <span class="text-xs font-bold px-2 py-1 rounded-lg {{ $bomMeta['cls'] }}">{{ $bomMeta['label'] }}</span>
                                @else
                                    <span class="text-xs text-slate-400">بدون BOM</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($itemsCount > 0)
                                    <button type="button"
                                            class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50"
                                            data-case-id="{{ $case->id }}"
                                            data-patient="{{ $case->patient?->name ?? '—' }}"
                                            data-case-no="{{ $case->case_no }}"
                                            data-work-order="{{ $case->work_order_no ?? '—' }}"
                                            data-items='@json($case->bom?->items->map(fn ($i) => ["stock_item_code" => $i->stock_item_code, "name" => $i->name, "qty" => $i->qty])->values() ?? [])'>
                                        عرض
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('operations.work-order.print', $case) }}" target="_blank" rel="noopener"
                                   class="text-xs font-bold rounded-lg border border-cyan-700 text-cyan-800 px-3 py-1.5 hover:bg-cyan-50 inline-block mb-1">
                                    🖨️ طباعة إذن شغل الورشة
                                </a>
                                @if ($bomStage === 'wip')
                                    <button type="button" class="btn-complete-manufacturing text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700"
                                            data-case-id="{{ $case->id }}">
                                        ✓ تم التصنيع
                                    </button>
                                @elseif ($bomStage === 'finished')
                                    <span class="text-xs font-bold text-emerald-700">جاهز للتسليم</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر تشغيل نشطة حالياً.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="opsBomItemsModal" class="hidden fixed inset-0 z-[200] bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800">📦 بنود أمر التشغيل</h3>
                <p class="text-xs text-slate-500 mt-1" id="opsBomItemsSubtitle">—</p>
            </div>
            <button type="button" id="closeOpsBomItemsModal" class="text-2xl text-slate-400 hover:text-slate-600">&times;</button>
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
                <tbody id="opsBomItemsBody" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="opsToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[300] hidden rounded-xl px-6 py-3 text-sm font-bold shadow-lg"></div>
