@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          wh: { DEFAULT: '#7c3aed', dark: '#6d28d9', light: '#f5f3ff' }
        }
      }
    }
  }
</script>
@endpush

@php
    $boms = $warehouse_boms ?? collect();
    $stageMeta = [
        'raw'      => ['label' => '📦 خام', 'cls' => 'bg-amber-100 text-amber-800 border-amber-200'],
        'wip'      => ['label' => '🏭 WIP', 'cls' => 'bg-cyan-100 text-cyan-800 border-cyan-200'],
        'finished' => ['label' => '✅ تام', 'cls' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
    ];
@endphp

<div id="analytics-bom">
    @include('partials.dashboard-analytics-empty', ['stats' => $bom_stats ?? []])
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" id="bomWarehouseRoot">
    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
        <div>
            <h3 class="font-bold text-slate-800">📋 BOM — خام → تحت التشغيل → تام</h3>
            <p class="text-xs text-slate-500 mt-1">صرف للورشة بالباركود — مطابقة صارمة مع بنود BOM الخام</p>
        </div>
        <button type="button" id="btnRefreshBoms"
                class="rounded-xl bg-wh text-white px-4 py-2 text-sm font-bold hover:bg-wh-dark transition-colors">
            ↻ تحديث
        </button>
    </div>

    <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center">
        <input type="search" id="bomSearch" placeholder="🔍 بحث بالمريض أو WO أو BOM..."
               class="flex-1 min-w-[200px] rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-wh/40">
        <div class="flex flex-wrap gap-2" id="bomFilters">
            <button type="button" class="bom-filter active rounded-full px-4 py-1.5 text-xs font-bold bg-slate-800 text-white" data-filter="all">الكل</button>
            <button type="button" class="bom-filter rounded-full px-4 py-1.5 text-xs font-bold bg-amber-100 text-amber-800" data-filter="raw">📦 خام</button>
            <button type="button" class="bom-filter rounded-full px-4 py-1.5 text-xs font-bold bg-cyan-100 text-cyan-800" data-filter="wip">🏭 WIP</button>
            <button type="button" class="bom-filter rounded-full px-4 py-1.5 text-xs font-bold bg-emerald-100 text-emerald-800" data-filter="finished">✅ تام</button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table data-paginate="10" class="w-full text-sm">
            <thead class="bg-slate-100 text-slate-600">
                <tr>
                    <th class="px-4 py-3 text-right font-bold">BOM</th>
                    <th class="px-4 py-3 text-right font-bold">المريض</th>
                    <th class="px-4 py-3 text-right font-bold">WO</th>
                    <th class="px-4 py-3 text-right font-bold">المرحلة</th>
                    <th class="px-4 py-3 text-right font-bold">البنود</th>
                    <th class="px-4 py-3 text-right font-bold">إجراء</th>
                </tr>
            </thead>
            <tbody id="bomTableBody" class="divide-y divide-slate-100">
                @forelse ($boms as $bom)
                    @php $meta = $stageMeta[$bom->stage] ?? ['label' => $bom->stage, 'cls' => 'bg-slate-100']; @endphp
                    <tr class="bom-row hover:bg-slate-50" data-bom-id="{{ $bom->id }}" data-stage="{{ $bom->stage }}"
                        data-search="{{ $bom->bom_no }} {{ $bom->patient_name }} {{ $bom->caseRecord?->work_order_no }}">
                        <td class="px-4 py-3 font-mono font-bold">{{ $bom->bom_no }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $bom->patient_name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-wh">{{ $bom->caseRecord?->work_order_no ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-bold px-2 py-1 rounded-lg border {{ $meta['cls'] }}">{{ $meta['label'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-center font-bold">{{ $bom->items->count() }}</td>
                        <td class="px-4 py-3">
                            @if ($bom->stage === 'raw')
                                <button type="button" class="btn-dispense rounded-xl bg-emerald-600 text-white px-4 py-2 text-xs font-bold hover:bg-emerald-700 shadow-sm"
                                        data-bom-id="{{ $bom->id }}">
                                    📤 صرف للورشة
                                </button>
                            @elseif ($bom->stage === 'wip')
                                <button type="button" class="btn-finish rounded-xl bg-slate-700 text-white px-4 py-2 text-xs font-bold hover:bg-slate-800"
                                        data-bom-id="{{ $bom->id }}">
                                    ✅ إغلاق BOM
                                </button>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد قوائم مواد بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Dispense modal — Tailwind --}}
<div id="dispenseModal" class="fixed inset-0 z-[200] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" id="dispenseBackdrop"></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden animate-[fadeIn_0.15s_ease]">
            <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-l from-emerald-600 to-teal-600 text-white flex items-center justify-between">
                <h3 class="font-bold">📤 صرف للورشة — مسح الباركود</h3>
                <button type="button" id="closeDispenseModal" class="text-2xl leading-none opacity-80 hover:opacity-100">&times;</button>
            </div>
            <div class="p-5 space-y-4">
                <div id="dispenseRequired" class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm space-y-2"></div>
                <div class="flex gap-2">
                    <input type="text" id="barcodeInput" autofocus
                           placeholder="امسح الباركود ثم Enter..."
                           maxlength="100"
                           class="flex-1 rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                    <button type="button" id="btnAddBarcode"
                            class="rounded-xl bg-slate-800 text-white px-4 py-2 text-sm font-bold">إضافة</button>
                </div>
                <div id="scannedList" class="flex flex-wrap gap-2 min-h-[40px]"></div>
                <div id="dispenseAlarm" class="hidden rounded-xl border-2 border-red-500 bg-red-50 p-4 text-red-800 font-bold animate-pulse">
                    ⛔ <span id="dispenseAlarmText">باركود غير مطابق — تم إيقاف الصرف!</span>
                </div>
                <div class="flex gap-3 justify-end pt-2">
                    <button type="button" id="cancelDispense"
                            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">إلغاء</button>
                    <button type="button" id="confirmDispense"
                            class="rounded-xl bg-emerald-600 text-white px-5 py-2.5 text-sm font-bold hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        ✓ تأكيد الصرف
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="bomToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[300] hidden rounded-xl px-6 py-3 text-sm font-bold shadow-lg"></div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
@endpush
